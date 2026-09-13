<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatParticipantFactory extends Factory
{
    protected $model = ChatParticipant::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'conversation_id' => ChatConversation::factory(),
            'user_id' => User::factory(),
            'student_id' => null,
            'role' => ChatParticipant::ROLE_MEMBER,
            'last_read_message_id' => null,
            'last_read_at' => null,
            'joined_at' => now(),
        ];
    }

    public function student(Student $student): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'student_id' => $student->id,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => ChatParticipant::ROLE_OWNER]);
    }
}
