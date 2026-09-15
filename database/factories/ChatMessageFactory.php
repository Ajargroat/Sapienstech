<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'conversation_id' => ChatConversation::factory(),
            'type' => ChatMessage::TYPE_TEXT,
            'body' => fake()->sentence(),
            'sender_user_id' => User::factory(),
            'sender_student_id' => null,
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_size' => null,
            'attachment_mime' => null,
            'edited_at' => null,
        ];
    }

    public function fromStudent(Student $student): static
    {
        return $this->state(fn () => [
            'sender_user_id' => null,
            'sender_student_id' => $student->id,
        ]);
    }

    public function system(string $body): static
    {
        return $this->state(fn () => [
            'type' => ChatMessage::TYPE_SYSTEM,
            'body' => $body,
            'sender_user_id' => null,
            'sender_student_id' => null,
        ]);
    }
}
