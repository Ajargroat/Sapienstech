<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Domain;
use App\Models\Tenant;
use App\Support\AuthSurface;
use App\Support\LandingSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every shipped tenant serves the new bespoke system on the same route pair:
 * `/` for its landing and `/login` for its login shell. These are render
 * tests, not resolver tests: a Blade mistake, a missing meta.json or a shell
 * that forgets the shared card would not be caught by the unit tests.
 */
class TenantSurfacesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: string} */
    private function hosted(string $slug): array
    {
        $name = match ($slug) {
            'moein' => 'مؤسسه معین',
            'salam' => 'آکادمی سلام',
            'agentation-scratch' => 'SP Agentation Scratch',
            'tenant-one' => 'آکادمی جنت',
            default => $slug,
        };

        $tenant = Tenant::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'hierarchy_type' => $slug === 'moein' ? 'school' : 'consultancy',
        ]);
        $host = $slug.'.surfaces.example.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    public static function surfaceProvider(): array
    {
        return [
            'moein editorial' => ['moein', 'me-mast', 'مؤسسه معین'],
            'salam brutalist' => ['salam', 'sa-top', 'آکادمی سلام'],
            'agentation scaffold' => ['agentation-scratch', 'st-hero', 'SP Agentation Scratch'],
        ];
    }

    #[DataProvider('surfaceProvider')]
    public function test_each_new_surface_is_published_and_serves_over_its_own_host(string $slug, string $marker, string $name): void
    {
        [$tenant, $host] = $this->hosted($slug);

        $this->assertSame($slug.'.landing', app(LandingSurface::class)->for($tenant));
        $this->assertSame($slug.'.login-shell', app(AuthSurface::class)->for($tenant));

        $landing = $this->get("http://{$host}/")->assertOk()->getContent();
        $this->assertStringContainsString($marker, $landing);
        $this->assertStringContainsString($name, $landing);
        $this->assertStringContainsString('--c-primary:', $landing);
        $this->assertStringContainsString('dir="rtl"', $landing);

        $login = $this->get("http://{$host}/login")->assertOk()->getContent();
        $this->assertStringContainsString('<body class="auth-shell">', $login);
        $this->assertStringContainsString('login-card', $login);
        $this->assertStringContainsString('action="http://'.$host.'/login"', $login);
        $this->assertStringContainsString('action="http://'.$host.'/student/login"', $login);
        $this->assertStringContainsString('name="_token"', $login);
    }

    public function test_moein_renders_its_editorial_sections_and_live_school_facts(): void
    {
        [$tenant, $host] = $this->hosted('moein');

        Classroom::create([
            'tenant_id' => $tenant->id,
            'name' => 'هفتم — الف',
            'grade' => 'هفتم',
        ]);

        $body = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('پایه هفتم', $body);
        $this->assertStringContainsString('هفتم — الف', $body);
        $this->assertStringContainsString('me-class', $body);
        // The faculty section drops when there is no staff, but its heading
        // must not: the page still owns the editorial section order.
        $this->assertStringContainsString('me-sec__h', $body);
    }

    public function test_salam_renders_the_comparison_table_and_the_team_blocks(): void
    {
        [, $host] = $this->hosted('salam');

        $body = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('sa-compare', $body);
        $this->assertStringContainsString('چرا مشاوره فردی؟', $body);
        $this->assertStringContainsString('تیم مشاوران سلام', $body);
        $this->assertStringContainsString('بهاره نظری', $body);
        $this->assertStringContainsString('امیرحسین کریمی', $body);
        $this->assertStringContainsString('سپیده رحیمی', $body);
    }

    public function test_the_legacy_shared_landing_is_still_served_for_a_tenant_without_a_surface(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'legacy-only', 'name' => 'Legacy']);
        $host = 'legacy-only.example.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        $this->assertNull(app(LandingSurface::class)->for($tenant));

        $response = $this->get("http://{$host}/")->assertOk();
        $this->assertStringContainsString('landing', strtolower($response->getContent()));
        $this->assertStringNotContainsString('me-mast', $response->getContent());
        $this->assertStringNotContainsString('sa-top', $response->getContent());
    }
}
