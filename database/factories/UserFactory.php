<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * No UserFactory previously existed in database/factories even though
 * App\Models\User uses HasFactory. Added so feature tests (and any future
 * ones) can create consultant/staff users without hand-rolling inserts.
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'consultant_staff',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function consultant(): static
    {
        return $this->state(fn () => ['role' => 'consultant_staff']);
    }

    public function tenantAdmin(): static
    {
        return $this->state(fn () => ['role' => 'tenant_admin']);
    }
}
