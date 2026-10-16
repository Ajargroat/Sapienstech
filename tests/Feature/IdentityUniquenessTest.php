<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IranianIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The uniqueness edges the identity columns introduce: phone per
 * (tenant_id, phone), کد ملی across the whole platform, and the NULL values
 * legacy accounts still carry.
 */
class IdentityUniquenessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    private function consultantFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
    }

    public function test_phone_may_repeat_across_tenants(): void
    {
        [$tenantA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        $userA = $this->consultantFor($tenantA);
        $userA->forceFill(['phone' => '09121234567'])->save();

        $userB = $this->consultantFor($tenantB);

        // The same number on another tenant's consultant is legal: phone
        // uniqueness is a (tenant_id, phone) key, not a global one.
        $this->actingAs($userB)->patch("http://{$hostB}/consultant/settings/profile", [
            'name' => $userB->name,
            'phone' => '09121234567',
        ])->assertRedirect();

        $this->assertSame('09121234567', $userB->fresh()->phone);
    }

    public function test_persian_digit_input_still_hits_the_tenant_unique_check(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $colleague = User::factory()->consultant()->create([
            'tenant_id' => $tenant->id,
            'phone' => '09121234567',
        ]);

        // Raw Persian digits would slip past a plain unique rule; the request
        // canonicalizes before validating, so they cannot.
        $this->actingAs($user)->patch("http://{$host}/consultant/settings/profile", [
            'name' => $user->name,
            'phone' => '۰۹۱۲۱۲۳۴۵۶۷',
        ])->assertSessionHasErrors('phone');
    }

    public function test_national_code_is_rejected_across_tenants(): void
    {
        [$tenantA] = $this->tenantWithDomain();
        app()->instance('tenant', $tenantA);
        $userA = User::factory()->consultant()->create([
            'tenant_id' => $tenantA->id,
            'national_code' => IranianIdentity::randomValidNationalCode(),
        ]);

        [$tenantB, $hostB] = $this->tenantWithDomain();
        $userB = $this->consultantFor($tenantB);

        // One person, one کد ملی: another tenant's account blocks the code.
        $this->actingAs($userB)->patch("http://{$hostB}/consultant/settings/profile", [
            'name' => $userB->name,
            'national_code' => $userA->national_code,
        ])->assertSessionHasErrors('national_code');
    }

    public function test_a_students_national_code_also_blocks_a_consultant(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        app()->instance('tenant', $tenant);
        $student = Student::factory()->for($tenant)->create([
            'national_code' => IranianIdentity::randomValidNationalCode(),
        ]);

        // One code = one person, across both tables.
        $this->actingAs($user)->patch("http://{$host}/consultant/settings/profile", [
            'name' => $user->name,
            'national_code' => $student->national_code,
        ])->assertSessionHasErrors('national_code');
    }

    public function test_two_null_emails_can_coexist_in_one_tenant(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        app()->instance('tenant', $tenant);
        $user = User::factory()->consultant()->create(['tenant_id' => $tenant->id, 'email' => null]);
        User::factory()->consultant()->create(['tenant_id' => $tenant->id, 'email' => null]);

        // Saving a profile without touching the email must not trip the
        // unique rule against the neighbour's NULL.
        $this->actingAs($user)->patch("http://{$host}/consultant/settings/profile", [
            'name' => 'بدون ایمیل',
        ])->assertRedirect();

        $this->assertNull($user->fresh()->email);
    }
}
