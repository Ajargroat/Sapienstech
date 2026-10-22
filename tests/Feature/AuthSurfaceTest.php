<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Models\Domain;
use App\Models\Tenant;
use App\Support\AuthSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * AuthSurface and its dispatch fail closed. Every test here asks the same
 * question LandingSurfaceTest does: can a misconfigured shell ever take down
 * or hijack a live tenant's login page? The answer must always be no — the
 * shared card renders whenever the shell cannot be proven, and the POST
 * contract (both guards, CSRF, throttle:login, session regeneration) is the
 * platform's, never the shell's.
 */
class AuthSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The probe shell: created for a tenant by these tests, always removed in
     * tearDown, never committed. It includes exactly the two shared partials
     * a real shell is allowed to build on — the token sheet and the card —
     * so the rendered page itself proves the security contract is intact.
     */
    private string $probeShell = <<<'BLADE'
        {{-- PROBE ONLY (AuthSurfaceTest): created and removed by the test. --}}
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>probe shell</title>
            @include('partials.theme-vars')
        </head>
        <body data-auth-surface="probe-shell">
            @include('auth.partials.login-card')
        </body>
        </html>
        BLADE;

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

    private function surface(): AuthSurface
    {
        return new AuthSurface;
    }

    private function tenant(string $slug = 'bespoke-login-test'): Tenant
    {
        return Tenant::factory()->create(['slug' => $slug]);
    }

    /** A tenant on its own host, the way a real request resolves one. */
    private function tenantWithHost(string $slug): array
    {
        $tenant = $this->tenant($slug);
        $host = $slug.'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    /** Create resources/landings/{slug}/ with the given shell + manifest. */
    private function makeSurface(string $slug, ?string $blade = '<html></html>', ?array $meta = ['enabled' => true]): string
    {
        $dir = resource_path('landings/'.$slug);
        @mkdir($dir, 0777, true);
        $this->createdDirs[] = $dir;

        if ($blade !== null) {
            file_put_contents($dir.'/login-shell.blade.php', $blade);
        }

        if ($meta !== null) {
            file_put_contents($dir.'/meta.json', json_encode($meta));
        }

        return $dir;
    }

    // --------------------------------------------------- resolution (unit)

    // ---------------------------------------------------------------- happy path

    public function test_a_published_shell_resolves_to_its_view(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug);

        $this->assertSame(
            $tenant->slug.'.login-shell',
            $this->surface()->for($tenant),
        );
    }

    // -------------------------------------------------------------- fails closed

    public function test_no_folder_means_the_shared_card(): void
    {
        $this->assertNull($this->surface()->for($this->tenant('no-shell-here')));
    }

    public function test_a_folder_without_a_manifest_is_not_served(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug, meta: null);

        // Unpublished must mean the shared card, never "publish everything".
        $this->assertNull($this->surface()->for($tenant));
    }

    public function test_an_explicitly_disabled_shell_is_not_served(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug, meta: ['enabled' => false]);

        $this->assertNull($this->surface()->for($tenant));
    }

    public function test_a_truthy_but_not_boolean_enabled_flag_is_not_enough(): void
    {
        $tenant = $this->tenant();

        foreach (['yes', 1, 'true'] as $loose) {
            $this->makeSurface($tenant->slug, meta: ['enabled' => $loose]);
            $this->assertNull($this->surface()->for($tenant), var_export($loose, true));
        }
    }

    public function test_a_manifest_without_the_shell_file_is_not_served(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug, blade: null);

        $this->assertNull($this->surface()->for($tenant));
    }

    public function test_corrupt_json_fails_closed_instead_of_throwing(): void
    {
        $tenant = $this->tenant();
        $dir = $this->makeSurface($tenant->slug, meta: null);
        file_put_contents($dir.'/meta.json', '{not json at all');

        // The point: no exception escapes. The login screen keeps working.
        $this->assertNull($this->surface()->for($tenant));
        $this->assertNull($this->surface()->meta($tenant));
    }

    public function test_a_manifest_that_is_not_an_object_fails_closed(): void
    {
        $tenant = $this->tenant();
        $dir = $this->makeSurface($tenant->slug, meta: null);
        file_put_contents($dir.'/meta.json', '"just a string"');

        $this->assertNull($this->surface()->for($tenant));
    }

    // ------------------------------------------------------------ path traversal

    public function test_a_slug_that_could_escape_the_directory_is_rejected(): void
    {
        foreach (['../etc', 'a/b', 'A-upper', 'with space', '.hidden', '', 'x\\y'] as $slug) {
            $tenant = Tenant::factory()->make(['slug' => $slug]);

            $this->assertNull(
                $this->surface()->for($tenant),
                'Slug must never resolve a view: '.var_export($slug, true),
            );
            $this->assertNull($this->surface()->path($tenant));
        }
    }

    public function test_a_null_tenant_resolves_nothing(): void
    {
        $this->assertNull($this->surface()->for(null));
        $this->assertNull($this->surface()->path(null));
        $this->assertNull($this->surface()->meta(null));
    }

    // ------------------------------------------------------------- end to end

    public function test_the_login_route_serves_the_bespoke_shell_when_published(): void
    {
        [$tenant, $host] = $this->tenantWithHost('shell-published');
        $this->makeSurface($tenant->slug, blade: $this->probeShell);

        $response = $this->get("http://{$host}/login")->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('data-auth-surface="probe-shell"', $content);

        // Presentation may change; the SECURITY contract may not. Both guards'
        // forms, one CSRF token each, and the shared brand sheet render.
        $this->assertSame(2, substr_count($content, 'name="_token"'));
        $this->assertSame(2, substr_count($content, 'name="identifier"'));
        $this->assertStringContainsString('action="http://'.$host.'/login"', $content);
        $this->assertStringContainsString('action="http://'.$host.'/student/login"', $content);
        $this->assertStringContainsString('--c-primary:', $content);
    }

    public function test_the_login_route_falls_back_to_the_shared_card(): void
    {
        [$tenant, $host] = $this->tenantWithHost('shell-unpublished');

        // A folder exists but is not published: the shared card takes it.
        $this->makeSurface($tenant->slug, blade: $this->probeShell, meta: ['enabled' => false]);

        $response = $this->get("http://{$host}/login")->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('data-auth-surface="probe-shell"', $content);
        // The shared page's own token sheet proves the legacy page ran.
        $this->assertStringContainsString('--c-primary:', $content);
        $this->assertSame(2, substr_count($content, 'name="identifier"'));
    }

    public function test_a_second_tenant_gets_the_shared_card(): void
    {
        [$a, $hostA] = $this->tenantWithHost('bespoke-login-a');
        $this->makeSurface($a->slug, blade: $this->probeShell);
        [, $hostB] = $this->tenantWithHost('bespoke-login-b');

        $this->assertStringContainsString(
            'data-auth-surface="probe-shell"',
            $this->get("http://{$hostA}/login")->assertOk()->getContent(),
        );

        // Tenant B's host never carries A's slug, so even with A published,
        // B is handed the shared card — not A's shell.
        $content = $this->get("http://{$hostB}/login")->assertOk()->getContent();

        $this->assertStringNotContainsString('data-auth-surface="probe-shell"', $content);
        $this->assertSame(2, substr_count($content, 'name="identifier"'));
    }

    // ------------------------------------------------- shipped tenants

    /**
     * The live shell must stay the shell.
     *
     * A shell that silently stops being dispatched is indistinguishable from
     * a shell that was never written: the shared card renders, the route
     * still answers 200, and every fail-closed test above still passes. So
     * this pins tenant-one's PUBLISHED shell end to end — the surface tag,
     * its own frame, the de-chromed card, the brand field — alongside the
     * unchanged security contract.
     *
     * It reads the committed files rather than creating any, so it also
     * guards the manifest: an `enabled` that silently flips is caught here.
     */
    public function test_tenant_one_serves_its_published_shell_not_the_shared_card(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tenant-one']);
        $host = 'tenant-one.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        $content = $this->get("http://{$host}/login")->assertOk()->getContent();

        // Dispatched at all — the failure this test exists to catch.
        $this->assertStringContainsString('data-auth-surface="tenant-one"', $content);
        $this->assertStringContainsString('<body class="auth-shell">', $content);

        // The frame the shell owns.
        $this->assertStringContainsString('<blockquote class="auth-shell__quote">', $content);
        $this->assertStringContainsString('auth-shell__panel', $content);

        // The card is de-chromed, never re-implemented: still one shared
        // partial (its login-card hook), still once, still both guards.
        $this->assertSame(1, substr_count($content, 'class="login-card'));
        $this->assertSame(2, substr_count($content, 'name="_token"'));
        $this->assertSame(2, substr_count($content, 'name="identifier"'));
        $this->assertStringContainsString('action="http://'.$host.'/login"', $content);
        $this->assertStringContainsString('action="http://'.$host.'/student/login"', $content);

        // One document, one brand sheet.
        $this->assertSame(1, substr_count($content, '<!DOCTYPE html>'));

        // The reference design replaced the aurora/glass look with a solid
        // field. If either marker returns, the shell regressed.
        $this->assertStringNotContainsString('jn-auth-drift', $content);
        $this->assertStringNotContainsString('background-clip: text', $content);
    }

    // ---------------------------------------------------------------- the LOCK

    /**
     * The LOCK proof: the bespoke shell is presentation dispatch ONLY. The
     * login route, both guards' POST endpoints, their throttling and which
     * controller owns them are platform wiring no shell can touch.
     */
    public function test_the_login_post_contract_stays_controller_bound_and_throttled(): void
    {
        $page = Route::getRoutes()->getByName('login');

        $this->assertSame(LoginController::class.'@showLoginForm', $page->getActionName());
        $this->assertContains('guest', $page->gatherMiddleware());

        $consultant = collect(Route::getRoutes()->getRoutes())->first(
            fn ($route) => in_array('POST', $route->methods(), true) && $route->uri() === 'login',
        );

        $this->assertNotNull($consultant);
        $this->assertSame(LoginController::class.'@login', $consultant->getActionName());
        $this->assertContains('throttle:login', $consultant->gatherMiddleware());

        $student = collect(Route::getRoutes()->getRoutes())->first(
            fn ($route) => in_array('POST', $route->methods(), true) && $route->uri() === 'student/login',
        );

        $this->assertNotNull($student);
        $this->assertSame(StudentLoginController::class.'@login', $student->getActionName());
        $this->assertContains('throttle:login', $student->gatherMiddleware());
    }

    public function test_a_published_shell_still_lets_login_fail_gracefully(): void
    {
        [, $host] = $this->tenantWithHost('shell-graceful');
        $this->makeSurface('shell-graceful', blade: $this->probeShell);

        $this->from("http://{$host}/login")
            ->post("http://{$host}/login", [
                'identifier' => 'nobody-at-shell-graceful',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('identifier');
    }
}
