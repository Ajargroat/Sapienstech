<?php

namespace Database\Factories;

use App\Models\Student;
use App\Support\IranianIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->firstName(),
            'email' => $this->faker->unique()->safeEmail(),
            // NULL = the account logs in by email only (legacy shape).
            // withIdentity() fills in the phone / کد ملی pair.
            'phone' => null,
            'national_code' => null,
            'password' => Hash::make('password'),
        ];
    }

    /**
     * Give the account a phone + کد ملی so it can log in by either.
     */
    public function withIdentity(?string $phone = null, ?string $nationalCode = null): static
    {
        return $this->state(fn () => [
            'phone' => $phone ?? sprintf('09%09d', $this->faker->unique()->numberBetween(0, 999999999)),
            'national_code' => $nationalCode ?? IranianIdentity::randomValidNationalCode(),
        ]);
    }
}
