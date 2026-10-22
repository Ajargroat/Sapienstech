<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\AuthSurface;
use App\Support\LandingSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A tenant may be renamed in the database while its shipped surfaces keep the
 * folder they were authored under. Production is exactly that case: the tenant
 * row is `dr-kafi-mentoring`, the committed surfaces are
 * `resources/landings/drkafimentoring/`.
 *
 * The mapping is the deploy-time `tenant.surface` config key. It is validated
 * with the same slug rule as the tenant slug, so it can never become a path
 * built from request input.
 */
class SurfaceAliasTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): Tenant
    {
        return Tenant::factory()->create(['slug' => $slug]);
    }

    private function hosted(Tenant $tenant, string $host): void
    {
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));
    }

    public function test_the_production_alias_resolves_to_the_committed_surface_folder(): void
    {
        $tenant = $this->tenant('dr-kafi-mentoring');

        $this->assertSame('drkafimentoring.landing', app(LandingSurface::class)->for($tenant));
        $this->assertSame('drkafimentoring.login-shell', app(AuthSurface::class)->for($tenant));
        $this->assertStringEndsWith(
            DIRECTORY_SEPARATOR.'landings'.DIRECTORY_SEPARATOR.'drkafimentoring',
            (string) app(LandingSurface::class)->path($tenant),
        );
    }

    public function test_the_alias_serves_the_bespoke_page_and_login_shell_over_http(): void
    {
        $tenant = $this->tenant('dr-kafi-mentoring');
        $host = 'dr-kafi-mentoring.example.test';
        $this->hosted($tenant, $host);

        $landing = $this->get("http://{$host}/")->assertOk()->getContent();
        $this->assertStringContainsString('dl-masthead', $landing);

        $login = $this->get("http://{$host}/login")->assertOk()->getContent();
        $this->assertStringContainsString('ka-auth', $login);
        $this->assertStringContainsString('action="http://'.$host.'/login"', $login);
        $this->assertStringContainsString('action="http://'.$host.'/student/login"', $login);
    }

    public function test_an_alias_that_is_not_a_safe_slug_is_ignored(): void
    {
        config()->set('tenants.unsafe-alias.tenant.surface', '../escape');
        $tenant = $this->tenant('unsafe-alias');

        // No surface exists under the tenant's own slug either, so both
        // resolvers fail closed rather than following the alias.
        $this->assertNull(app(LandingSurface::class)->for($tenant));
        $this->assertNull(app(AuthSurface::class)->for($tenant));
    }

    public function test_a_tenant_without_an_alias_still_resolves_its_own_slug(): void
    {
        // tenant-one is a shipped surface with no alias; the fallback path is
        // the ordinary one and must not change.
        $tenant = $this->tenant('tenant-one');

        $this->assertSame('tenant-one.landing', app(LandingSurface::class)->for($tenant));
        $this->assertSame('tenant-one.login-shell', app(AuthSurface::class)->for($tenant));
    }

    public function test_the_lint_gate_reports_the_aliased_tenant_as_published(): void
    {
        // Regression: the gate used to look the tenant up by the *folder* name,
        // so production's `dr-kafi-mentoring` row was reported "not published"
        // even though it serves the committed drkafimentoring surface.
        $tenant = $this->tenant('dr-kafi-mentoring');

        $this->assertSame('drkafimentoring', app(LandingSurface::class)->surfaceSlug($tenant));

        $this->artisan('landing:lint', ['slug' => 'dr-kafi-mentoring'])
            ->expectsOutputToContain('drkafimentoring: OK (published)')
            ->assertSuccessful();
    }
}
