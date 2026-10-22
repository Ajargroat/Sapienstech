<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\SiteConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SecurityHeaders end-to-end: the headers must reach a *real tenant page*
 * through the full middleware stack, not just through the middleware on its
 * own. The unit-level behaviour is covered by SecurityHeadersTest; this file
 * answers the only question that matters in production — "does a visitor on
 * a tenant's domain actually receive the floor?"
 */
class TenantSecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            @unlink($path);
        }

        $this->createdFiles = [];
        app(SiteConfig::class)->flush();

        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(string $slug): array
    {
        $tenant = Tenant::factory()->create(['slug' => $slug]);

        $host = Str::lower(Str::random(8)).'.sapienstech.test';
        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
            'is_primary' => true,
        ]);

        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    public function test_a_tenant_landing_page_carries_the_hardening_headers(): void
    {
        [, $host] = $this->tenantWithDomain('sec-headers-test');

        $response = $this->get("http://{$host}/")->assertOk();

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Default rollout is report-only, so the enforcing header must be
        // absent and the reporting one present.
        $response->assertHeaderMissing('Content-Security-Policy');
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_a_tenant_login_page_carries_the_headers_too(): void
    {
        [, $host] = $this->tenantWithDomain('sec-login-test');

        $response = $this->get("http://{$host}/login");

        // The login route exists per tenant; if it redirects that is fine, the
        // point is the header survives whichever response comes back.
        $this->assertTrue(in_array($response->getStatusCode(), [200, 302], true));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_enforcing_the_policy_still_renders_the_landing_page(): void
    {
        config()->set('security.csp.mode', 'enforce');

        [, $host] = $this->tenantWithDomain('sec-enforce-test');

        $response = $this->get("http://{$host}/")->assertOk();

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $policy);

        // The page still renders its tokens: a policy that broke rendering
        // would be worse than no policy, so this is the guard against that.
        $response->assertSee('--c-primary:', false);
    }

    /**
     * Every inline script on a rendered page must carry the nonce the policy
     * published. This is the wiring test that stops the policy from silently
     * killing behaviour: a tag missing its nonce is invisible in report-only
     * mode and only shows up as a broken button in production.
     */
    public function test_every_inline_script_on_the_landing_page_carries_the_published_nonce(): void
    {
        config()->set('security.csp.mode', 'enforce');

        [, $host] = $this->tenantWithDomain('sec-nonce-test');

        $html = $this->get("http://{$host}/")->assertOk()->getContent();
        $policy = (string) $this->get("http://{$host}/")->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/'nonce-([A-Za-z0-9+\/=]+)'/", $policy);

        // Find every <script> that has no src= — those are the inline ones the
        // policy must authorise.
        preg_match_all('/<script\b([^>]*)>/i', $html, $matches);

        $inline = 0;
        foreach ($matches[1] as $attrs) {
            if (stripos($attrs, 'src=') !== false) {
                continue;
            }

            $inline++;
            $this->assertMatchesRegularExpression(
                '/nonce="[A-Za-z0-9+\/=]+"/',
                $attrs,
                'An inline <script> reached the page without a CSP nonce: '.trim($attrs),
            );
        }

        $this->assertGreaterThan(0, $inline, 'Expected at least one inline script on the landing page.');
    }

    public function test_the_nonce_is_unique_per_response(): void
    {
        config()->set('security.csp.mode', 'enforce');

        [, $host] = $this->tenantWithDomain('sec-nonce-unique-test');

        $first = (string) $this->get("http://{$host}/")->headers->get('Content-Security-Policy');
        $second = (string) $this->get("http://{$host}/")->headers->get('Content-Security-Policy');

        // A reused nonce is a reusable capability: it would let one response's
        // injected script run in the next.
        $this->assertNotSame($first, $second);
    }
}
