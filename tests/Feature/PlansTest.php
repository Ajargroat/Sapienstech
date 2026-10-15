<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\StudentJoinRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Paying plans («طرح‌ها»): the landing page's priced cards, and the join form's
 * plan chooser.
 *
 * The security property under test is the whole reason this feature exists in
 * this shape: a price is determined by the TENANT in config, and the public form
 * only ever names a plan. A visitor must never be able to state their own
 * amount, because the consultant verifies the declared transfer against the
 * price — an attacker-supplied number would make that verification meaningless.
 */
class PlansTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: string} */
    private function tenantWithDomain(string $host, array $plans = []): array
    {
        $tenant = Tenant::factory()->create();
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        if ($plans !== []) {
            WebsiteConfig::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'layout_config' => ['public' => ['landing' => [
                    'sections' => ['hero', 'plans', 'join'],
                    'plans' => ['items' => $plans],
                ]]],
            ]);
        }

        return [$tenant, $host];
    }

    /** @return array<string, mixed> */
    private function plan(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'quarterly',
            'name' => 'سه‌ماهه',
            'price' => 3000000,
            'period' => 'سه ماه',
            'features' => "چهار جلسهٔ مشاوره\nبرنامهٔ مطالعهٔ هفتگی",
            'featured' => true,
            'visible' => true,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'زهرا محمدی',
            'email' => 'zahra@example.test',
            'reference' => 'REF-12345',
        ], $overrides);
    }

    // =========================================================================
    // The resolver
    // =========================================================================

    public function test_plans_resolve_from_config_keyed_by_slug(): void
    {
        [$tenant] = $this->tenantWithDomain('plans-resolve.test', [
            $this->plan(['slug' => 'monthly', 'name' => 'ماهانه', 'price' => 1200000, 'featured' => false]),
            $this->plan(),
        ]);

        app()->instance('tenant', $tenant);

        $this->assertSame(['monthly', 'quarterly'], Plans::slugs());
        $this->assertSame(3000000, Plans::priceFor('quarterly'));
        $this->assertSame(1200000, Plans::priceFor('monthly'));
    }

    public function test_an_unknown_slug_has_no_price(): void
    {
        [$tenant] = $this->tenantWithDomain('plans-unknown.test', [$this->plan()]);

        app()->instance('tenant', $tenant);

        // Null (no such plan) is distinct from 0 (a plan priced on request).
        $this->assertNull(Plans::priceFor('does-not-exist'));
        $this->assertNull(Plans::priceFor(''));
        $this->assertNull(Plans::priceFor(null));
    }

    public function test_hidden_plans_are_not_offered(): void
    {
        [$tenant] = $this->tenantWithDomain('plans-hidden.test', [
            $this->plan(['slug' => 'quarterly']),
            $this->plan(['slug' => 'annual', 'price' => 10800000, 'visible' => false]),
        ]);

        app()->instance('tenant', $tenant);

        $this->assertSame(['quarterly'], Plans::slugs());
        $this->assertNull(Plans::priceFor('annual'));
    }

    public function test_features_are_split_one_per_line(): void
    {
        $plan = $this->plan(['features' => "یک\n\n  دو  \r\nسه"]);

        $this->assertSame(['یک', 'دو', 'سه'], Plans::features($plan));
    }

    public function test_a_tenant_with_no_plans_offers_none(): void
    {
        [, $host] = $this->tenantWithDomain('plans-none.test');

        // The baseline ships `items` empty, so a tenant that never opts in has
        // no plans — the verified no-op the whole section design rests on.
        $this->assertSame([], Plans::slugs());
        $this->assertSame([], Plans::options());
    }

    // =========================================================================
    // The public form
    // =========================================================================

    public function test_the_plans_section_renders_the_prices(): void
    {
        [, $host] = $this->tenantWithDomain('plans-render.test', [
            $this->plan(['slug' => 'monthly', 'name' => 'ماهانه', 'price' => 1200000, 'featured' => false]),
            $this->plan(),
        ]);

        $this->get("http://{$host}/")
            ->assertOk()
            ->assertSee('ماهانه')
            ->assertSee('سه‌ماهه')
            // Persian numerals, like every other figure on the site.
            ->assertSee('۳,۰۰۰,۰۰۰');
    }

    public function test_the_section_renders_nothing_when_there_are_no_plans(): void
    {
        [, $host] = $this->tenantWithDomain('plans-empty.test', []);

        $response = $this->get("http://{$host}/");

        $response->assertOk();
        // The section is in `sections` but has no rows, so it must vanish
        // rather than render an empty grid with a heading.
        $response->assertDontSee('طرح‌های مشاوره');
    }

    public function test_the_join_form_offers_the_plans(): void
    {
        [, $host] = $this->tenantWithDomain('plans-form.test', [$this->plan()]);

        $this->get("http://{$host}/")
            ->assertOk()
            ->assertSee('name="plan_slug"', false)
            ->assertSee('value="quarterly"', false);
    }

    // =========================================================================
    // The amount is the tenant's, never the visitor's
    // =========================================================================

    public function test_the_amount_comes_from_the_selected_plan(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('plans-amount.test', [
            $this->plan(['slug' => 'monthly', 'name' => 'ماهانه', 'price' => 1200000, 'featured' => false]),
            $this->plan(),
        ]);

        $this->post("http://{$host}/join", $this->payload(['plan_slug' => 'monthly']))
            ->assertRedirect();

        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(1200000, $request->amount);
        $this->assertSame('monthly', $request->plan_slug);
        $this->assertSame('ماهانه', $request->plan_name);
    }

    /**
     * The adversarial one: the visitor posts an amount of their own choosing.
     *
     * It must be ignored outright — the price is read from the tenant's config
     * by slug, and there is no code path that reads an amount from the form.
     */
    public function test_a_visitor_cannot_name_their_own_price(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('plans-forge.test', [$this->plan()]);

        $this->post("http://{$host}/join", $this->payload([
            'plan_slug' => 'quarterly',
            'amount' => 1,            // "I paid 1 toman"
            'price' => 1,
        ]))->assertRedirect();

        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(3000000, $request->amount);
    }

    public function test_an_unknown_plan_slug_falls_back_to_the_configured_amount(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('plans-fallback.test', [$this->plan()]);

        WebsiteConfig::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update(['layout_config' => ['public' => ['landing' => [
                'sections' => ['hero', 'join'],
                'join' => ['amount' => 500000],
            ]]]]);

        $this->post("http://{$host}/join", $this->payload(['plan_slug' => 'not-a-plan']))
            ->assertRedirect();

        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        // An unoffered slug is dropped entirely, and the section's own hint is
        // used rather than trusting the slug that arrived.
        $this->assertNull($request->plan_slug);
        $this->assertNull($request->plan_name);
        $this->assertSame(500000, $request->amount);
    }

    public function test_a_request_without_a_plan_still_works(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('plans-none-selected.test', [$this->plan()]);

        $this->post("http://{$host}/join", $this->payload())->assertRedirect();

        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->assertNull($request->plan_slug);
        $this->assertSame(0, $request->amount);
    }

    public function test_the_consultant_sees_the_chosen_plan_and_price(): void
    {
        [$tenant, $host] = $this->tenantWithDomain('plans-review.test', [$this->plan()]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_ADMIN,
        ]);

        $this->post("http://{$host}/join", $this->payload(['plan_slug' => 'quarterly']))
            ->assertRedirect();

        $request = StudentJoinRequest::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)
            ->get("http://{$host}/consultant/requests/{$request->id}")
            ->assertOk()
            ->assertSee('طرح انتخابی')
            ->assertSee('سه‌ماهه')
            ->assertSee('۳,۰۰۰,۰۰۰');
    }
}
