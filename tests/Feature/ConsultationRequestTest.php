<?php

namespace Tests\Feature;

use App\Http\Requests\Public\StoreConsultationRequest;
use App\Models\ConsultationRequest;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\WebsiteConfig;
use App\Support\ConsultationRequests;
use App\Support\SiteConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The consultation-request backend slice: a public, one-way submission that
 * waits on a tenant's decision.
 *
 * The boundaries under test: the endpoint is dark until the tenant's flag
 * exists (wave 2b owns the config row), the tenant comes from the host and
 * never from the form, a repeat pending submission refreshes one row instead
 * of queueing a duplicate, the public form is throttled, rows never cross a
 * tenant, and approve/reject are idempotent status transitions with nothing
 * enrolled behind them.
 */
class ConsultationRequestTest extends TestCase
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

    private function enableConsultation(Tenant $tenant): void
    {
        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'layout_config' => ['features' => ['consultation_requests' => true]],
        ]);
        app(SiteConfig::class)->flush();
    }

    /** A valid public submission. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sara Ahmadi',
            'email' => 'sara@example.test',
            'phone' => '09121112233',
            'message' => 'می‌خواهم درباره دوره‌ها مشورت کنم.',
        ], $overrides);
    }

    public function test_the_endpoint_is_dark_while_the_feature_flag_does_not_exist(): void
    {
        [, $host] = $this->tenantWithDomain('consult-off.test');

        // No config row at all: site() answers false, so this 404s before
        // anything is written. Wave 2b adds the flag row; this test does not
        // need it to exist.
        $this->post("http://{$host}/consultation", $this->payload())->assertNotFound();

        $this->assertSame(0, ConsultationRequest::withoutGlobalScopes()->count());
    }

    public function test_a_visitor_submission_creates_a_pending_row_tenant_scoped_from_the_host(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consult-submit.test');
        [$other] = $this->tenantWithDomain('consult-submit-other.test');
        $this->enableConsultation($tenant);

        // A forged tenant_id in the body must be ignored: the route carries
        // no tenant field, and IdentifyTenant resolves the host.
        $this->post("http://{$host}/consultation", $this->payload(['tenant_id' => $other->id]))
            ->assertRedirect()
            ->assertSessionHas('consultation_status');

        $row = ConsultationRequest::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($tenant->id, $row->tenant_id);
        $this->assertSame('Sara Ahmadi', $row->name);
        $this->assertSame('sara@example.test', $row->email);
        $this->assertSame('09121112233', $row->phone);
        $this->assertSame(ConsultationRequest::STATUS_PENDING, $row->status);
        $this->assertSame(0, ConsultationRequest::withoutGlobalScopes()->where('tenant_id', $other->id)->count());
    }

    public function test_a_repeat_pending_submission_returns_the_same_row_not_a_duplicate(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consult-dedupe.test');
        $this->enableConsultation($tenant);

        $this->post("http://{$host}/consultation", $this->payload())->assertRedirect();
        // The same visitor corrects their message; the email match is
        // case-insensitive, like open_key was for join requests.
        $this->post("http://{$host}/consultation", $this->payload([
            'email' => 'SARA@Example.test',
            'message' => 'تصحیح: درباره مشاوره تحصیلی.',
        ]))->assertRedirect();

        $this->assertSame(1, ConsultationRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $row = ConsultationRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('تصحیح: درباره مشاوره تحصیلی.', $row->message);
        $this->assertSame('sara@example.test', $row->email);
    }

    public function test_rapid_posts_are_throttled_per_ip_and_tenant(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consult-throttle.test');
        $this->enableConsultation($tenant);

        // The limiter is 5/min per IP+tenant: the first five pass, the sixth
        // is the first refusal.
        for ($i = 0; $i < 5; $i++) {
            $this->post("http://{$host}/consultation", $this->payload([
                'email' => "visitor-{$i}@example.test",
            ]))->assertRedirect();
        }

        $this->post("http://{$host}/consultation", $this->payload(['email' => 'sixth@example.test']))
            ->assertStatus(429);

        // The refused request wrote nothing either.
        $this->assertSame(5, ConsultationRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_rows_never_cross_a_tenant(): void
    {
        // TenantIsolationTest style: a direct model read under each tenant's
        // binding — tenant A's rows are invisible to tenant B's context.
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app()->instance('tenant', $tenantA);
        $rowA = ConsultationRequest::create([
            'name' => 'A', 'email' => 'a@example.test', 'message' => 'A',
            'status' => ConsultationRequest::STATUS_PENDING,
        ]);

        app()->instance('tenant', $tenantB);
        $this->assertFalse(ConsultationRequest::whereKey($rowA->id)->exists());
        $rowB = ConsultationRequest::create([
            'name' => 'B', 'email' => 'b@example.test', 'message' => 'B',
            'status' => ConsultationRequest::STATUS_PENDING,
        ]);

        app()->instance('tenant', $tenantA);
        $this->assertTrue(ConsultationRequest::whereKey($rowA->id)->exists());
        $this->assertFalse(ConsultationRequest::whereKey($rowB->id)->exists());
    }

    public function test_approve_and_reject_are_idempotent_status_transitions(): void
    {
        $tenant = Tenant::factory()->create();
        $row = ConsultationRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sara Ahmadi', 'email' => 'sara@example.test', 'message' => 'A',
            'status' => ConsultationRequest::STATUS_PENDING,
        ]);

        // A double click must not error out or drift: whatever the row was,
        // it re-pins to exactly the decided status. Nothing is enrolled, so
        // there is no student_id and no reviewer to stamp.
        ConsultationRequests::approve($row);
        ConsultationRequests::approve($row);
        $this->assertSame(ConsultationRequest::STATUS_APPROVED, $row->fresh()->status);

        ConsultationRequests::reject($row);
        ConsultationRequests::reject($row);
        $this->assertSame(ConsultationRequest::STATUS_REJECTED, $row->fresh()->status);
    }

    public function test_the_message_cap_is_a_validation_error_with_the_limit_named(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consult-cap.test');
        $this->enableConsultation($tenant);

        $this->post("http://{$host}/consultation", $this->payload([
            'message' => str_repeat('x', StoreConsultationRequest::MESSAGE_MAX + 1),
        ]))->assertSessionHasErrors('message');

        $this->assertSame(0, ConsultationRequest::withoutGlobalScopes()->count());
    }

    public function test_malformed_input_is_an_error_not_a_crash(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consult-malformed.test');
        $this->enableConsultation($tenant);

        // An array where a string is expected must fail validation cleanly
        // (§7.6), not raise a 500.
        $this->post("http://{$host}/consultation", [
            'name' => ['nested' => 'array'],
            'email' => 'sara@example.test',
            'message' => 'hello',
        ])->assertSessionHasErrors('name');

        $this->assertSame(0, ConsultationRequest::withoutGlobalScopes()->count());
    }
}
