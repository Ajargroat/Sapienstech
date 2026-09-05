<?php

namespace Database\Factories;

use App\Models\ExamCompany;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExamCompanyFactory extends Factory
{
    protected $model = ExamCompany::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name) ?: Str::random(8),
            'color' => fake()->hexColor(),
        ];
    }
}
