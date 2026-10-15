<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\SiteConfig;
use App\Support\StudentAccess;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Direct student creation: the tenant admin types a student's data in himself,
 * outside the درخواست/join-request flow, for a person assigned "out of the
 * blue" — no visitor, no declared payment.
 *
 * The boundary these tests care about is the same one the join flow has: only a
 * tenant admin may write the roster, the tenant always comes from the host, and
 * nothing crosses a tenant. The one new rule the hand-entry path adds is the
 * optional `student_staff` link, without which a hand-created student is
 * invisible to the consultant he was added for.
 */
class ConsultantStudentCreateTest extends TestCase
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

    private function staffFor(Tenant $tenant, string $role = User::ROLE_TENANT_ADMIN): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
    }

    /** The hand-entry payload: the roster's own columns, nothing more. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'مهدی رضایی',
            'email' => 'mehdi@example.test',
            'grade' => 'دهم',
            'gender' => 'پسر',
            'major' => 'ریاضی',
            'bio' => 'دانش‌آموز تازه‌اضافه‌شده',
        ], $overrides);
    }

    private function createUrl(string $host): string
    {
        return "http://{$host}/consultant/requests/create";
    }

    // =========================================================================
    // The happy path
    // =========================================================================

    public function test_an_admin_can_create_a_student_from_the_consultant_area(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-ok.test');
        $admin = $this->staffFor($tenant);

        $this->actingAs($admin)
            ->post($this->createUrl($host), $this->payload())
            ->assertRedirect();

        $this->assertSame(1, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $student = Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('مهدی رضایی', $student->name);
        $this->assertSame('mehdi@example.test', $student->email);
        $this->assertSame('دهم', $student->grade);
        $this->assertSame('پسر', $student->gender);
        $this->assertSame('ریاضی', $student->major);
        $this->assertSame('دانش‌آموز تازه‌اضافه‌شده', $student->bio);
        $this->assertNull($student->removed_at);
    }

    public function test_the_created_student_can_log_in_with_the_revealed_password(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-password.test');
        $admin = $this->staffFor($tenant);

        $this->actingAs($admin)->post($this->createUrl($host), $this->payload());

        $password = session('generated_password');
        $this->assertNotNull($password, 'create must reveal the generated password once');

        $student = Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertTrue(Hash::check($password, $student->password));

        // …and the password is not recoverable afterwards: the redirect is the
        // only place it is ever shown — as the pop-up that reveals it.
        $this->actingAs($admin)
            ->get("http://{$host}/consultant/students/{$student->id}")
            ->assertOk()
            ->assertSee('id="generated-password-dialog"', false)
            ->assertSee($password, false)
            ->assertSee('settings-flash--success', false);

        $this->assertNull(session('generated_password'));

        // One look, then gone: a reload must not render the dialog again.
        $this->actingAs($admin)
            ->get("http://{$host}/consultant/students/{$student->id}")
            ->assertOk()
            ->assertDontSee('id="generated-password-dialog"', false);
    }

    public function test_the_created_student_appears_on_the_dashboard_students_list(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-listed.test');
        $admin = $this->staffFor($tenant);

        $this->actingAs($admin)->post($this->createUrl($host), $this->payload())->assertRedirect();

        // Proves the created row is inside StudentAccess, not merely in the table.
        $this->actingAs($admin)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->assertSee('مهدی رضایی');
    }

    // =========================================================================
    // Authorisation
    // =========================================================================

    public function test_a_consultant_staff_may_not_create_a_student(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-perm-staff.test');
        $staff = $this->staffFor($tenant, User::ROLE_CONSULTANT_STAFF);

        $this->actingAs($staff)
            ->post($this->createUrl($host), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_a_teacher_may_not_create_a_student(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-perm-teacher.test');
        $teacher = $this->staffFor($tenant, User::ROLE_TEACHER);

        // A teacher is refused before the controller is reached at all:
        // EnsureHierarchyAccess 404s every teacher under /consultant/*, since
        // teachers have their own portal. (A consultant_staff reaches the
        // controller and is refused there with 403 — see the test above.)
        $this->actingAs($teacher)
            ->post($this->createUrl($host), $this->payload())
            ->assertNotFound();

        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_create_form_is_offered_to_an_admin_and_not_to_staff(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-form-perm.test');
        $admin = $this->staffFor($tenant);

        $this->actingAs($admin)->get($this->createUrl($host))->assertOk();

        $staff = $this->staffFor($tenant, User::ROLE_CONSULTANT_STAFF);
        $this->actingAs($staff)->get($this->createUrl($host))->assertForbidden();
    }

    // =========================================================================
    // Tenancy
    // =========================================================================

    public function test_the_tenant_comes_from_the_host_not_the_form(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain('create-tenant-a.test');
        [$tenantB] = $this->tenantWithDomain('create-tenant-b.test');
        $adminA = $this->staffFor($tenantA);

        // A forged tenant_id in the body must be ignored: the route carries no
        // tenant field, and IdentifyTenant resolves the host.
        $this->actingAs($adminA)
            ->post($this->createUrl($hostA), $this->payload(['tenant_id' => $tenantB->id]))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($tenantA->id, $student->tenant_id);
        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }

    public function test_a_student_cannot_be_created_on_another_tenants_roster(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain('create-iso-a.test');
        [$tenantB, $hostB] = $this->tenantWithDomain('create-iso-b.test');

        $adminA = $this->staffFor($tenantA);

        // Tenant B's host resolves to B, and A's admin is not admitted to it at
        // all (EnsureUserDomain refuses the foreign actor), so no row lands on
        // either roster.
        $this->actingAs($adminA)->post($this->createUrl($hostB), $this->payload());

        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count());
        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }

    public function test_the_roster_cannot_be_written_from_another_tenants_host(): void
    {
        [$tenantA] = $this->tenantWithDomain('create-cross-a.test');
        [$tenantB, $hostB] = $this->tenantWithDomain('create-cross-b.test');

        // Tenant A's admin addresses tenant B's host (a stale bookmark, a
        // copied link). The session is global, so the "acting user" check alone
        // passes — the resolved tenant must be the thing that stops this.
        $adminA = $this->staffFor($tenantA);
        app()->instance('tenant', $tenantB);

        $this->actingAs($adminA)->post($this->createUrl($hostB), $this->payload());

        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_name_and_email_are_required(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-required.test');
        $admin = $this->staffFor($tenant);

        $this->actingAs($admin)
            ->post($this->createUrl($host), ['name' => '', 'email' => ''])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_an_email_already_on_the_active_roster_is_refused(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-existing.test');
        $admin = $this->staffFor($tenant);

        Student::factory()->for($tenant)->create(['email' => 'mehdi@example.test']);

        // Issuing a fresh password for that email would hand whoever typed the
        // address a working login to an existing student's account.
        $this->actingAs($admin)
            ->post($this->createUrl($host), $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_creating_reuses_a_previously_removed_student_row(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-reuse.test');
        $admin = $this->staffFor($tenant);

        // Someone who was a student, then removed, now added back by hand.
        $student = Student::factory()->for($tenant)->create(['email' => 'mehdi@example.test']);
        StudentEnrollment::remove($student);

        $this->actingAs($admin)
            ->post($this->createUrl($host), $this->payload())
            ->assertRedirect();

        // Reusing the row (rather than inserting) both respects the unique
        // (tenant_id, email) key and restores the person's history.
        $this->assertSame(1, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertNull($student->refresh()->removed_at);
        $this->assertSame('مهدی رضایی', $student->name);
    }

    // =========================================================================
    // Field choices — fixed lists, not free text
    // =========================================================================

    public function test_the_form_offers_the_fixed_choices_on_a_fresh_roster(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-choices.test');
        $admin = $this->staffFor($tenant);

        // No students exist yet — exactly the case a roster-fed picker breaks
        // on: the drop-downs must still be there, fed by the fixed lists.
        $response = $this->actingAs($admin)->get($this->createUrl($host))->assertOk();

        $response->assertSee('<select id="create-grade"', false)
            ->assertSee('<select id="create-gender"', false)
            ->assertSee('<select id="create-major"', false)
            ->assertSee('انتخاب کنید')
            ->assertSee('<option value="دهم"', false)
            ->assertSee('<option value="یازدهم"', false)
            ->assertSee('<option value="دوازدهم"', false)
            ->assertSee('<option value="پسر"', false)
            ->assertSee('<option value="دختر"', false)
            ->assertSee('<option value="ریاضی"', false)
            ->assertSee('<option value="تجربی"', false)
            ->assertSee('<option value="انسانی"', false);
    }

    public function test_the_choice_lists_keep_their_given_order(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-choices-order.test');
        $admin = $this->staffFor($tenant);

        $html = $this->actingAs($admin)->get($this->createUrl($host))->assertOk()->getContent();

        // دهم → یازدهم → دوازدهم: the teaching order the tenant wrote. A
        // plain column collation would print «دوازدهم» before «یازدهم».
        $tenth = strpos($html, '<option value="دهم"');
        $eleventh = strpos($html, '<option value="یازدهم"');
        $twelfth = strpos($html, '<option value="دوازدهم"');

        $this->assertNotFalse($tenth);
        $this->assertNotFalse($eleventh);
        $this->assertNotFalse($twelfth);
        $this->assertTrue(
            $tenth < $eleventh && $eleventh < $twelfth,
            'the grade picker must read دهم → یازدهم → دوازدهم'
        );

        // Same for the other two lists, in the order the tenant gave them.
        $boy = strpos($html, '<option value="پسر"');
        $girl = strpos($html, '<option value="دختر"');

        $this->assertNotFalse($boy);
        $this->assertNotFalse($girl);
        $this->assertTrue($boy < $girl, 'the gender picker must read پسر → دختر');

        $math = strpos($html, '<option value="ریاضی"');
        $experimental = strpos($html, '<option value="تجربی"');
        $humanities = strpos($html, '<option value="انسانی"');

        $this->assertNotFalse($math);
        $this->assertNotFalse($experimental);
        $this->assertNotFalse($humanities);
        $this->assertTrue(
            $math < $experimental && $experimental < $humanities,
            'the major picker must read ریاضی → تجربی → انسانی'
        );
    }

    public function test_the_pickers_are_static_not_fed_by_the_rosters_values(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-choices-static.test');
        $admin = $this->staffFor($tenant);

        // The lists are fixed, so roster values outside them — a legacy
        // spelling, a removed student's data — must not leak into the
        // drop-downs.
        Student::factory()->for($tenant)->create([
            'email' => 'legacy@example.test',
            'grade' => 'هفتم',
            'major' => 'ریاضی و فیزیک',
        ]);
        $removed = Student::factory()->for($tenant)->create([
            'email' => 'gone@example.test',
            'grade' => 'یازدهم',
            'major' => 'علوم تجربی',
        ]);
        StudentEnrollment::remove($removed);

        $this->actingAs($admin)
            ->get($this->createUrl($host))
            ->assertOk()
            ->assertSee('<option value="انسانی"', false)
            ->assertDontSee('<option value="هفتم"', false)
            ->assertDontSee('<option value="ریاضی و فیزیک"', false)
            ->assertDontSee('<option value="علوم تجربی"', false);
    }

    public function test_an_old_value_outside_the_lists_stays_selectable(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-choices-old.test');
        $admin = $this->staffFor($tenant);

        // A value outside today's list comes back on a validation round-trip
        // (the list changed while the form was open): the admin's pick must
        // survive rather than silently resetting.
        $this->actingAs($admin)
            ->post($this->createUrl($host), $this->payload(['name' => '', 'grade' => 'هفتم']))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->get($this->createUrl($host))
            ->assertOk()
            ->assertSee('<option value="هفتم" selected>هفتم</option>', false);
    }

    // =========================================================================
    // Optional consultant assignment
    // =========================================================================

    public function test_an_assigned_student_is_visible_to_his_consultant(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-assign.test');
        $tenant->forceFill(['hierarchy_type' => 'consultancy'])->save();

        $admin = $this->staffFor($tenant);
        $consultant = $this->staffFor($tenant, User::ROLE_CONSULTANT_STAFF);
        $other = $this->staffFor($tenant, User::ROLE_CONSULTANT_STAFF);

        $this->actingAs($admin)
            ->post($this->createUrl($host), $this->payload(['consultant_id' => $consultant->id]))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertDatabaseHas('student_staff', [
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'user_id' => $consultant->id,
        ]);

        // The linked consultant sees him; an unlinked colleague does not.
        $this->assertTrue(StudentAccess::allows($consultant, $student));
        $this->assertFalse(StudentAccess::allows($other, $student));
    }

    public function test_the_consultant_select_is_offered_only_to_a_hierarchical_consultancy(): void
    {
        [$plain, $plainHost] = $this->tenantWithDomain('create-plain.test');
        $plainAdmin = $this->staffFor($plain);

        // A non-hierarchical tenant has no consultant layer to assign to.
        $this->actingAs($plainAdmin)
            ->get($this->createUrl($plainHost))
            ->assertOk()
            ->assertDontSee('name="consultant_id"', false);

        [$school, $schoolHost] = $this->tenantWithDomain('create-school.test');
        $school->forceFill(['hierarchy_type' => 'school'])->save();
        $schoolAdmin = $this->staffFor($school);

        // A school groups by classroom — deliberately out of this plan's scope.
        $this->actingAs($schoolAdmin)
            ->get($this->createUrl($schoolHost))
            ->assertOk()
            ->assertDontSee('name="consultant_id"', false);
    }

    public function test_a_consultant_from_another_tenant_cannot_be_assigned(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-foreign-consultant.test');
        $tenant->forceFill(['hierarchy_type' => 'consultancy'])->save();

        [$foreignTenant] = $this->tenantWithDomain('create-foreign-other.test');
        $foreign = User::factory()->create([
            'tenant_id' => $foreignTenant->id,
            'role' => User::ROLE_CONSULTANT_STAFF,
        ]);

        $admin = $this->staffFor($tenant);

        $this->actingAs($admin)
            ->post($this->createUrl($host), $this->payload(['consultant_id' => $foreign->id]))
            ->assertSessionHasErrors('consultant_id');

        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    // =========================================================================
    // Feature flag
    // =========================================================================

    public function test_the_feature_flag_gates_the_create_routes(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('create-flag.test');
        $admin = $this->staffFor($tenant);

        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'layout_config' => ['features' => ['student_join_requests' => false]],
        ]);

        app(SiteConfig::class)->flush();

        $this->actingAs($admin)->get($this->createUrl($host))->assertNotFound();
        $this->actingAs($admin)
            ->post($this->createUrl($host), $this->payload())
            ->assertNotFound();

        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
