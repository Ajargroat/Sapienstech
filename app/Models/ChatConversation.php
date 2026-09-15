<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ChatActor;
use App\Support\ChatService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A chat thread.
 *
 * type=direct : staff_user_id <-> student_id (mirrored as two participant rows)
 * type=group  : staff owner + N student members (participants rows only)
 *
 * last_message_id is a denormalized pointer kept fresh by ChatService (not an
 * FK — chat_conversations and chat_messages would form a circular dependency).
 */
class ChatConversation extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_DIRECT = 'direct';
    public const TYPE_GROUP = 'group';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'type',
        'title',
        'staff_user_id',
        'student_id',
        'created_by_user_id',
        'status',
        'last_message_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** Conversations the given actor currently participates in. */
    public function scopeForActor(Builder $query, ChatActor $actor): Builder
    {
        $column = $actor->isStudent() ? 'student_id' : 'user_id';

        // The relation() call inherits the tenant scope from ChatParticipant;
        // whereHas keeps the whole filter in one query for list + count use.
        return $query->whereHas('participants', function (Builder $q) use ($column, $actor) {
            $q->where($column, $actor->id());
        });
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public function isDirect(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }

    /** Private broadcast channel for one open thread. */
    public static function channelName(int $conversationId): string
    {
        return 'chat.conversation.'.$conversationId;
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * A closed thread blocks everyone from writing; the config switch lets
     * students keep chatting in closed direct threads if a tenant prefers
     * (consultants can always reopen via the PATCH endpoint).
     */
    public function isClosedFor(ChatActor $actor): bool
    {
        if ($this->isOpen()) {
            return false;
        }

        if (! $actor->isStudent() || ! ChatService::config('closed_threads_readable_by_students', false)) {
            return true;
        }

        return false;
    }

    /** The actor's membership row, if any. */
    public function participantFor(ChatActor $actor): ?ChatParticipant
    {
        $column = $actor->isStudent() ? 'student_id' : 'user_id';

        if ($this->relationLoaded('participants')) {
            return $this->participants->firstWhere($column, $actor->id());
        }

        return $this->participants()->where($column, $actor->id())->first();
    }

    /** The staff participant of a direct conversation (the consultant side). */
    public function staffActor(): ?ChatActor
    {
        return $this->staff ? ChatActor::make($this->staff) : null;
    }

    /**
     * The other participant from the caller's point of view — used for the
     * list-row title of direct threads (student name / consultant name).
     */
    public function displayName(?ChatActor $viewer = null): string
    {
        if ($this->isGroup() || ! $viewer) {
            return (string) ($this->title ?: 'گفتگوی گروهی');
        }

        if ($viewer->isStudent()) {
            return $this->staff?->name ?? 'مشاور';
        }

        return $this->student?->name ?? 'دانش‌آموز';
    }

    /**
     * JSON shape for the conversation list (consultant and student sides use
     * the same envelope so one JS module can drive both portals).
     */
    public function toListArray(?ChatActor $viewer = null, int $unread = 0): array
    {
        $last = $this->lastMessage;

        return [
            'id' => (int) $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'title' => $this->displayName($viewer),
            'unread' => $unread,
            'participant_count' => $this->isGroup()
                ? ($this->relationLoaded('participants') ? $this->participants->count() : $this->participants()->count())
                : null,
            'last_message' => $last ? $last->toPayloadArray($viewer) : null,
            'updated_at' => optional($this->last_message_at ?? $this->updated_at)->toIso8601String(),
        ];
    }
}
