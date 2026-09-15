<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ChatActor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chat message: text, a system notice ("کاربر عضو شد"), or a
 * text/attachment hybrid. Deletion is permanent ("for everyone") — a
 * one-workspace-per-tenant model with no tombstone rows; the legacy
 * deleted_at column is kept only so old tombstones can be purged.
 */
class ChatMessage extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'type',
        'body',
        'sender_user_id',
        'sender_student_id',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'attachment_mime',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function senderStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'sender_student_id');
    }

    public function isSystem(): bool
    {
        return $this->type === self::TYPE_SYSTEM;
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /** The sending actor, or null for system messages / deleted accounts. */
    public function sender(): ?ChatActor
    {
        if ($this->isSystem()) {
            return null;
        }

        $model = $this->sender_student_id ? $this->senderStudent : $this->senderUser;

        return $model ? ChatActor::make($model) : null;
    }

    /**
     * Wire format consumed by the chat JS module in BOTH portals (and by
     * broadcast payloads), so consultant and student render from one shape.
     */
    public function toPayloadArray(?ChatActor $viewer = null): array
    {
        $sender = $this->sender();

        return [
            'id' => (int) $this->id,
            'type' => $this->type,
            'body' => $this->body,
            'edited' => $this->edited_at !== null,
            'sender' => $sender ? [
                'key' => $sender->key(),
                'type' => $sender->type(),
                'name' => $sender->name(),
                'avatar' => $sender->avatar() ? tenant_asset($sender->avatar()) : null,
            ] : null,
            'mine' => $viewer && $sender && $viewer->key() === $sender->key(),
            'attachment' => $this->hasAttachment() ? [
                'url' => tenant_asset((string) $this->attachment_path),
                'name' => $this->attachment_name,
                'size' => (int) $this->attachment_size,
                'mime' => $this->attachment_mime,
            ] : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
