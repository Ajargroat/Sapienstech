<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * How a visitor who has no account *reaches* the join flow.
 *
 * The join form and the priced plans are both opt-in landing sections, so a
 * tenant that configures them has a working flow — but the surrounding links
 * are what make it findable. Before this, the only nav destination was the
 * login page, which a brand-new student cannot use, and the hero's primary
 * button pointed at '#'.
 *
 * The property that matters most here is the negative one: a tenant that has
 * NOT opted in must render exactly what it did before. `join` is absent from
 * the baseline `sections` list, and that is the switch — not a new key a
 * tenant would have to remember to turn off.
 */
class JoinEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(string $host, ?array $landing = null): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        if ($landing !== null) {
            site_override(['public' => ['landing' => $landing]]);
        }

        return [$tenant, $host];
    }

    /**
     * One visible plan, so the `plans` section has something to render.
     *
     * @return array<string, mixed>
     */
    private function plan(): array
    {
        return ['slug' => 'quarterly', 'name' => 'سه‌ماهه', 'price' => 3000000,
            'period' => 'سه ماه', 'features' => "مشاوره\nبرنامه", 'featured' => true, 'visible' => true];
    }

    // =========================================================================
    // The login page is not a dead end
    // =========================================================================

    public function test_a_tenant_with_the_join_section_offers_a_way_to_sign_up(): void
    {
        [, $host] = $this->tenantWithDomain('entry-signup.test', [
            'sections' => ['hero', 'plans', 'join'],
        ]);

        $this->get("http://{$host}/login")
            ->assertOk()
            // The link text is the baseline copy, and it points at the home
            // page's plans grid — the first stop of the join flow.
            ->assertSee('حساب کاربری ندارید؟ درخواست عضویت دهید')
            ->assertSee(route('home').'#plans', false);
    }

    /**
     * The no-op guarantee: without the `join` section there is no membership
     * form to send anyone to, so the link must not render at all.
     */
    public function test_a_tenant_without_the_join_section_shows_no_signup_link(): void
    {
        [, $host] = $this->tenantWithDomain('entry-nosignup.test', [
            'sections' => ['hero', 'services', 'cta'],
        ]);

        $this->get("http://{$host}/login")
            ->assertOk()
            ->assertDontSee('حساب کاربری ندارید؟ درخواست عضویت دهید');
    }

    /**
     * The link tracks the section's *configured id*, not the section key, so a
     * tenant that renamed the anchor still gets a working link.
     */
    public function test_the_signup_link_follows_a_renamed_section_anchor(): void
    {
        [, $host] = $this->tenantWithDomain('entry-renamed.test', [
            'sections' => ['hero', 'plans', 'join'],
            'join' => ['id' => 'membership'],
            'plans' => ['id' => 'pricing'],
        ]);

        $this->get("http://{$host}/login")
            ->assertOk()
            // The link targets `plans`, whose id this tenant renamed.
            ->assertSee(route('home').'#pricing', false);
    }

    /** A tenant may point the link straight at the form instead. */
    public function test_the_signup_target_is_configurable(): void
    {
        [, $host] = $this->tenantWithDomain('entry-fragment.test', [
            'sections' => ['hero', 'plans', 'join'],
        ]);

        site_override(['public' => ['login' => ['signup_link' => [
            'label' => 'همین حالا عضو شوید',
            'fragment' => 'join',
            'visible' => true,
        ]]]]);

        $this->get("http://{$host}/login")
            ->assertOk()
            ->assertSee('همین حالا عضو شوید')
            ->assertSee(route('home').'#join', false);
    }

    /**
     * A tenant that switches the link off, or leaves it unlabelled, gets no
     * link — an empty anchor with no text would be an invisible dead end.
     */
    public function test_the_signup_link_can_be_switched_off(): void
    {
        [, $host] = $this->tenantWithDomain('entry-off.test', [
            'sections' => ['hero', 'plans', 'join'],
        ]);

        site_override(['public' => ['login' => ['signup_link' => [
            'label' => 'حساب کاربری ندارید؟ درخواست عضویت دهید',
            'fragment' => 'plans',
            'visible' => false,
        ]]]]);

        $this->get("http://{$host}/login")
            ->assertOk()
            ->assertDontSee('حساب کاربری ندارید؟ درخواست عضویت دهید');

        // An unlabelled link is suppressed too, rather than rendering blank.
        site_override(['public' => ['login' => ['signup_link' => [
            'label' => '   ',
            'fragment' => 'plans',
            'visible' => true,
        ]]]]);

        $this->get("http://{$host}/login")
            ->assertOk()
            ->assertDontSee(route('home').'#plans', false);
    }

    /**
     * A `fragment` naming a section this tenant does not have falls back to
     * the top of the home page instead of building a dangling anchor.
     */
    public function test_an_unknown_signup_fragment_does_not_build_a_dangling_anchor(): void
    {
        [, $host] = $this->tenantWithDomain('entry-badfrag.test', [
            'sections' => ['hero', 'plans', 'join'],
        ]);

        site_override(['public' => ['login' => ['signup_link' => [
            'label' => 'عضو شوید',
            'fragment' => 'nope',
            'visible' => true,
        ]]]]);

        $this->get("http://{$host}/login")
            ->assertOk()
            ->assertSee('عضو شوید')
            ->assertDontSee('#nope', false);
    }

    // =========================================================================
    // The anchor vocabulary
    // =========================================================================

    /**
     * A button whose destination is an in-page anchor must render that anchor
     * verbatim. Passing '#plans' to Laravel's route() would throw, so the
     * shared button partial treats a '#'-prefixed destination as a URL.
     */
    public function test_an_anchor_destination_renders_as_a_plain_href(): void
    {
        [, $host] = $this->tenantWithDomain('entry-anchor.test', [
            'sections' => ['hero', 'plans', 'join', 'cta'],
            'plans' => ['items' => [$this->plan()]],
            'cta' => [
                'buttons' => [
                    ['label' => 'شروع کنید', 'route' => '#plans', 'style' => 'primary', 'visible' => true],
                ],
            ],
        ]);

        $this->get("http://{$host}/")
            ->assertOk()
            ->assertSee('href="#plans"', false)
            ->assertSee('شروع کنید');
    }

    /**
     * The landing page's own links must all resolve to a section that exists,
     * or a visitor follows a link into nothing. This is what caught the hero
     * button pointing at '#'.
     */
    public function test_the_home_page_links_to_the_join_flow(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('entry-home.test', [
            'sections' => ['hero', 'plans', 'join', 'cta'],
            'plans' => ['items' => [$this->plan()]],
            // The hero's primary button and the closing band, pointed at the
            // plans grid the way the drkafimentoring tenant configures them.
            'hero' => [
                'buttons' => [
                    ['label' => 'رزرو وقت مشاوره', 'href' => '#plans', 'style' => 'primary', 'visible' => true],
                ],
            ],
            'cta' => [
                'buttons' => [
                    ['label' => 'رزرو وقت مشاوره', 'route' => '#plans', 'style' => 'primary', 'visible' => true],
                ],
            ],
        ]);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('id="plans"', $html);
        $this->assertStringContainsString('id="join"', $html);

        // The hero's primary button (an `href`) and the closing band (a
        // `route`) both lead into the flow rather than to a bare '#' — and
        // both forms of the anchor render the same way.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'href="#plans"'),
            'the hero and the closing call-to-action should both point at the plans grid',
        );
    }
}
