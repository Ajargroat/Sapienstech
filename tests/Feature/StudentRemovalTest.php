<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\StudentDeal;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ChatActor;
use App\Support\ChatService;
use App\Support\StudentAccess;
use App\Support\StudentEnrollment;
use App\Support\TenantRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Removing a student from the roster.
 *
 * The user's rule: removal is a soft marker. The row and its history survive —
 * deals, attempts, chat — the person disappears from every staff-facing list,
 * and they can no longer log in. There is no restore screen; removal is
 * presented to the consultant as final.
 *
 * The riskiest part of the feature is that the `roster` scope must hide the
 * person WITHOUT breaking the surfaces that legitimately still render their
 * history, so those cases are pinned here too.
 */
class StudentRemovalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(string $host): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    private function adminFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_ADMIN]);
    }

    private function studentFor(Tenant $tenant, array $attributes = []): Student
    {
        app()->instance('tenant', $tenant);

        return Student::factory()->for($tenant)->create($attributes);
    }

    // =========================================================================
    // The action itself
    // =========================================================================

    public function test_a_tenant_admin_can_remove_a_student_from_their_profile(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-basic.test');
        $admin = $this->adminFor($tenant);
        $student = $this->studentFor($tenant);

        $this->actingAs($admin)
            ->delete("http://{$host}/consultant/students/{$student->id}", ['confirm' => 'remove'])
            ->assertRedirect(route('consultant.dashboard'));

        $this->assertNotNull($student->refresh()->removed_at);
    }

    public function test_removal_does_not_delete_the_row_or_its_history(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-keeps.test');
        $admin = $this->adminFor($tenant);
        $student = $this->studentFor($tenant);

        // The student has a deal — the kind of record the user does not want
        // destroyed by a roster cleanup.
        $deal = StudentDeal::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'starts_on' => now()->subDays(30),
            'ends_on' => now()->addDays(30),
            'amount' => 500000,
            'period_days' => 60,
            'decision' => StudentDeal::DECISION_PENDING,
        ]);

        $this->actingAs($admin)
            ->delete("http://{$host}/consultant/students/{$student->id}", ['confirm' => 'remove']);

        // The row survives, and so does everything hanging off it.
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('student_deals', ['id' => $deal->id]);
        $this->assertSame(1, StudentDeal::withoutGlobalScopes()->where('student_id', $student->id)->count());
    }

    public function test_removal_requires_the_confirmation_field(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-confirm.test');
        $admin = $this->adminFor($tenant);
        $student = $this->studentFor($tenant);

        // A destructive action must not be one stray POST away, even though the
        // UI already asks for a confirmation.
        $this->actingAs($admin)
            ->delete("http://{$host}/consultant/students/{$student->id}")
            ->assertSessionHasErrors('confirm');

        $this->assertNull($student->refresh()->removed_at);
    }

    public function test_only_a_tenant_admin_may_remove_a_student(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-perm.test');
        app()->instance('tenant', $tenant);
        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_CONSULTANT_STAFF]);
        $student = $this->studentFor($tenant);

        $this->actingAs($staff)
            ->delete("http://{$host}/consultant/students/{$student->id}", ['confirm' => 'remove'])
            ->assertForbidden();

        $this->assertNull($student->refresh()->removed_at);
    }

    public function test_a_student_cannot_be_removed_across_tenants(): void
    {
        [$tenantA] = $this->tenantWithDomain('remove-iso-a.test');
        [$tenantB, $hostB] = $this->tenantWithDomain('remove-iso-b.test');

        $studentA = $this->studentFor($tenantA);
        $adminB = $this->adminFor($tenantB);

        // Tenant B's admin must not reach tenant A's student at all: the
        // implicit binding resolves through the tenant scope and 404s.
        $this->actingAs($adminB)
            ->delete("http://{$hostB}/consultant/students/{$studentA->id}", ['confirm' => 'remove'])
            ->assertNotFound();

        $this->assertNull($studentA->refresh()->removed_at);
    }

    // =========================================================================
    // The person disappears from staff-facing surfaces
    // =========================================================================

    public function test_a_removed_student_leaves_every_staff_facing_list(): void
    {
        [$tenant] = $this->tenantWithDomain('remove-lists.test');
        app()->instance('tenant', $tenant);
        $student = $this->studentFor($tenant);

        $this->assertSame(1, Student::query()->count());

        StudentEnrollment::remove($student);

        $this->assertSame(0, Student::query()->count());
        $this->assertFalse(Student::query()->whereKey($student->id)->exists());
    }

    public function test_a_removed_student_is_out_of_roster_metrics(): void
    {
        [$tenant] = $this->tenantWithDomain('remove-metrics.test');
        app()->instance('tenant', $tenant);
        $student = $this->studentFor($tenant);

        $this->assertSame(1, TenantRoster::metrics($tenant)['students'] ?? 0);

        StudentEnrollment::remove($student);

        $this->assertArrayNotHasKey('students', TenantRoster::metrics($tenant));
    }

    public function test_a_removed_student_can_no_longer_log_in(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-login.test');
        $student = $this->studentFor($tenant, ['password' => Hash::make('password')]);

        // They could log in before removal.
        $this->post("http://{$host}/student/login", [
            'identifier' => $student->email, 'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->post("http://{$host}/student/logout");

        StudentEnrollment::remove($student);

        // …and cannot afterwards. The failure is the generic one, so the login
        // page does not confirm the removal to whoever holds the credentials.
        $this->post("http://{$host}/student/login", [
            'identifier' => $student->email, 'password' => 'password',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest('student');
    }

    public function test_a_removed_students_existing_session_ends(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-session.test');
        $student = $this->studentFor($tenant, ['password' => Hash::make('password')]);

        // A real login, so the session holds the student's ID and every later
        // request resolves it through the database — the path `actingAs()`
        // would bypass by injecting the model straight into the guard.
        $this->post("http://{$host}/student/login", [
            'identifier' => $student->email, 'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->get("http://{$host}/student/dashboard")->assertOk();

        // Removed mid-session: the guard resolves through the roster scope, so
        // the next request can no longer authenticate them.
        StudentEnrollment::remove($student);

        // A test shares one application instance across requests, so the
        // session guard would answer from its in-memory cache; a real request
        // builds a fresh guard and resolves the session's user id against the
        // database. Forgetting the guards reproduces that, keeping the session
        // itself intact.
        $this->app['auth']->forgetGuards();

        $this->get("http://{$host}/student/dashboard")->assertRedirect();
        $this->assertGuest('student');
    }

    // =========================================================================
    // …but their history still renders (the scope must not over-reach)
    // =========================================================================

    public function test_the_deals_page_still_renders_a_removed_students_history(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-history.test');
        $admin = $this->adminFor($tenant);
        $student = $this->studentFor($tenant, ['name' => 'دانش‌آموز حذف‌شده']);

        StudentDeal::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'starts_on' => now()->subDays(10),
            'ends_on' => now()->subDay(),
            'amount' => 500000,
            'period_days' => 30,
            'decision' => StudentDeal::DECISION_PENDING,
        ]);

        StudentEnrollment::remove($student);

        // The deal list renders the student name unguarded, so a scope that
        // nulled the relation would 500 this page.
        $this->actingAs($admin)
            ->get("http://{$host}/consultant/deals")
            ->assertOk()
            ->assertSee('دانش‌آموز حذف‌شده');
    }

    public function test_a_removed_students_direct_thread_still_renders(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('remove-chat.test');
        $admin = $this->adminFor($tenant);
        $student = $this->studentFor($tenant);

        app()->instance('tenant', $tenant);
        $conversation = ChatService::directThread($admin, $student);

        StudentEnrollment::remove($student);

        // The thread survives: the user's rule is that removal hides the person,
        // not their history.
        $this->assertDatabaseHas('chat_conversations', ['id' => $conversation->id]);

        $this->actingAs($admin)
            ->get("http://{$host}/consultant/direct-chat/conversations/{$conversation->id}")
            ->assertOk();
    }

    public function test_removing_one_student_does_not_hide_a_group_thread_from_its_other_members(): void
    {
        [$tenant] = $this->tenantWithDomain('remove-group.test');
        app()->instance('tenant', $tenant);

        $admin = $this->adminFor($tenant);
        $a = $this->studentFor($tenant);
        $b = $this->studentFor($tenant);

        $group = ChatService::createGroup($admin, 'گروه آزمون', [$a->id, $b->id]);

        StudentEnrollment::remove($a);

        // The group check asks "is every participant still a *member*", not "is
        // every participant still on the roster" — otherwise one removal would
        // hide the whole thread from the students who are still there. The
        // conversation list runs that check, so it is the honest assertion.
        $this->assertTrue(
            ChatService::conversationList(ChatActor::make($admin))
                ->contains(fn ($row) => $row['conversation']->id === $group->id)
        );
    }

    public function test_the_student_remains_visible_to_an_explicit_history_lookup(): void
    {
        [$tenant] = $this->tenantWithDomain('remove-explicit.test');
        app()->instance('tenant', $tenant);
        $student = $this->studentFor($tenant);

        StudentEnrollment::remove($student);

        // Roster-scoped queries hide them…
        $this->assertNull(Student::query()->find($student->id));

        // …while a deliberate history lookup can still reach the row.
        $this->assertNotNull(
            Student::withoutGlobalScope('roster')->find($student->id)
        );
        $this->assertTrue($student->refresh()->isRemoved());
    }

    // =========================================================================
    // Roster scope independence
    // =========================================================================

    public function test_the_roster_scope_does_not_replace_the_roster_access_rules(): void
    {
        [$tenant] = $this->tenantWithDomain('remove-access.test');
        app()->instance('tenant', $tenant);

        // A hierarchical tenant: a consultant only sees their own students.
        $tenant->forceFill(['hierarchy_type' => 'consultancy'])->save();

        $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_CONSULTANT_STAFF]);
        $mine = $this->studentFor($tenant);
        $theirs = $this->studentFor($tenant);

        $mine->staff()->attach($staff->id, ['tenant_id' => $tenant->id]);

        // StudentAccess still decides *which* students a staff member may see…
        $this->assertTrue(StudentAccess::allows($staff, $mine));
        $this->assertFalse(StudentAccess::allows($staff, $theirs));

        // …and removal is an independent concern on top of it.
        StudentEnrollment::remove($mine);
        $this->assertFalse(StudentAccess::allows($staff, $mine));
    }
}
