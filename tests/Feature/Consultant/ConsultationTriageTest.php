<?php

namespace Tests\Feature\Consultant;

use App\Models\ConsultationRequest;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\SiteConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The consultant-side triage slice for consultation requests: the review
 * queue behind the public form.
 *
 * The boundaries under test: the queue is dark until the tenant's
 * `consultation_requests` flag exists, the tabs and the nav badge carry the
 * pending rows and counts, approve/reject are idempotent HTTP status
 * transitions with nothing created or deleted behind them, and rows never
 * cross a tenant — not in the list, not in the detail view, not in the
 * decision endpoints.
 */
class ConsultationTriageTest extends TestCase
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

    /** The same flag idiom ConsultationRequestTest pins: a layout_config row merged over the baseline. */
    private function enableConsultation(Tenant $tenant): void
    {
        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'layout_config' => ['features' => ['consultation_requests' => true]],
        ]);
        app(SiteConfig::class)->flush();
    }

    /** A triage row seeded straight into the tenant's queue. */
    private function seedRequest(Tenant $tenant, array $overrides = []): ConsultationRequest
    {
        app()->instance('tenant', $tenant);

        return ConsultationRequest::create(array_merge([
            'name' => 'سعید کریمی',
            'email' => 'saeed@example.test',
            'phone' => '09112223344',
            'message' => 'می‌خواهم در مورد برنامه مطالعه مشورت کنم.',
            'status' => ConsultationRequest::STATUS_PENDING,
        ], $overrides));
    }

    // =========================================================================
    // The flag is the switch
    // =========================================================================

    public function test_triage_routes_are_dark_while_the_feature_flag_does_not_exist(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('triage-dark.test');
        $admin = $this->staffFor($tenant);

        // No config row at all: site() answers false, so both reading the
        // queue and reaching a decision endpoint 404 in front of the
        // controller.
        $this->actingAs($admin)->get("http://{$host}/consultant/consultations")->assertNotFound();
        $this->actingAs($admin)->post("http://{$host}/consultant/consultations/1/approve")->assertNotFound();
    }

    // =========================================================================
    // The enabled queue
    // =========================================================================

    public function test_the_flag_unlocks_the_queue_with_tabs_pending_rows_and_the_nav_badge(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('triage-render.test');
        $admin = $this->staffFor($tenant);
        $this->enableConsultation($tenant);

        $pendingA = $this->seedRequest($tenant, ['name' => 'سعیدکریمیکوچک', 'email' => 'saeed@example.test']);
        $pendingB = $this->seedRequest($tenant, ['name' => 'ناهیدنجفیخانم', 'email' => 'nahid@example.test']);
        $this->seedRequest($tenant, ['name' => 'وفاقردشده', 'email' => 'rejected@example.test', 'status' => ConsultationRequest::STATUS_REJECTED]);
        $this->seedRequest($tenant, ['name' => 'موثق‌تأییدشده', 'email' => 'approved@example.test', 'status' => ConsultationRequest::STATUS_APPROVED]);

        // The default tab is pending: two rows, and neither decided row
        // leaks into it.
        $this->actingAs($admin)->get("http://{$host}/consultant/consultations")
            ->assertOk()
            ->assertSee('سعیدکریمیکوچک')
            ->assertSee('ناهیدنجفیخانم')
            ->assertDontSee('وفاقردشده')
            ->assertDontSee('موثق‌تأییدشده');

        // The tabs carry the counts and both nav components carry the
        // pending badge.
        $this->actingAs($admin)->get("http://{$host}/consultant/consultations?status=all")
            ->assertOk()
            ->assertSee('وفاقردشده')
            ->assertSee('موثق‌تأییدشده')
            ->assertSee('درخواست مشاوره')
            ->assertSee('topnav-notif-badge', false);

        // The detail view: the visitor's data plus the two decision forms.
        $this->actingAs($admin)->get("http://{$host}/consultant/consultations/{$pendingA->id}")
            ->assertOk()
            ->assertSee('saeed@example.test')
            ->assertSee('تأیید درخواست');
    }

    // =========================================================================
    // Decisions
    // =========================================================================

    public function test_approve_and_reject_transition_the_row_and_repeat_idempotently(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('triage-decide.test');
        $admin = $this->staffFor($tenant);
        $this->enableConsultation($tenant);

        $toApprove = $this->seedRequest($tenant, ['email' => 'approve@example.test']);
        $toReject = $this->seedRequest($tenant, ['email' => 'reject@example.test']);

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/consultations/{$toApprove->id}/approve")
            ->assertRedirect(route('consultant.consultations.show', $toApprove))
            ->assertSessionHas('status');

        // A double click re-pins instead of erroring or drifting.
        $this->actingAs($admin)
            ->post("http://{$host}/consultant/consultations/{$toApprove->id}/approve")
            ->assertRedirect(route('consultant.consultations.show', $toApprove));

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/consultations/{$toReject->id}/reject")
            ->assertRedirect(route('consultant.consultations.index'))
            ->assertSessionHas('status');

        $this->actingAs($admin)
            ->post("http://{$host}/consultant/consultations/{$toReject->id}/reject")
            ->assertRedirect(route('consultant.consultations.index'));

        $this->assertSame(ConsultationRequest::STATUS_APPROVED, $toApprove->fresh()->status);
        $this->assertSame(ConsultationRequest::STATUS_REJECTED, $toReject->fresh()->status);
    }

    // =========================================================================
    // Tenant isolation
    // =========================================================================

    public function test_a_consultant_only_sees_their_own_tenants_rows_end_to_end(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain('triage-tenant-a.test');
        $admin = $this->staffFor($tenantA);
        $this->enableConsultation($tenantA);

        [$tenantB] = $this->tenantWithDomain('triage-tenant-b.test');
        $foreign = $this->seedRequest($tenantB, ['name' => 'خارج‌قلمرو', 'email' => 'foreign@example.test']);

        // Their list never shows tenant B's rows.
        $this->actingAs($admin)->get("http://{$hostA}/consultant/consultations")
            ->assertOk()
            ->assertDontSee('foreign@example.test')
            ->assertDontSee('خارج‌قلمرو');

        // The detail view cannot be reached by guessing the foreign id either.
        $this->actingAs($admin)->get("http://{$hostA}/consultant/consultations/{$foreign->id}")
            ->assertNotFound();

        // ...nor can a decision endpoint reach across.
        $this->actingAs($admin)->post("http://{$hostA}/consultant/consultations/{$foreign->id}/approve")
            ->assertNotFound();

        $this->assertSame(ConsultationRequest::STATUS_PENDING, $foreign->fresh()->status);
    }
}
