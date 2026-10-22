<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\WebsiteConfig;
use App\Support\SiteConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The consultation section renders for opted-in tenants on BOTH surfaces —
 * the shell renderer and a bespoke `{slug}.landing` page — and stays
 * byte-inert for tenants that never opted in (the §7.9 alive-branch rule:
 * a branch a tenant did not key must render byte-for-byte what it rendered
 * before).
 *
 * The studio whitelist is pinned against the partial's actual reads, so a
 * key the partial reads without a lever (or a lever nothing reads) fails
 * here instead of surfacing as a silently uneditable form.
 */
class ConsultationLandingTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> directories created here, removed in tearDown */
    private array $createdDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->createdDirs as $dir) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }

        $this->createdDirs = [];

        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(string $host): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    /**
     * The same DB-layer idiom the triage and join tests pin: a layout_config
     * row merged over the baseline. `sections` replaces the list wholesale
     * (studio list semantics), so the opted-in list carries the section and
     * the baseline `public.landing.consultation` block supplies its copy.
     *
     * @param  list<string>  $sections
     */
    private function optIn(Tenant $tenant, array $sections = ['hero', 'cta', 'consultation']): void
    {
        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'layout_config' => [
                'features' => ['consultation_requests' => true],
                'public' => ['landing' => ['sections' => $sections]],
            ],
        ]);
        app(SiteConfig::class)->flush();
    }

    /** A temp bespoke surface, cleaned in tearDown (LandingSurfaceTest's pattern). */
    private function makeSurface(string $slug): void
    {
        $dir = resource_path('landings/'.$slug);
        @mkdir($dir, 0777, true);
        $this->createdDirs[] = $dir;

        // The composer (de-keyed, wave 1) feeds every bespoke surface the same
        // composed variables as the shell — this probe consumes them exactly
        // the way the drkafimentoring page's include contract does.
        file_put_contents($dir.'/landing.blade.php',
            '<html><body data-bespoke-probe>'.
            "@include('public.sections.consultation.default', ['cfg' => (\$L['consultation'] ?? []), 'anim' => ['reveal' => false]])".
            '</body></html>'
        );
        file_put_contents($dir.'/meta.json', json_encode(['enabled' => true]));
    }

    /** The two per-request movers (CSRF token, CSP nonce) neutralised by name. */
    private function stable(string $body): string
    {
        $body = (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value="X"', $body);
        $body = (string) preg_replace('/nonce="[^"]*"/', 'nonce="X"', $body);

        return $body;
    }

    // =========================================================================
    // The section renders where it was opted in — on both surfaces
    // =========================================================================

    public function test_the_opted_in_shell_landing_renders_the_consultation_form(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consultation-shell.test');
        $this->optIn($tenant);

        $response = $this->get('http://consultation-shell.test/');

        $response->assertOk();
        $response->assertSee(route('consultation.store'), false);
        $response->assertSee('ارسال درخواست');
        $response->assertSee('درخواست مشاوره');
    }

    public function test_a_bespoke_surface_renders_the_consultation_partial_when_opted_in(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consultation-bespoke.test');
        $this->makeSurface($tenant->slug);
        $this->optIn($tenant);

        $response = $this->get('http://consultation-bespoke.test/');

        $response->assertOk();
        // The probe surface embeds the shared partial and consumed the
        // composer-fed `$L` — the bespoke page is connected to the same
        // backend the shell is.
        $this->assertStringContainsString('data-bespoke-probe', $response->getContent());
        $this->assertStringContainsString(route('consultation.store'), $response->getContent());
    }

    // =========================================================================
    // The section is inert for tenants that never opted in
    // =========================================================================

    public function test_a_tenant_without_opt_in_renders_byte_identical_with_and_without_the_flag(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('consultation-inert.test');

        // Flag off, section not listed — today's default for every tenant.
        $off = $this->stable($this->get('http://consultation-inert.test/')->getContent());

        // Flag on but the section still not listed: the @includeIf no-ops an
        // unlisted section, so the page must not change by a byte.
        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'layout_config' => ['features' => ['consultation_requests' => true]],
        ]);
        app(SiteConfig::class)->flush();

        $on = $this->stable($this->get('http://consultation-inert.test/')->getContent());

        $this->assertSame($off, $on, 'a non-opted tenant must render byte-identically with the flag on');
        $this->assertStringNotContainsString('consultation.store', $on);
    }

    // =========================================================================
    // The studio whitelist mirrors the partial's actual reads
    // =========================================================================

    public function test_studio_rows_cover_every_key_the_consultation_partial_reads(): void
    {
        $root = dirname(__DIR__, 2);
        $partial = (string) file_get_contents($root.'/resources/views/Public/sections/consultation/default.blade.php');

        // The cfg keys the partial actually reads: $c['key'] occurrences.
        preg_match_all("/\\\$c\['([a-z_]+)'\]/", $partial, $reads);
        $reads = array_values(array_unique($reads[1]));

        // `id` is structural (the section's DOM id), never a studio lever.
        $contentKeys = array_values(array_diff($reads, ['id']));

        $this->assertSame(['heading', 'subheading', 'submit_label'], $contentKeys,
            'the partial grew a config key without this test being told');

        $studio = (string) file_get_contents($root.'/config/studio.php');

        foreach ($contentKeys as $key) {
            $this->assertStringContainsString(
                "'path' => 'public.landing.consultation.".$key."'",
                $studio,
                'the partial reads public.landing.consultation.'.$key.' but no studio row feeds it'
            );
        }

        // The reverse direction: no dead studio rows for keys nothing reads.
        preg_match_all("/'path' => 'public\.landing\.consultation\.([a-z_.]+)'/", $studio, $rows);
        foreach (array_unique($rows[1]) as $row) {
            $this->assertContains($row, $contentKeys,
                'studio row public.landing.consultation.'.$row.' has no consumer in the partial');
        }

        // The sections picker must offer the section, or a tenant cannot opt
        // in from the studio at all.
        $this->assertStringContainsString("'consultation' => 'Consultation request'", $studio);
    }
}
