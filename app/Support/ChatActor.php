<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\Student;
use App\Models\User;

/**
 * A chat actor: either a staff User (web guard) or a Student (student guard).
 *
 * The platform keeps users.id and students.id as independent sequences that
 * collide, so chat never uses a single polymorphic id. This value object
 * centralizes the "which of the two FK columns / which guard / which key"
 * branching that would otherwise repeat in every query, policy and payload.
 *
 * The stable string form ("u:123" / "s:45") is what crosses process
 * boundaries: broadcast channel names, JSON payloads and RateLimiter keys.
 */
final class ChatActor
{
    public function __construct(public readonly User|Student $model) {}

    public static function make(User|Student $model): self
    {
        return new self($model);
    }

    /** "u:123" for staff, "s:45" for students. */
    public function key(): string
    {
        return ($this->isStudent() ? 's:' : 'u:').$this->model->id;
    }

    /** Key from the raw dual-FK columns, without needing the model. */
    public static function keyFromColumns(?int $userId, ?int $studentId): ?string
    {
        if ($studentId) {
            return 's:'.$studentId;
        }

        return $userId ? 'u:'.$userId : null;
    }

    /**
     * Private broadcast channel carrying one actor's cross-thread events
     * (badges, new-thread announcements). Channel names disallow ':', so the
     * key is dashed: chat.actor.u-123.
     */
    public static function channelName(string $key): string
    {
        return 'chat.actor.'.str_replace(':', '-', $key);
    }

    /** Parse "chat.actor.u-123" style keys back to [type, id]. */
    public static function parseKey(string $key): ?array
    {
        return preg_match('/^([us])-(\d+)$/', $key, $m) ? [$m[1], (int) $m[2]] : null;
    }

    public function type(): string
    {
        return $this->isStudent() ? 'student' : 'user';
    }

    public function isStudent(): bool
    {
        return $this->model instanceof Student;
    }

    public function isStaff(): bool
    {
        return $this->model instanceof User;
    }

    public function id(): int
    {
        return (int) $this->model->id;
    }

    public function name(): string
    {
        return (string) ($this->model->name ?? ($this->isStudent() ? 'دانش‌آموز' : 'مشاور'));
    }

    public function avatar(): ?string
    {
        return $this->model->avatar ?: null;
    }

    /** The FK column pair (sender_user_id/sender_student_id etc.) for this actor. */
    public function foreignKeys(string $prefix): array
    {
        return $this->isStudent()
            ? ["{$prefix}_user_id" => null, "{$prefix}_student_id" => $this->id()]
            : ["{$prefix}_user_id" => $this->id(), "{$prefix}_student_id" => null];
    }

    /**
     * Per-actor preferences live in the existing JSON preferences column
     * (users.preferences / students.preferences), under the "chat" namespace.
     */
    public function preferences(): array
    {
        return (array) ($this->model->preferences['chat'] ?? []);
    }

    public function savePreferences(array $chatPreferences): void
    {
        $all = (array) ($this->model->preferences ?? []);
        $all['chat'] = $chatPreferences;
        $this->model->preferences = $all;
        $this->model->save();
    }

    public function participantRow(ChatConversation $conversation): ?ChatParticipant
    {
        $column = $this->isStudent() ? 'student_id' : 'user_id';

        return $conversation->participants()->where($column, $this->id())->first();
    }
}
