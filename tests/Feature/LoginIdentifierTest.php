<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Signing in with the «شناسهٔ ورود»: email, mobile or کد ملی — on both guards,
 * through the tenant's own host only, with one generic failure.
 */
class LoginIdentifierTest extends TestCase
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

    private function consultant(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->withIdentity()->create([
            'tenant_id' => $tenant->id,
        ]);
    }

    private function student(Tenant $tenant): Student
    {
        app()->instance('tenant', $tenant);

        return Student::factory()->for($tenant)->withIdentity()->create();
    }

    public function test_consultant_signs_in_with_their_national_code(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultant($tenant);

        $this->post("http://{$host}/login", [
            'identifier' => $user->national_code,
            'password' => 'password',
        ])->assertRedirect(route('consultant.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_consultant_signs_in_with_plus_98_and_persian_digits(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultant($tenant);

        // The +98 shape…
        $this->post("http://{$host}/login", [
            'identifier' => '+98'.substr($user->phone, 1),
            'password' => 'password',
        ])->assertRedirect(route('consultant.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post("http://{$host}/logout");

        // …and the same number typed with Persian digits.
        $persian = strtr($user->phone, array_combine(range('0', '9'), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']));
        $this->post("http://{$host}/login", [
            'identifier' => $persian,
            'password' => 'password',
        ])->assertRedirect(route('consultant.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_student_signs_in_with_phone_and_with_national_code(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->student($tenant);

        $this->post("http://{$host}/student/login", [
            'identifier' => $student->phone,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');

        $this->post("http://{$host}/student/logout");

        $this->post("http://{$host}/student/login", [
            'identifier' => $student->national_code,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_wrong_password_reports_the_identifier_field(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultant($tenant);

        $this->post("http://{$host}/login", [
            'identifier' => $user->national_code,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_identifiers_are_resolved_within_the_tenants_own_host_only(): void
    {
        [$tenantA] = $this->tenantWithDomain();
        [, $hostB] = $this->tenantWithDomain();
        $userA = $this->consultant($tenantA);

        // Correct code, correct password, foreign host: still a guest.
        $this->post("http://{$hostB}/login", [
            'identifier' => $userA->national_code,
            'password' => 'password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_a_removed_students_identifiers_get_the_generic_failure(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->student($tenant);

        app()->instance('tenant', $tenant);
        StudentEnrollment::remove($student);

        // The failure is the generic one, so the login page does not confirm
        // the removal to whoever holds the credentials.
        $this->post("http://{$host}/student/login", [
            'identifier' => $student->phone,
            'password' => 'password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest('student');
    }
}
