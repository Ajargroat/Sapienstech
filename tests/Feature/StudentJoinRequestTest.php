<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\StudentJoinRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\SiteConfig;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The join-request flow: a visitor imports their data on the tenant's public
 * site and declares a payment; a tenant admin/owner approves it and the data
 * becomes a student.
 *
 * The point of the feature is that the consultant never types a record into the
 * database, so these tests care most about the boundary: nothing reaches the
 * roster before approval, and nothing crosses a tenant.
 */
class StudentJoinRequestTest extends TestCase
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

    /** A valid public submission. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'زهرا محمدی',
            'email' => 'zahra@example.test',
            'phone' => '09120000000',
            'grade' => 'دهم',
            'gender' => 'دختر',
            'major' => 'تجربی',
            'bio' => 'دانش‌آموز نمونه',
            'reference' => 'REF-12345',
        ], $overrides);
    }

    // =========================================================================
    // Public submission
    // =========================================================================

    public function test_a_visitor_can_submit_a_join_request_from_the_public_site(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-public.test');

        $this->post("http://{$host}/join", $this->payload())
            ->assertRedirect()
            ->assertSessionHas('join_status');

        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($tenant->id, $request->tenant_id);
        $this->assertSame('زهرا محمدی', $request->name);
        $this->assertSame(StudentJoinRequest::STATUS_PENDING, $request->status);
        $this->assertSame('REF-12345', $request->reference);
        $this->assertNull($request->student_id);
    }

    public function test_a_pending_request_does_not_create_a_student(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-nostudent.test');

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();

        // The whole point: an unapproved visitor must never appear in the
        // roster, in counts, or in any picker.
        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_tenant_comes_from_the_host_not_the_form(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain('join-tenant-a.test');
        [$tenantB] = $this->tenantWithDomain('join-tenant-b.test');

        // A forged tenant_id in the body must be ignored: the route carries no
        // tenant field, and IdentifyTenant resolves the host.
        $this->post("http://{$hostA}/join", $this->payload(['tenant_id' => $tenantB->id]))
            ->assertRedirect();

        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($tenantA->id, $request->tenant_id);
        $this->assertSame(0, StudentJoinRequest::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }

    public function test_a_second_submission_updates_the_open_request_instead_of_queueing_a_duplicate(): void
    {
        [, $host] = $this->tenantWithDomain('join-resubmit.test');

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        // The visitor mistyped their reference and corrects it.
        $this->post("http://{$host}/join", $this->payload(['reference' => 'REF-99999']))->assertRedirect();

        $this->assertSame(1, StudentJoinRequest::withoutGlobalScopes()->count());
        $this->assertSame('REF-99999', StudentJoinRequest::withoutGlobalScopes()->firstOrFail()->reference);
    }

    public function test_name_and_email_are_required(): void
    {
        [, $host] = $this->tenantWithDomain('join-required.test');

        $this->post("http://{$host}/join", ['name' => '', 'email' => ''])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertSame(0, StudentJoinRequest::withoutGlobalScopes()->count());
    }

    public function test_an_email_already_on_the_active_roster_is_refused(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-existing.test');

        app()->instance('tenant', $tenant);
        Student::factory()->for($tenant)->create(['email' => 'zahra@example.test']);

        // Accepting this would let whoever typed the address take over an
        // existing student's account, because approval issues a new password.
        $this->post("http://{$host}/join", $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertSame(0, StudentJoinRequest::withoutGlobalScopes()->count());
    }

    public function test_the_public_form_is_hidden_when_the_feature_is_off(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-off.test');

        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'layout_config' => ['features' => ['student_join_requests' => false]],
        ]);

        app(SiteConfig::class)->flush();

        $this->post("http://{$host}/join", $this->payload())->assertNotFound();
        $this->assertSame(0, StudentJoinRequest::withoutGlobalScopes()->count());
    }

    // =========================================================================
    // Approval
    // =========================================================================

    public function test_approving_a_request_creates_the_student_from_the_imported_data(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-approve.test');
        $admin = $this->staffFor($tenant);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/requests/{$request->id}/approve")
            ->assertRedirect(route('consultant.requests.show', $request));

        $student = Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('زهرا محمدی', $student->name);
        $this->assertSame('zahra@example.test', $student->email);
        $this->assertSame('دهم', $student->grade);
        $this->assertSame('تجربی', $student->major);

        $request->refresh();
        $this->assertSame(StudentJoinRequest::STATUS_APPROVED, $request->status);
        $this->assertSame($student->id, $request->student_id);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
    }

    public function test_approval_reveals_a_password_that_actually_works(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-password.test');
        $admin = $this->staffFor($tenant);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $response = $this->actingAs($admin)
            ->post("http://{$host}/consultant/requests/{$request->id}/approve");

        $password = session('generated_password');
        $this->assertNotNull($password, 'approval must reveal the generated password once');

        $student = Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertTrue(Hash::check($password, $student->password));

        // …and the password is not recoverable afterwards: the redirect is the
        // only place it is ever shown — as the pop-up that reveals it.
        $this->actingAs($admin)
            ->get("http://{$host}/consultant/requests/{$request->id}")
            ->assertOk()
            ->assertSee('id="generated-password-dialog"', false)
            ->assertSee($password, false);

        $this->assertNull(session('generated_password'));

        // One look, then gone: a reload must not render the dialog again.
        $this->actingAs($admin)
            ->get("http://{$host}/consultant/requests/{$request->id}")
            ->assertOk()
            ->assertDontSee('id="generated-password-dialog"', false);
    }

    public function test_a_request_cannot_be_approved_twice(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-double.test');
        $admin = $this->staffFor($tenant);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)->post("http://{$host}/consultant/requests/{$request->id}/approve");
        $this->actingAs($admin)
            ->post("http://{$host}/consultant/requests/{$request->id}/approve")
            ->assertSessionHasErrors('review');

        // Exactly one student, however many times Approve was clicked.
        $this->assertSame(1, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_rejecting_a_request_creates_no_student_and_releases_the_email(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-reject.test');
        $admin = $this->staffFor($tenant);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/requests/{$request->id}/reject", ['review_note' => 'پرداخت یافت نشد'])
            ->assertRedirect(route('consultant.requests.index'));

        $request->refresh();
        $this->assertSame(StudentJoinRequest::STATUS_REJECTED, $request->status);
        $this->assertSame('پرداخت یافت نشد', $request->review_note);
        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // A rejected applicant may apply again once they have actually paid.
        $this->post("http://{$host}/join", $this->payload(['reference' => 'REF-2']))->assertRedirect();
        $this->assertSame(2, StudentJoinRequest::withoutGlobalScopes()->count());
    }

    public function test_only_a_tenant_admin_may_decide(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-perm.test');
        $staff = $this->staffFor($tenant, User::ROLE_CONSULTANT_STAFF);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($staff)
            ->post("http://{$host}/consultant/requests/{$request->id}/approve")
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("http://{$host}/consultant/requests/{$request->id}/reject")
            ->assertForbidden();

        $this->assertSame(StudentJoinRequest::STATUS_PENDING, $request->refresh()->status);
        $this->assertSame(0, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_a_consultant_staff_may_read_the_queue_but_not_decide(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-read.test');
        $staff = $this->staffFor($tenant, User::ROLE_CONSULTANT_STAFF);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($staff)
            ->get("http://{$host}/consultant/requests")
            ->assertOk()
            ->assertSee('زهرا محمدی');

        // The decision controls are not offered to staff.
        $this->actingAs($staff)
            ->get("http://{$host}/consultant/requests/{$request->id}")
            ->assertOk()
            ->assertDontSee('تأیید و افزودن به فهرست');
    }

    // =========================================================================
    // Tenant isolation
    // =========================================================================

    public function test_a_request_cannot_be_decided_across_tenants(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain('join-iso-a.test');
        [$tenantB, $hostB] = $this->tenantWithDomain('join-iso-b.test');

        // A request filed on tenant A's site.
        $this->post("http://{$hostA}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        // Tenant B's admin must not be able to approve it, and the implicit
        // binding must not even resolve it under tenant B's host.
        $adminB = $this->staffFor($tenantB);

        $this->actingAs($adminB)
            ->post("http://{$hostB}/consultant/requests/{$request->id}/approve")
            ->assertNotFound();

        $this->assertSame(StudentJoinRequest::STATUS_PENDING, $request->refresh()->status);
    }

    public function test_the_queue_only_shows_the_current_tenants_requests(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain('join-queue-a.test');
        [$tenantB, $hostB] = $this->tenantWithDomain('join-queue-b.test');

        // Distinctive names, so the assertion cannot be satisfied by an
        // unrelated occurrence of a common letter elsewhere on the page.
        $this->post("http://{$hostA}/join", $this->payload([
            'name' => 'الف', 'email' => 'alpha-tenant@example.test',
        ]))->assertRedirect();
        $this->post("http://{$hostB}/join", $this->payload([
            'name' => 'ب', 'email' => 'beta-tenant@example.test',
        ]))->assertRedirect();

        $adminA = $this->staffFor($tenantA);

        $this->actingAs($adminA)
            ->get("http://{$hostA}/consultant/requests")
            ->assertOk()
            ->assertSee('alpha-tenant@example.test')
            ->assertDontSee('beta-tenant@example.test');
    }

    public function test_the_same_email_may_hold_an_open_request_on_two_tenants(): void
    {
        [, $hostA] = $this->tenantWithDomain('join-shared-a.test');
        [, $hostB] = $this->tenantWithDomain('join-shared-b.test');

        // The duplicate guard is per tenant, so one person may be applying to
        // two different academies at once.
        $this->post("http://{$hostA}/join", $this->payload())->assertRedirect();
        $this->post("http://{$hostB}/join", $this->payload())->assertRedirect();

        $this->assertSame(2, StudentJoinRequest::withoutGlobalScopes()->count());
    }

    // =========================================================================
    // Landing section
    // =========================================================================

    public function test_the_join_section_renders_the_form_when_the_tenant_opts_in(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-section.test');

        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'layout_config' => ['public' => ['landing' => ['sections' => ['hero', 'join']]]],
        ]);

        app(SiteConfig::class)->flush();

        $this->get("http://{$host}/")
            ->assertOk()
            ->assertSee('درخواست عضویت')
            ->assertSee(route('join.store'), false);
    }

    public function test_the_join_section_is_absent_for_a_tenant_that_does_not_opt_in(): void
    {
        [, $host] = $this->tenantWithDomain('join-nosection.test');

        // Default `sections` has no `join`, so the landing must not carry the
        // form — a verified no-op for every existing tenant.
        $this->get("http://{$host}/")
            ->assertOk()
            ->assertDontSee(route('join.store'), false);
    }

    // =========================================================================
    // Service-level guards
    // =========================================================================

    public function test_approval_reuses_an_existing_removed_student_row(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('join-reuse.test');
        $admin = $this->staffFor($tenant);

        // Someone who was a student, then removed, then applies again.
        $student = Student::factory()->for($tenant)->create(['email' => 'zahra@example.test']);
        StudentEnrollment::remove($student);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();
        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)->post("http://{$host}/consultant/requests/{$request->id}/approve");

        // Reusing the row (rather than inserting) both respects the unique
        // (tenant_id, email) key and restores the person's history.
        $this->assertSame(1, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertNull($student->refresh()->removed_at);
        $this->assertSame('زهرا محمدی', $student->name);
    }
}
