<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\SiteConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The brand-consistency measurement: every visitor-facing surface must emit
 * the SAME brand tokens, on the SAME layers, for the SAME tenant.
 *
 * This test does not pin tenant-one's palette (any value the pipeline emits
 * is correct for its tenant); it pins the invariant a user can actually SEE
 * when it breaks: landing, login and dashboard all carrying identical --c-*
 * brand variables. Four configurations are measured:
 *
 *   a) no personal layer, no DB layer — the three surfaces must be EQUAL;
 *   b) an authenticated user with a personal layer (`preferences.site`) —
 *      the measured record of the "blue dashboard, green landing" report;
 *   c) website_configs.layout_config overriding colors.primary — measured;
 *   d) personal layer PLUS the tenant's brand lock — the locked override is
 *      dropped and the surfaces re-equalize.
 */
class BrandConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A slug with NO `config/tenants/{slug}.php` behind it, on purpose. The
     * fixture must render the baseline + archetype chain so these assertions
     * measure the platform's behaviour, not whatever a real tenant happens to
     * configure. It previously read `tenant-one`, which collided with the real
     * tenant file: adding `theme.brand_lock` to that file silently changed
     * what this suite measured.
     */
    private const SLUG = 'brand-probe';

    private const HOST = 'tenant1.sapienstech.test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => self::SLUG]);
        Domain::create(['tenant_id' => $this->tenant->id, 'domain' => self::HOST, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey(self::HOST));
    }

    /** The `--{name}: {value}` pairs of the first `:root` block of a page. */
    private function brandVars(string $html): array
    {
        $this->assertStringContainsString('<style>', $html, 'no inline token block rendered');

        preg_match('/<style>\s*:root \{(.*?)\}/s', $html, $root);
        $this->assertArrayHasKey(1, $root, 'no :root token block rendered');

        // CRLF pages: normalise before line-anchored parsing.
        preg_match_all('/^\s*(--[a-z0-9-]+):\s*([^;]+);/m', str_replace("\r", '', $root[1]), $pairs, PREG_SET_ORDER);

        $vars = [];

        foreach ($pairs as [, $name, $value]) {
            $vars[$name] = trim($value);
        }

        return $vars;
    }

    /** Landing + login are guest pages: capture them before anyone acts in. */
    private function guestPages(): array
    {
        return [
            'landing' => $this->brandVars($this->get('http://'.self::HOST.'/')->assertOk()->getContent()),
            'login' => $this->brandVars($this->get('http://'.self::HOST.'/login')->assertOk()->getContent()),
        ];
    }

    private function dashboard(User $user): array
    {
        return ['dashboard' => $this->brandVars(
            $this->actingAs($user)->get('http://'.self::HOST.'/consultant/dashboard')->assertOk()->getContent(),
        )];
    }

    private function plainUser(): User
    {
        return User::factory()->consultant()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_without_any_override_the_three_surfaces_emit_identical_brand_vars(): void
    {
        $guests = $this->guestPages();
        $pages = $this->dashboard($this->plainUser()) + $guests;

        $this->assertNotSame([], $guests['landing']);
        $this->assertSame($guests['landing'], $guests['login']);
        $this->assertSame($guests['landing'], $pages['dashboard']);
    }

    public function test_a_personal_layer_swings_only_the_surfaces_its_owner_sees(): void
    {
        $guests = $this->guestPages();

        $user = User::factory()->consultant()->create([
            'tenant_id' => $this->tenant->id,
            'preferences' => ['site' => ['theme' => ['colors' => ['primary' => '#123456']]]],
        ]);

        $pages = $this->dashboard($user) + $guests;

        // Guests never carry the layer: landing and login still agree with
        // each other. The user-reported divergence is reproduced ONLY on the
        // page the personalising owner actually opens.
        $this->assertSame($guests['landing'], $guests['login']);
        $this->assertNotSame($guests['landing'], $pages['dashboard']);
        $this->assertSame('#123456', $pages['dashboard']['--c-primary']);
    }

    public function test_a_database_layer_overrides_every_surface_at_once(): void
    {
        $background = $this->guestPages();

        WebsiteConfig::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'layout_config' => ['theme' => ['colors' => ['primary' => '#0A5A5A']]],
        ]);
        app(SiteConfig::class)->flush();

        $guests = $this->guestPages();
        $pages = $this->dashboard($this->plainUser()) + $guests;

        $this->assertNotSame($background['landing']['--c-primary'], $guests['landing']['--c-primary']);
        $this->assertSame('#0A5A5A', $guests['landing']['--c-primary']);
        // One layer, one fate: all three surfaces move together, so the DB
        // layer is not something the brand can be seen disagreeing with.
        $this->assertSame($guests['landing'], $guests['login']);
        $this->assertSame($guests['landing'], $pages['dashboard']);
    }

    public function test_a_brand_lock_drops_the_locked_swings_and_the_surfaces_re_equalize(): void
    {
        // (d) A personal layer PLUS the tenant's brand lock. The locked
        // group's token paths never reach a surface, so the owner's
        // dashboard renders the tenant's own --c-primary again; a tweak
        // outside the lock still answers its owner; the guests never see
        // the layer at all.
        site_override(['theme' => ['brand_lock' => ['colors']]]);

        // Guests are guests: capture them before anyone acts.
        $guests = $this->guestPages();

        $user = User::factory()->consultant()->create([
            'tenant_id' => $this->tenant->id,
            'preferences' => ['site' => ['theme' => [
                // The one colour the earlier cases already pin.
                'colors' => ['primary' => '#123456'],
                // 'layout' is NOT locked: everything the tenant did not
                // lock stays per-user overridable.
                'layout' => ['card_gap' => '77px'],
            ]]],
        ]);

        $dashboard = $this->dashboard($user);

        // The locked group cannot swing the brand the owner opens.
        $this->assertSame($guests['landing']['--c-primary'], $dashboard['dashboard']['--c-primary']);
        $this->assertNotSame('#123456', $dashboard['dashboard']['--c-primary']);

        // Guest pages stay on the tenant's own brand, never the user's.
        $this->assertSame($guests['landing'], $guests['login']);

        // The lock is not a mute button for the whole layer: 'layout' still
        // answers the user who set it, and only on that user's pages.
        $this->assertSame('77px', $dashboard['dashboard']['--card-gap']);
        $this->assertNotSame($guests['landing']['--card-gap'], $dashboard['dashboard']['--card-gap']);
    }

    public function test_a_brand_lock_follows_the_real_token_vocabulary(): void
    {
        // The lockable vocabulary is ThemeTokens::VARS's real top-level
        // groups, not the tenant's freehand. An entry naming no real group
        // is inert: no crash, and no accidental lock of a NEIGHBOUR group.
        site_override(['theme' => ['brand_lock' => ['colours']]]);

        $user = User::factory()->consultant()->create([
            'tenant_id' => $this->tenant->id,
            'preferences' => ['site' => ['theme' => ['colors' => ['primary' => '#123456']]]],
        ]);

        $this->assertSame('#123456', $this->dashboard($user)['dashboard']['--c-primary']);
    }
}
