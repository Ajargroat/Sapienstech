<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Support\AuthSurface;
use App\Support\LandingLint;
use App\Support\LandingSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `tenant:make` is the factory for a new tenant. It must leave that tenant on
 * the new bespoke landing system, not on the legacy shared skeleton — and the
 * generated page must pass the same publish gate a hand-written one does.
 */
class TenantMakeTest extends TestCase
{
    use RefreshDatabase;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slug = 'probe-tenant-'.Str::lower(Str::random(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(resource_path('landings/'.$this->slug));
        $this->removeDirectory(public_path('tenants/'.$this->slug));

        $config = config_path('tenants/'.$this->slug.'.php');
        if (is_file($config)) {
            @unlink($config);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
        }

        @rmdir($dir);
    }

    public function test_it_scaffolds_a_published_bespoke_surface_and_login_shell(): void
    {
        $this->artisan('tenant:make', [
            'name' => 'Probe Academy',
            'slug' => $this->slug,
            '--domain' => $this->slug.'.example.test',
        ])->assertSuccessful();

        $dir = resource_path('landings/'.$this->slug);

        $this->assertFileExists($dir.'/landing.blade.php');
        $this->assertFileExists($dir.'/login-shell.blade.php');
        $this->assertFileExists($dir.'/meta.json');

        $meta = json_decode((string) file_get_contents($dir.'/meta.json'), true);
        $this->assertTrue($meta['enabled'] ?? false);
        $this->assertSame('tenant:make', $meta['published_by'] ?? null);

        $landing = (string) file_get_contents($dir.'/landing.blade.php');
        $login = (string) file_get_contents($dir.'/login-shell.blade.php');

        $this->assertSame([], (new LandingLint)->check($landing));
        $this->assertStringContainsString("@include('partials.theme-vars')", $landing);
        $this->assertStringContainsString("@include('partials.theme-attrs')", $landing);
        $this->assertStringContainsString('@include(\'auth.partials.login-card\'', $login);

        $tenant = Tenant::where('slug', $this->slug)->firstOrFail();

        $this->assertSame($this->slug.'.landing', app(LandingSurface::class)->for($tenant));
        $this->assertSame($this->slug.'.login-shell', app(AuthSurface::class)->for($tenant));
    }

    public function test_it_refuses_to_reuse_an_existing_slug(): void
    {
        Tenant::factory()->create(['slug' => $this->slug]);

        $this->artisan('tenant:make', ['name' => 'Probe', 'slug' => $this->slug])
            ->assertFailed();

        $this->assertFileDoesNotExist(config_path('tenants/'.$this->slug.'.php'));
    }
}
