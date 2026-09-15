<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ChatActor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership row: exactly one of user_id / student_id is set (the dual-FK
 * pattern — see App\Support\ChatActor). Also carries per-actor read state.
 */
class ChatParticipant extends Model
{
    use BelongsToTenant, HasFactory;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'user_id',
        'student_id',
        'role',
        'last_read_message_id',
        'last_read_at',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * NOT named actor(): Eloquent treats a single-word method ending
     * without args as a relation via the magic attribute getter.
     */
    public function toActor(): ChatActor
    {
        return ChatActor::make($this->student_id ? $this->student : $this->user);
    }

    /** Dual-FK key without loading the relation (safe on raw rows). */
    public function actorKey(): ?string
    {
        return ChatActor::keyFromColumns($this->user_id, $this->student_id);
    }

    public function actorName(): string
    {
        return $this->toActor()->name();
    }
}
