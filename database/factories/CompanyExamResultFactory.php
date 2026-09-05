<?php

namespace Database\Factories;

use App\Models\CompanyExamResult;
use App\Models\ExamCompany;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyExamResultFactory extends Factory
{
    protected $model = CompanyExamResult::class;

    public function definition(): array
    {
        $correct = fake()->numberBetween(20, 90);
        $wrong = fake()->numberBetween(0, 30);
        $blank = fake()->numberBetween(0, 30);
        $total = $correct + $wrong + $blank;

        return [
            'student_id' => Student::factory(),
            'company_id' => ExamCompany::factory(),
            'title' => 'آزمون آزمایشی ' . fake()->numberBetween(1, 30),
            'exam_date' => fake()->dateTimeBetween('-8 months', 'now')->format('Y-m-d'),
            'status' => 'completed',
            'total_questions' => $total,
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'blank_count' => $blank,
            'percent' => round($correct / max(1, $total) * 100, 2),
            'exam_rank' => fake()->numberBetween(100, 50000),
            'participants' => fake()->numberBetween(20000, 120000),
            'lesson_percents' => null,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'status' => 'absent',
            'correct_count' => null,
            'wrong_count' => null,
            'blank_count' => null,
            'percent' => null,
            'exam_rank' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'percent' => null,
            'exam_rank' => null,
        ]);
    }
}
