<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The composer's de-keying contract (docs/landing-data.md, class B): DB-backed
 * public reads reach the shared page AND every bespoke `{slug}.landing`
 * surface through the same LandingFeatures manifest — identical items,
 * identical see_all and empty-drop semantics, tenant-scoped.
 */
class LandingDataInjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> probe surface directories, removed in tearDown */
    private array $probeDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->probeDirs as $dir) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }

        $this->probeDirs = [];

        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(string $slug): array
    {
        $tenant = Tenant::factory()->create(['slug' => $slug]);
        $host = $slug.'.sapienstech.test';
        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
            'is_primary' => true,
        ]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    /**
     * A published bespoke surface whose body embeds the shared landing — so
     * one page carries both the bespoke marker and the shell's own section
     * loop, and the assertion proves the bespoke view received exactly what
     * the shell would have received.
     */
    private function makeProbeSurface(string $slug): void
    {
        $dir = resource_path('landings/'.$slug);
        @mkdir($dir, 0777, true);
        $this->probeDirs[] = $dir;

        file_put_contents(
            $dir.'/landing.blade.php',
            '<p class="probe-shell" data-probe-marker="landing-db-injection">@include(\'public.landing\')</p>'
        );
        file_put_contents($dir.'/meta.json', json_encode(['enabled' => true]));
    }

    public function test_a_bespoke_surface_receives_db_blog_data(): void
    {
        [$probe, $probeHost] = $this->tenantWithDomain('landing-injection-probe');
        [$legacy, $legacyHost] = $this->tenantWithDomain('landing-shell-probe');
        $this->makeProbeSurface('landing-injection-probe');

        // Both tenants carry the SAME shared titles, plus one per-tenant-only
        // title, so bespoke == shell is provable on titles AND tenant scoping
        // is provable by absence at the same time.
        $shared = ['Landing probe post alpha-7431', 'Landing probe post beta-2960'];

        foreach ($shared as $title) {
            BlogPost::factory()->published()->create(['tenant_id' => $legacy->id, 'title' => $title]);
            BlogPost::factory()->published()->create(['tenant_id' => $probe->id, 'title' => $title]);
        }

        BlogPost::factory()->published()->create([
            'tenant_id' => $probe->id,
            'title' => 'Probe-only post delta-7288',
        ]);
        BlogPost::factory()->published()->create([
            'tenant_id' => $legacy->id,
            'title' => 'Legacy-only post omega-6183',
        ]);

        $probeBody = $this->get("http://{$probeHost}/")->assertOk()->getContent();
        $legacyBody = $this->get("http://{$legacyHost}/")->assertOk()->getContent();

        // The bespoke page rendered (its own markup), and the shell section
        // loop embedded inside it fed the tenant's real posts.
        $this->assertStringContainsString('data-probe-marker="landing-db-injection"', $probeBody);
        $this->assertStringContainsString('lp-section--blog', $probeBody);
        $this->assertStringContainsString('lp-section--blog', $legacyBody);

        foreach ($shared as $title) {
            $this->assertStringContainsString($title, $legacyBody);
            $this->assertStringContainsString($title, $probeBody);
        }

        // Tenant scoping: each surface carries only its own tenant's rows.
        $this->assertStringContainsString('Probe-only post delta-7288', $probeBody);
        $this->assertStringNotContainsString('Probe-only post delta-7288', $legacyBody);
        $this->assertStringContainsString('Legacy-only post omega-6183', $legacyBody);
        $this->assertStringNotContainsString('Legacy-only post omega-6183', $probeBody);
    }

    public function test_empty_blog_table_drops_blog_section_with_200(): void
    {
        [$probe, $probeHost] = $this->tenantWithDomain('landing-injection-empty');
        $this->makeProbeSurface('landing-injection-empty');

        // Nothing published anywhere: the section drops off the landing
        // instead of showing an empty heading — the bespoke surface included.
        $body = $this->get("http://{$probeHost}/")->assertOk()->getContent();

        $this->assertStringNotContainsString('data-studio-section-marker="blog"', $body);
        $this->assertStringNotContainsString('lp-section--blog', $body);

        // The section loop still renders its neighbours: the dropped section
        // must not take the page with it.
        $this->assertStringContainsString('data-studio-section-marker="cta"', $body);
    }

    public function test_blog_see_all_href_defaults_to_blog_index(): void
    {
        [$legacy, $host] = $this->tenantWithDomain('landing-see-all-probe');

        BlogPost::factory()->published()->create([
            'tenant_id' => $legacy->id,
            'title' => 'See-all probe post alpha-5173',
        ]);

        $this->get("http://{$host}/")
            ->assertOk()
            ->assertSee('data-studio-section-marker="blog"', false)
            ->assertSee(route('blog.index'), false);
    }
}
