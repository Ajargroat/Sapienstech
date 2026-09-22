<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Access control and tenant wiring of the teacher panel: who gets in, who
 * is bounced, where a teacher lands after login, and the new tenant
 * columns (owner_user_id / teachers relation).
 */
class TeacherPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $host = 'teacher-panel.test'): Tenant
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget("domain:{$host}");

        return $tenant;
    }

    private function teacherFor(Tenant $tenant, array $overrides = []): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->teacher()->create([
            'tenant_id' => $tenant->id,
            ...$overrides,
        ]);
    }

    public function test_tenant_owner_column_and_teachers_relation_work(): void
    {
        $tenant = $this->makeTenant();

        $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
        $teacher = $this->teacherFor($tenant);
        User::factory()->consultant()->create(['tenant_id' => $tenant->id]);

        $tenant->update(['owner_user_id' => $admin->id]);
        $tenant->refresh();

        $this->assertTrue($tenant->owner->is($admin));

        // teachers() must list ONLY the teacher role, tenant-scoped.
        $this->assertSame(1, $tenant->teachers()->count());
        $this->assertTrue($tenant->teachers->first()->is($teacher));
        $this->assertTrue($teacher->isTeacher());
    }

    public function test_teacher_login_redirects_to_the_teacher_dashboard(): void
    {
        $tenant = $this->makeTenant();
        $teacher = $this->teacherFor($tenant, ['password' => \Illuminate\Support\Facades\Hash::make('password')]);

        $this->post('http://teacher-panel.test/login', [
            'email' => $teacher->email,
            'password' => 'password',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_teacher_can_view_the_dashboard(): void
    {
        $tenant = $this->makeTenant();
        $teacher = $this->teacherFor($tenant);

        $this->actingAs($teacher)
            ->get('http://teacher-panel.test/teacher/dashboard')
            ->assertOk()
            ->assertSee('پنل معلم');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->makeTenant();

        $this->get('http://teacher-panel.test/teacher/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_consultant_cannot_enter_the_teacher_area(): void
    {
        $tenant = $this->makeTenant();
        $consultant = User::factory()->consultant()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($consultant)
            ->get('http://teacher-panel.test/teacher/dashboard')
            ->assertNotFound();
    }

    public function test_tenant_admin_cannot_enter_the_teacher_area(): void
    {
        $tenant = $this->makeTenant();
        $admin = User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)
            ->get('http://teacher-panel.test/teacher/dashboard')
            ->assertNotFound();
    }

    public function test_disabling_the_teacher_panel_feature_hides_the_area(): void
    {
        $tenant = $this->makeTenant();
        $teacher = $this->teacherFor($tenant);

        site_override(['features' => ['teacher_panel' => false]]);

        $this->actingAs($teacher)
            ->get('http://teacher-panel.test/teacher/dashboard')
            ->assertNotFound();
    }

    public function test_disabling_a_section_feature_hides_that_section(): void
    {
        $tenant = $this->makeTenant();
        $teacher = $this->teacherFor($tenant);

        site_override(['features' => ['teacher_materials' => false]]);

        $this->actingAs($teacher)
            ->get('http://teacher-panel.test/teacher/materials')
            ->assertNotFound();

        // The rest of the panel stays reachable.
        $this->actingAs($teacher)
            ->get('http://teacher-panel.test/teacher/dashboard')
            ->assertOk();
    }

    public function test_teacher_of_another_tenant_gets_404_on_a_foreign_domain(): void
    {
        $tenantA = $this->makeTenant('teacher-a.test');
        $teacherA = $this->teacherFor($tenantA);

        $this->makeTenant('teacher-b.test');

        // Teacher A's session used on tenant B's domain: EnsureUserDomain
        // ends the session (wrong domain), so the panel is never reachable.
        $this->actingAs($teacherA)
            ->get('http://teacher-b.test/teacher/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_students_of_the_tenant_are_scoped_to_it(): void
    {
        $tenantA = $this->makeTenant('teacher-a.test');
        $tenantB = $this->makeTenant('teacher-b.test');

        app()->instance('tenant', $tenantA);
        Student::factory()->for($tenantA)->create(['name' => 'دانش‌آموز مال همین آکادمی']);
        app()->instance('tenant', $tenantB);
        Student::factory()->for($tenantB)->create(['name' => 'دانش‌آموز آکادمی دیگر']);

        $teacher = $this->teacherFor($tenantA);

        app()->instance('tenant', $tenantA);

        $this->actingAs($teacher)
            ->get('http://teacher-a.test/teacher/students')
            ->assertOk()
            ->assertSee('دانش‌آموز مال همین آکادمی')
            ->assertDontSee('دانش‌آموز آکادمی دیگر');
    }
}
