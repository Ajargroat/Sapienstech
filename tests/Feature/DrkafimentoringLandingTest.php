<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LandingLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The vertical slice: the real drkafimentoring bespoke page, rendered through
 * a real tenant request. Unit tests prove the resolver works; this proves the
 * page that actually shipped works, with the tenant's real copy.
 */
class DrkafimentoringLandingTest extends TestCase
{
    use RefreshDatabase;

    private function hosted(): string
    {
        $tenant = Tenant::create([
            'name' => 'علیرضا کافی',
            'slug' => 'drkafimentoring',
            'status' => 'active',
        ]);

        $host = 'drkafimentoring.example.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return $host;
    }

    public function test_the_bespoke_page_serves_for_the_tenant(): void
    {
        $host = $this->hosted();

        $response = $this->get("http://{$host}/")->assertOk();
        $body = $response->getContent();

        // The bespoke shell (not the shared one) rendered: its distinctive
        // masthead class exists only in this file.
        $this->assertStringContainsString('dl-masthead', $body);

        // Brand tokens are present and come from the shared partial.
        $this->assertStringContainsString('--c-primary:', $body);
        $this->assertStringContainsString('var(--c-primary)', $body);
    }

    /**
     * The join form is a dialog now: plans open it, it is not a page section,
     * and the removed closing band must not come back.
     */
    public function test_the_join_form_lives_in_a_dialog_and_the_closing_band_is_gone(): void
    {
        $host = $this->hosted();

        $body = $this->get("http://{$host}/")->assertOk()->getContent();

        // The shared partial renders exactly once, inside the dialog.
        $this->assertStringContainsString('<dialog id="dl-join-dialog"', $body);
        $this->assertSame(3, substr_count($body, 'name="plan_slug"'));
        $this->assertStringContainsString('data-dl-join', $body);
        $this->assertStringContainsString('landings/drkafimentoring/landing.js', $body);

        // It is no longer a section, and the closing band is gone: no plan
        // button may point at the old '#join' anchor either.
        $this->assertStringNotContainsString('<section class="dl-band" id="join"', $body);
        $this->assertStringNotContainsString('dl-close', $body);
        $this->assertStringNotContainsString('href="#join"', $body);

        // The one-line footer was replaced by the three-column one.
        $this->assertStringContainsString('dl-foot__nav', $body);
    }

    public function test_the_page_renders_the_tenants_real_content(): void
    {
        $host = $this->hosted();

        $body = $this->get("http://{$host}/")->assertOk()->getContent();

        // Real copy from config/tenants/drkafimentoring.php, not invented text.
        $this->assertStringContainsString('مسیر روشن', $body);
        $this->assertStringContainsString('جلسه شناخت', $body);
        $this->assertStringContainsString('انتخاب رشته', $body);
    }

    public function test_the_page_carries_the_security_headers(): void
    {
        $host = $this->hosted();

        $response = $this->get("http://{$host}/")->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_the_page_is_rtl_and_uses_logical_properties(): void
    {
        $host = $this->hosted();

        $body = $this->get("http://{$host}/")->assertOk()->getContent();

        // The tenant is Persian-first; a bespoke page that hard-codes LTR is
        // broken for the only audience it has.
        $this->assertMatchesRegularExpression('/dir="rtl"/', $body);
        $this->assertStringContainsString('padding-inline', $body);
        $this->assertStringNotContainsString('margin-left:', $body);
        $this->assertStringNotContainsString('padding-right:', $body);
    }

    public function test_the_page_contains_no_inline_script(): void
    {
        $host = $this->hosted();

        $body = $this->get("http://{$host}/")->assertOk()->getContent();

        preg_match_all('/<script\b([^>]*)>/i', $body, $m);

        foreach ($m[1] as $attrs) {
            if (stripos($attrs, 'src=') !== false) {
                continue;
            }

            $this->fail('Bespoke page rendered an inline <script>: '.trim($attrs));
        }
    }

    public function test_the_surface_passes_the_linter(): void
    {
        $source = file_get_contents(resource_path('landings/drkafimentoring/landing.blade.php'));

        $this->assertSame([], (new LandingLint)->check($source));
    }

    /**
     * Second-tenant proof. A probe surface under a test-only slug must serve
     * for its own tenant while a tenant with no surface keeps the legacy page
     * — the override is per-tenant, not global.
     */
    public function test_a_second_tenant_probe_serves_while_others_keep_legacy(): void
    {
        $probeHost = $this->probeHosted();
        $plainHost = $this->plainHosted();

        $this->makeProbeSurface();

        try {
            $probeBody = $this->get("http://{$probeHost}/")->assertOk()->getContent();
            $this->assertStringContainsString('probe-second-marker', $probeBody);

            $plainBody = $this->get("http://{$plainHost}/")->assertOk()->getContent();
            $this->assertStringNotContainsString('probe-second-marker', $plainBody);
            $this->assertStringNotContainsString('dl-masthead', $plainBody);
        } finally {
            $this->removeProbeSurface();
        }
    }

    /**
     * Legacy fallback is byte-identical. Removing a surface (or unpublishing
     * it) must restore exactly the page that rendered before it existed — no
     * composer leakage from the bespoke path. Only the session CSRF token is
     * normalised: it is per-session randomness, not page content.
     */
    public function test_legacy_fallback_is_byte_identical_after_surface_removed(): void
    {
        $host = $this->probeHosted();

        $before = $this->normalise($this->get("http://{$host}/")->assertOk()->getContent());

        $this->makeProbeSurface();

        try {
            $bespoke = $this->get("http://{$host}/")->assertOk()->getContent();
            $this->assertStringContainsString('probe-second-marker', $bespoke);
        } finally {
            $this->removeProbeSurface();
        }

        $after = $this->normalise($this->get("http://{$host}/")->assertOk()->getContent());

        $this->assertSame($before, $after);
    }

    public function test_guest_cannot_preview_a_draft(): void
    {
        $host = $this->probeHosted();

        $this->makeProbeSurface(['enabled' => false]);

        try {
            $this->get("http://{$host}/landing/preview")->assertRedirect();
        } finally {
            $this->removeProbeSurface();
        }
    }

    /**
     * Staff draft preview: an unpublished surface renders for staff with
     * force:true, through the same composer (brand tokens present), carrying
     * X-Robots-Tag noindex — while the public page still serves legacy.
     */
    public function test_staff_can_preview_an_unpublished_draft_with_noindex(): void
    {
        $host = $this->probeHosted();
        $staff = $this->staffFor($host);

        $this->makeProbeSurface(['enabled' => false]);

        try {
            $public = $this->get("http://{$host}/")->assertOk()->getContent();
            $this->assertStringNotContainsString('probe-second-marker', $public);

            $preview = $this->actingAs($staff)
                ->get("http://{$host}/landing/preview")
                ->assertOk();
            $preview->assertHeader('X-Robots-Tag', 'noindex, nofollow');

            $body = $preview->getContent();
            $this->assertStringContainsString('probe-second-marker', $body);
            $this->assertStringContainsString('--c-primary:', $body);
        } finally {
            $this->removeProbeSurface();
        }
    }

    public function test_staff_preview_is_404_without_a_surface(): void
    {
        $host = $this->probeHosted();
        $staff = $this->staffFor($host);

        $this->actingAs($staff)
            ->get("http://{$host}/landing/preview")
            ->assertNotFound();
    }

    private const PROBE_SLUG = 'probe-second-tenant';

    private function probeHosted(): string
    {
        $tenant = Tenant::create([
            'name' => 'Probe Second',
            'slug' => self::PROBE_SLUG,
            'status' => 'active',
        ]);

        $host = self::PROBE_SLUG.'.example.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return $host;
    }

    private function plainHosted(): string
    {
        $tenant = Tenant::create([
            'name' => 'Plain Tenant',
            'slug' => 'plain-tenant',
            'status' => 'active',
        ]);

        $host = 'plain-tenant.example.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return $host;
    }

    private function staffFor(string $host): User
    {
        $tenant = Tenant::findOrFail(Domain::where('domain', $host)->firstOrFail()->tenant_id);
        app()->instance('tenant', $tenant);

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'consultant_staff']);
    }

    /** Create a minimal valid probe surface (both brand includes, exactly once). */
    private function makeProbeSurface(?array $meta = ['enabled' => true]): void
    {
        $dir = resource_path('landings/'.self::PROBE_SLUG);
        @mkdir($dir, 0777, true);

        file_put_contents($dir.'/landing.blade.php', implode("\n", [
            '<!DOCTYPE html>',
            '<html lang="fa" dir="rtl">',
            '<head>',
            '<meta charset="UTF-8">',
            '<title>probe</title>',
            "@include('partials.theme-vars')",
            '</head>',
            "<body @include('partials.theme-attrs')>",
            'probe-second-marker',
            '</body>',
            '</html>',
        ]));

        if ($meta !== null) {
            file_put_contents($dir.'/meta.json', json_encode($meta));
        }
    }

    private function removeProbeSurface(): void
    {
        $dir = resource_path('landings/'.self::PROBE_SLUG);

        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** Strip per-response randomness so two renders can be compared byte-for-byte. */
    private function normalise(string $body): string
    {
        // Session CSRF token and per-response CSP nonce: both are secrets
        // minted per session/response, not page content.
        $body = preg_replace(
            '/(<input[^>]*name="_token"[^>]*value=")[^"]*(")/',
            '$1TOKEN$2',
            $body,
        ) ?? $body;

        return preg_replace('/\snonce="[^"]*"/', ' nonce="NONCE"', $body) ?? $body;
    }
}
