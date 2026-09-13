<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => ChatConversation::TYPE_DIRECT,
            'title' => null,
            'staff_user_id' => User::factory(),
            'student_id' => Student::factory(),
            'created_by_user_id' => null,
            'status' => ChatConversation::STATUS_OPEN,
            'last_message_id' => null,
            'last_message_at' => null,
        ];
    }

    public function group(): static
    {
        return $this->state(fn () => [
            'type' => ChatConversation::TYPE_GROUP,
            'title' => fake()->sentence(2),
            'student_id' => null,
        ]);
    }
}
