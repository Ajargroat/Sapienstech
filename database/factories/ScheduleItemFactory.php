<?php

namespace Database\Factories;

use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ScheduleItemFactory extends Factory
{
    protected $model = ScheduleItem::class;

    public function definition(): array
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::SATURDAY);
        $start = $weekStart->copy()->addHours(9);
        $end = $start->copy()->addHour();

        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'week_start_date' => $weekStart->toDateString(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'start_datetime' => $start,
            'end_datetime' => $end,
            'color' => '#3b82f6',
            'item_type' => 'consultant_event',
            'created_by_type' => 'user',
            'created_by_user_id' => User::factory(),
            'created_by_student_id' => null,
            'link_url' => null,
            'book_name' => null,
            'test_count' => null,
            'page_count' => null,
            'is_completed' => false,
            'completion_timestamp' => null,
        ];
    }

    public function studentBlock(): static
    {
        return $this->state(fn () => [
            'item_type' => 'student_personal_block',
            'created_by_type' => 'student',
            'created_by_user_id' => null,
        ]);
    }
}
