<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentAuthTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantWithStudent(string $host): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget("domain:{$host}");

        $student = Student::factory()->for($tenant)->create([
            'password' => Hash::make('password'),
        ]);

        return [$tenant, $student, $host];
    }

    public function test_student_can_view_login_page(): void
    {
        [, , $host] = $this->makeTenantWithStudent('tenant-student.test');

        $this->get("http://tenant-student.test/student/login")
            ->assertOk()
            ->assertSee('ورود دانش‌آموز');
    }

    public function test_student_can_login_with_correct_credentials(): void
    {
        [, $student, $host] = $this->makeTenantWithStudent('tenant-student.test');

        $this->post("http://tenant-student.test/student/login", [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student, 'student');
    }

    public function test_student_cannot_login_with_wrong_password(): void
    {
        [, $student, $host] = $this->makeTenantWithStudent('tenant-student.test');

        $this->post("http://tenant-student.test/student/login", [
            'email' => $student->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('student');
    }

    public function test_student_cannot_login_with_another_tenants_email(): void
    {
        [, $studentA, $hostA] = $this->makeTenantWithStudent('tenant-a.test');
        [, $studentB, $hostB] = $this->makeTenantWithStudent('tenant-b.test');

        // Try to login to Tenant A's domain using Tenant B's student email
        $this->post("http://tenant-a.test/student/login", [
            'email' => $studentB->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('student');
    }

    public function test_authenticated_student_can_view_dashboard(): void
    {
        [, $student, $host] = $this->makeTenantWithStudent('tenant-student.test');

        $this->actingAs($student, 'student')
            ->get("http://tenant-student.test/student/dashboard")
            ->assertOk()
            ->assertSee($student->name);
    }

    public function test_guest_student_is_redirected_to_login(): void
    {
        [, , $host] = $this->makeTenantWithStudent('tenant-student.test');

        $this->get("http://tenant-student.test/student/dashboard")
            ->assertRedirect(route('student.login'));
    }
}
