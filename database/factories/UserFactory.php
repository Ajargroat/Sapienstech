<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IranianIdentity;
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
            // NULL = not pinned to a specific domain: the user may log in
            // through any of the tenant's domains (tenant admins and all
            // pre-existing accounts). pinnedTo() restricts a consultant
            // to a single domain.
            'domain_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // NULL = the account logs in by email only (legacy shape).
            // withIdentity() fills in the phone / کد ملی pair.
            'phone' => null,
            'national_code' => null,
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

    public function teacher(): static
    {
        return $this->state(fn () => ['role' => 'teacher']);
    }

    /**
     * Give the account a phone + کد ملی so it can log in by either.
     */
    public function withIdentity(?string $phone = null, ?string $nationalCode = null): static
    {
        return $this->state(fn () => [
            'phone' => $phone ?? sprintf('09%09d', fake()->unique()->numberBetween(0, 999999999)),
            'national_code' => $nationalCode ?? IranianIdentity::randomValidNationalCode(),
        ]);
    }

    /**
     * Pin the user to a single domain: they may only log in through it.
     */
    public function pinnedTo(Domain $domain): static
    {
        return $this->state(fn () => ['domain_id' => $domain->id]);
    }
}
