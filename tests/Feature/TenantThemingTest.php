<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\WebsiteConfig;
use App\Support\SiteConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the tenant-resolution pipeline: domain -> tenant -> layered config ->
 * rendered CSS variables. The pre-existing tests cover auth and data isolation;
 * nothing covered the theming path, which is the half that was broken.
 */
class TenantThemingTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> tenant config files this test created, removed in tearDown */
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

    /**
     * Create a tenant + primary domain, optionally with a config/tenants file
     * naming an archetype. Returns [$tenant, $host].
     */
    private function tenantWithDomain(string $slug, ?string $archetype = null, ?string $name = null): array
    {
        $tenant = Tenant::factory()->create(['slug' => $slug]);

        $host = Str::lower(Str::random(8)).'.sapienstech.test';
        Domain::create([
            'tenant_id'  => $tenant->id,
            'domain'     => $host,
            'is_primary' => true,
        ]);

        Cache::forget(Domain::cacheKey($host));

        if ($archetype !== null || $name !== null) {
            $this->writeTenantFile($slug, array_filter([
                'tenant' => $name !== null ? ['name' => $name, 'short_name' => $name] : null,
                'theme'  => $archetype !== null ? ['archetype' => $archetype] : null,
            ]));
        }

        return [$tenant, $host];
    }

    private function writeTenantFile(string $slug, array $config): void
    {
        $path = config_path("tenants/{$slug}.php");

        file_put_contents($path, '<?php return '.var_export($config, true).';');
        $this->createdFiles[] = $path;

        app(SiteConfig::class)->flush();
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    public function test_landing_page_renders_for_a_tenant_domain(): void
    {
        [, $host] = $this->tenantWithDomain('render-test');

        $this->get("http://{$host}/")->assertOk();
    }

    public function test_the_landing_page_emits_a_complete_token_set(): void
    {
        [, $host] = $this->tenantWithDomain('vars-test');

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        // radius-sidebar and stagger-ms existed in config but the hand-written
        // partial never emitted them.
        foreach (['--c-primary:', '--radius-card:', '--radius-sidebar:', '--stagger-ms:', '--section-gap:'] as $var) {
            $this->assertStringContainsString($var, $html, "{$var} missing from the rendered theme");
        }
    }

    public function test_behaviour_levers_reach_the_markup_as_data_attributes(): void
    {
        [, $host] = $this->tenantWithDomain('attrs-test', 'editorial_serif');

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('data-archetype="editorial_serif"', $html);
        $this->assertStringContainsString('data-reveal="line-mask"', $html);
        $this->assertStringContainsString('data-surface="paper"', $html);
    }

    /**
     * The second wave of levers: motion text effects, the scroll-progress bar,
     * marquee pause, card edges and the button shadow all select behaviour,
     * so they must arrive as data attributes; the value levers as variables.
     */
    public function test_the_new_levers_reach_the_markup(): void
    {
        [, $host] = $this->tenantWithDomain('levers-test', 'aurora_glass');

        site_override([
            'theme' => [
                'motion'     => ['text_effect' => 'gradient-shift', 'scroll_progress' => true, 'marquee_pause' => true],
                'decoration' => ['card_edge' => 'top-accent'],
                'buttons'    => ['shadow' => true, 'letter_spacing' => '.05em'],
                'colors'     => ['link' => '#22D3EE'],
                'layout'     => ['hero_ratio' => '60-40'],
            ],
        ]);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        foreach ([
            'data-text-effect="gradient-shift"',
            'data-scroll-progress="on"',
            'data-marquee-pause="on"',
            'data-card-edge="top-accent"',
            'data-btn-shadow="on"',
            '--c-link: #22D3EE',
            '--hero-cols: 1.5fr 1fr',
            '--btn-letter-spacing: .05em',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "{$needle} missing from the rendered page");
        }
    }

    /** Bento / boxed / masonry variants and the filled optional faq section. */
    public function test_the_new_section_variants_render(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('new-variants', 'aurora_glass');

        $this->writeTenantFile('new-variants', [
            'theme'  => ['archetype' => 'aurora_glass'],
            'public' => ['landing' => [
                'sections'     => ['hero', 'services', 'stats', 'testimonials', 'faq'],
                'services'     => ['variant' => 'bento', 'items' => [
                    ['icon' => 'fa-solid fa-bolt', 'accent' => 'accent-violet', 'title' => 'تست', 'text' => 'تست'],
                    ['icon' => 'fa-solid fa-robot', 'accent' => 'accent-lime', 'title' => 'تست دو', 'text' => 'تست دو'],
                ]],
                'stats'        => ['variant' => 'boxed', 'items' => [
                    ['value' => 5, 'suffix' => '+', 'label' => 'نمونه'],
                ]],
                'testimonials' => ['variant' => 'masonry', 'items' => [
                    ['initials' => 'ن.م', 'name' => 'نمونه', 'result' => 'نتیجه',
                     'from' => 'primary', 'to' => 'accent-cyan', 'text' => 'متن نمونه'],
                ]],
                'faq'          => ['items' => [
                    ['question' => 'پرسش نمونه؟', 'answer' => 'پاسخ نمونه'],
                ]],
            ]],
        ]);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('lp-bento', $html);
        $this->assertStringContainsString('var(--c-accent-violet)', $html);
        $this->assertStringContainsString('lp-masonry', $html);
        $this->assertStringContainsString('lp-faq', $html);
        $this->assertStringContainsString('--c-accent-cyan:', $html);
    }

    /** Per-button icons: two buttons, two different glyphs. */
    public function test_buttons_can_carry_their_own_icons(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('button-icons', 'aurora_glass');

        $this->writeTenantFile('button-icons', [
            'theme'  => ['archetype' => 'aurora_glass'],
            'public' => ['landing' => [
                'sections' => ['hero'],
                'hero'     => ['buttons' => [
                    ['label' => 'الف', 'href' => '#', 'style' => 'solid', 'icon' => 'sparkle', 'visible' => true],
                    ['label' => 'ب',   'href' => '#', 'style' => 'ghost', 'icon' => 'arrow-down', 'visible' => true],
                ]],
            ]],
        ]);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('fa-wand-magic-sparkles', $html);
        $this->assertStringContainsString('fa-arrow-down', $html);
    }

    public function test_the_og_image_meta_is_rendered(): void
    {
        [, $host] = $this->tenantWithDomain('og-test');

        site_override(['public' => ['landing' => ['meta' => ['og_image' => 'images/blog-pics/blog-pic1.png']]]]);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('blog-pic1.png', $html);
    }

    /** The core promise: two archetypes must not render the same CSS. */
    public function test_archetype_changes_the_rendered_theme(): void
    {
        [, $glassHost] = $this->tenantWithDomain('glass-probe', 'aurora_glass');
        [, $paperHost] = $this->tenantWithDomain('paper-probe', 'editorial_serif');

        $glass = $this->get("http://{$glassHost}/")->getContent();
        $paper = $this->get("http://{$paperHost}/")->getContent();

        $this->assertStringContainsString('--c-primary: #06B6D4', $glass);
        $this->assertStringContainsString('--c-primary: #1E3A5F', $paper);
        $this->assertStringContainsString('--radius-card: 24px', $glass);
        $this->assertStringContainsString('--radius-card: 2px', $paper);
    }

    /**
     * The regression most likely to appear later: a resolved tree memoized for
     * one tenant and then served to the next.
     */
    public function test_two_tenants_never_share_a_resolved_config(): void
    {
        [, $hostA] = $this->tenantWithDomain('alpha-probe', 'aurora_glass', 'Alpha');
        [, $hostB] = $this->tenantWithDomain('beta-probe', 'brutalist_mono', 'Beta');

        // A -> B -> A: nothing may be sticky in either direction.
        $this->assertStringContainsString('Alpha', $this->get("http://{$hostA}/")->getContent());
        $this->assertStringContainsString('#D4FF3F', $this->get("http://{$hostB}/")->getContent());
        $this->assertStringContainsString('#06B6D4', $this->get("http://{$hostA}/")->getContent());
        $this->assertStringContainsString('Beta', $this->get("http://{$hostB}/")->getContent());
    }

    // =========================================================================
    // Domain resolution
    // =========================================================================

    public function test_an_unknown_domain_is_not_served(): void
    {
        $host = 'nobody-here-'.Str::lower(Str::random(6)).'.sapienstech.test';
        Cache::forget(Domain::cacheKey($host));

        $this->get("http://{$host}/")->assertNotFound();
    }

    /**
     * The dev fallback must come from config, not the raw environment, because
     * reading environment variables directly returns null once
     * `php artisan config:cache` has run — which would silently break
     * `php artisan serve` on localhost in exactly the setup where caching is on.
     */
    public function test_the_fallback_host_is_read_from_config_not_the_environment(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/IdentifyTenant.php'));

        $this->assertStringNotContainsString('env(', $source,
            "IdentifyTenant must read config('tenancy.*'), not the environment");
        $this->assertStringContainsString("config('tenancy.fallback_domains'", $source);
    }

    public function test_a_configured_fallback_host_serves_the_primary_tenant(): void
    {
        [$tenant, ] = $this->tenantWithDomain('fallback-test');

        config(['tenancy.fallback_domains' => ['localhost']]);

        $this->get('http://localhost/')->assertOk();
    }

    public function test_an_unconfigured_fallback_host_still_404s(): void
    {
        config(['tenancy.fallback_domains' => []]);

        $this->get('http://localhost/')->assertNotFound();
    }

    /** Hosts are stored canonically, so an uppercase insert cannot miss on lookup. */
    public function test_domains_are_stored_in_canonical_form(): void
    {
        $tenant = Tenant::factory()->create();
        $raw    = Str::upper(Str::random(8).'.sapienstech.test');

        $domain = Domain::create([
            'tenant_id'  => $tenant->id,
            'domain'     => $raw,
            'is_primary' => true,
        ]);

        $this->assertSame(strtolower($raw), $domain->fresh()->domain);
    }

    public function test_a_saved_domain_invalidates_the_resolution_cache(): void
    {
        $host = Str::lower(Str::random(8)).'.sapienstech.test';

        Cache::put(Domain::cacheKey($host), 'stale', now()->addMinutes(10));

        $domain = Domain::create([
            'tenant_id'  => Tenant::factory()->create()->id,
            'domain'     => $host,
            'is_primary' => true,
        ]);

        $this->assertNotSame('stale', Cache::get(Domain::cacheKey($host)),
            'Domain::saved must bust the IdentifyTenant cache');

        $domain->delete();
    }

    // =========================================================================
    // Layer precedence
    // =========================================================================

    public function test_site_override_wins_over_every_layer(): void
    {
        [, $host] = $this->tenantWithDomain('override-test', 'aurora_glass');

        site_override(['theme' => ['colors' => ['primary' => '#123456']]]);

        $this->assertStringContainsString('--c-primary: #123456', $this->get("http://{$host}/")->getContent());
    }

    public function test_the_database_layer_overrides_the_tenant_file(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('db-test', 'aurora_glass');

        WebsiteConfig::create([
            'tenant_id'     => $tenant->id,
            'layout_config' => ['theme' => ['colors' => ['primary' => '#ABCDEF']]],
        ]);

        app(SiteConfig::class)->flush();

        $this->assertStringContainsString('--c-primary: #ABCDEF', $this->get("http://{$host}/")->getContent());
    }

    /**
     * A tenant must be able to shorten the section list without the baseline's
     * leftovers splicing back in — the array_replace_recursive trap Merge exists
     * to avoid.
     */
    public function test_a_tenant_can_shorten_the_section_list(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('trim-test', 'aurora_glass');

        $this->writeTenantFile('trim-test', [
            'theme'  => ['archetype' => 'aurora_glass'],
            'public' => ['landing' => ['sections' => ['hero', 'cta']]],
        ]);

        $resolved = app(SiteConfig::class)->resolve($tenant);

        $this->assertSame(['hero', 'cta'], $resolved['public']['landing']['sections']);
    }

    /** An archetype naming a section that has no template must not 500. */
    public function test_an_unknown_section_is_skipped_rather_than_fatal(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('missing-section', 'aurora_glass');

        $this->writeTenantFile('missing-section', [
            'theme'  => ['archetype' => 'aurora_glass'],
            'public' => ['landing' => ['sections' => ['hero', 'definitely_not_a_section']]],
        ]);

        $this->get("http://{$host}/")->assertOk();
    }

    /** A variant that does not exist yet must fall through to the default. */
    public function test_an_unknown_variant_falls_back_to_the_default_template(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('variant-fallback', 'aurora_glass');

        $this->writeTenantFile('variant-fallback', [
            'theme'  => ['archetype' => 'aurora_glass'],
            'public' => ['landing' => ['services' => ['variant' => 'not-built-yet']]],
        ]);

        $this->get("http://{$host}/")->assertOk();
    }

    /**
     * The light-mode block must only override colours.
     *
     * theme.schemes.light names five background/text tokens, but resolving it
     * also fills typography/spacing/shape with platform defaults — emitting
     * those would silently replace a tenant's fonts and radii the moment light
     * mode was active.
     */
    public function test_the_light_scheme_block_only_overrides_colour_tokens(): void
    {
        [, $host] = $this->tenantWithDomain('scheme-test', 'editorial_serif');

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\[data-color-scheme="light"\][^}]*--c-background:\s*#F6F7FB/s', $html);

        // Non-colour tokens must not appear inside the scheme block.
        $block = '';
        if (preg_match('/\[data-color-scheme="light"\]\s*\{(.*?)\}/s', $html, $m)) {
            $block = $m[1];
        }

        $this->assertNotEmpty($block, 'light scheme block was not rendered');

        foreach (['--font-body', '--font-heading', '--radius-card', '--section-gap', '--h1-size'] as $token) {
            $this->assertStringNotContainsString($token, $block, "{$token} must not be overridden by the colour scheme");
        }

        // ...and the tenant's own heading font still reaches the page overall.
        $this->assertStringContainsString('--font-heading: Amiri', $html);
    }
}
