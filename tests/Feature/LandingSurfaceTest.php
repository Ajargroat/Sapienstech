<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\LandingSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LandingSurface fails closed. Every test here is really the same question
 * asked a different way: can a misconfigured surface ever take down or hijack
 * a live tenant's home page? The answer must always be no.
 */
class LandingSurfaceTest extends TestCase
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

    private function surface(): LandingSurface
    {
        return new LandingSurface;
    }

    private function tenant(string $slug = 'bespoke-test'): Tenant
    {
        return Tenant::factory()->create(['slug' => $slug]);
    }

    /** Create resources/landings/{slug}/ with the given files. */
    private function makeSurface(string $slug, ?string $blade = '<html></html>', ?array $meta = ['enabled' => true]): string
    {
        $dir = resource_path('landings/'.$slug);
        @mkdir($dir, 0777, true);
        $this->createdDirs[] = $dir;

        if ($blade !== null) {
            file_put_contents($dir.'/landing.blade.php', $blade);
        }

        if ($meta !== null) {
            file_put_contents($dir.'/meta.json', json_encode($meta));
        }

        return $dir;
    }

    // ---------------------------------------------------------------- happy path

    public function test_a_published_surface_resolves_to_its_view(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug);

        $this->assertSame(
            $tenant->slug.'.landing',
            $this->surface()->for($tenant),
        );
    }

    // -------------------------------------------------------------- fails closed

    public function test_no_folder_means_the_legacy_landing(): void
    {
        $this->assertNull($this->surface()->for($this->tenant('no-folder-here')));
    }

    public function test_a_folder_without_a_manifest_is_not_served(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug, meta: null);

        // Unpublished must mean legacy, never "publish everything".
        $this->assertNull($this->surface()->for($tenant));
    }

    public function test_an_explicitly_disabled_surface_is_not_served(): void
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

    public function test_a_manifest_without_the_view_is_not_served(): void
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

        // The point: no exception escapes. The site keeps rendering.
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

    // ---------------------------------------------------------------- preview mode

    public function test_force_serves_a_draft_but_still_validates_the_slug(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug, meta: ['enabled' => false]);

        // Preview can see an unpublished page…
        $this->assertSame(
            $tenant->slug.'.landing',
            $this->surface()->for($tenant, force: true),
        );

        // …but preview never bypasses the slug rule.
        $evil = Tenant::factory()->make(['slug' => '../escape']);
        $this->assertNull($this->surface()->for($evil, force: true));
    }

    public function test_force_still_requires_the_view_file(): void
    {
        $tenant = $this->tenant();
        $this->makeSurface($tenant->slug, blade: null, meta: ['enabled' => true]);

        $this->assertNull($this->surface()->for($tenant, force: true));
    }

    // ------------------------------------------------------------- end to end

    public function test_the_home_route_serves_the_bespoke_page_when_published(): void
    {
        $tenant = $this->tenant();
        $host = Str::lower(Str::random(8)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        $this->makeSurface(
            $tenant->slug,
            blade: '<html><body>BESPOKE-MARKER</body></html>',
        );

        $response = $this->get("http://{$host}/")->assertOk();

        $response->assertSee('BESPOKE-MARKER', false);
    }

    public function test_the_home_route_falls_back_to_the_legacy_landing(): void
    {
        $tenant = $this->tenant();
        $host = Str::lower(Str::random(8)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        // A surface exists but is not published.
        $this->makeSurface(
            $tenant->slug,
            blade: '<html><body>BESPOKE-MARKER</body></html>',
            meta: ['enabled' => false],
        );

        $response = $this->get("http://{$host}/")->assertOk();

        $response->assertDontSee('BESPOKE-MARKER', false);
        // The shared skeleton's token sheet is what proves the legacy page ran.
        $response->assertSee('--c-primary:', false);
    }

    public function test_a_bespoke_page_still_receives_the_tenant_composer_data(): void
    {
        $tenant = $this->tenant();
        $host = Str::lower(Str::random(8)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        // A page that omits theme-vars and reads the composer's own variables:
        // if the composer did not run, this Blade file would blow up with an
        // undefined-variable error and the request would not be a 200.
        $this->makeSurface(
            $tenant->slug,
            blade: '<html><body>{{ $tenant["name"] }}|{{ count($public["landing"]["sections"]) }}|{{ $theme["archetype"] ?? "none" }}</body></html>',
        );

        $response = $this->get("http://{$host}/")->assertOk();
        $body = $response->getContent();

        // The composer always supplies a non-empty tenant name and the
        // landing section list; both come from site(), not from the view.
        $this->assertMatchesRegularExpression('/^<html><body>[^|]+\|\d+\|/', $body);
        $this->assertDoesNotMatchRegularExpression('/undefined/i', $body);
    }
}
