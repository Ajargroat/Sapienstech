<?php

namespace Tests\Unit;

use App\Support\LandingFeatures;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * The landing feature manifest pins itself. No app boots here: the provider,
 * routes, limiter and config are read as source text, so every check fails
 * loudly the moment the manifest and the code it references drift apart.
 */
class LandingFeaturesTest extends TestCase
{
    public function test_manifest_registers_exactly_today_three_features(): void
    {
        // Pinned today: blog (class B) + join and consultation (class C). A new feature appends
        // one manifest entry, one docs/landing-data.md audit row AND updates
        // this count — the registered features never grow silently.
        $this->assertSame(['blog', 'join', 'consultation'], array_keys(LandingFeatures::manifest()));
    }

    public function test_every_feature_declares_its_data_life_class(): void
    {
        foreach (LandingFeatures::manifest() as $id => $feature) {
            $this->assertArrayHasKey('class', $feature, $id);
            $this->assertContains($feature['class'], ['A', 'B', 'C'], $id);
        }
    }

    public function test_class_b_features_declare_views_the_composer_serves(): void
    {
        $membership = $this->composerMembership();

        $this->assertNotEmpty($membership, 'the landing composer membership was not found');

        foreach (LandingFeatures::composers() as $id => $feature) {
            $this->assertArrayHasKey('views', $feature, $id);
            $this->assertNotEmpty($feature['views'], $id.' feeds no view — a class B entry must declare one');

            foreach ($feature['views'] as $pattern) {
                $served = false;

                foreach ($membership as $member) {
                    if ($member === $pattern || Str::is($member, $pattern)) {
                        $served = true;
                        break;
                    }
                }

                $this->assertTrue($served, $id.' view pattern "'.$pattern.'" is not served by the composer');
            }
        }
    }

    public function test_class_b_entries_name_real_models_shapers_and_routes(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        $this->assertGreaterThan(0, count(LandingFeatures::composers()), 'no class B features are pinned');

        foreach (LandingFeatures::composers() as $id => $feature) {
            $this->assertArrayHasKey('model', $feature, $id);
            $this->assertTrue(class_exists($feature['model']), $id.' names a model that does not exist');
            $this->assertTrue(method_exists($feature['model'], 'scopePublished'), $id.' model has no published() scope');
            $this->assertTrue(method_exists($feature['model'], 'toLandingItem'), $id.' model has no toLandingItem() shaper');
            $this->assertTrue(is_callable($feature['provider']), $id.' provider is not callable');
            $this->assertArrayHasKey('section', $feature, $id);
            $this->assertArrayHasKey('drop_section_when_empty', $feature, $id);
            $this->assertArrayHasKey('see_all_route', $feature, $id);
            $this->assertMatchesRegularExpression(
                '/->name\('.preg_quote("'".$feature['see_all_route']."'", '/').'\)/',
                $routes,
                $id.' see_all route does not exist in routes/web.php'
            );
        }
    }

    public function test_class_c_entries_name_their_submission_contract_keys(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root.'/routes/web.php');
        $provider = (string) file_get_contents($root.'/app/Providers/AppServiceProvider.php');
        $theme = (string) file_get_contents($root.'/config/theme.php');

        $seenClassC = false;

        foreach (LandingFeatures::manifest() as $id => $feature) {
            if ($feature['class'] !== 'C') {
                continue;
            }

            $seenClassC = true;

            $this->assertSame([], $feature['views'], $id.' feeds no composer — declared views must stay empty');
            $this->assertArrayHasKey('route', $feature, $id);
            $this->assertArrayHasKey('throttle', $feature, $id);
            $this->assertArrayHasKey('flag', $feature, $id);

            $this->assertMatchesRegularExpression(
                '/->name\('.preg_quote("'".$feature['route']."'", '/').'\)/',
                $routes,
                $id.' route does not exist in routes/web.php'
            );
            $this->assertStringContainsString(
                'throttle:'.$feature['throttle'],
                $routes,
                $id.' route is not throttled by its dedicated limiter'
            );
            $this->assertStringContainsString(
                "RateLimiter::for('".$feature['throttle']."'",
                $provider,
                $id.' dedicated RateLimiter missing from AppServiceProvider'
            );
            $this->assertStringContainsString(
                "'".Str::after($feature['flag'], 'features.')."' =>",
                $theme,
                $id.' feature flag missing from config/theme.php'
            );
        }

        $this->assertTrue($seenClassC, 'no class C feature is pinned');
    }

    public function test_the_composer_consumes_the_manifest_and_is_not_re_keyed(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Providers/ViewServiceProvider.php');

        $this->assertStringContainsString(
            'LandingFeatures::composers()',
            $source,
            'the composer must obtain class-B features through the manifest seam'
        );
        $this->assertStringNotContainsString(
            "getName() === 'public.landing'",
            $source,
            'the composer must not re-key class-B injection to one hard-coded view name'
        );
    }

    /**
     * The composer's membership patterns, read from the provider source: the
     * landing composer is the one whose list leads with the legacy view name.
     *
     * @return list<string>
     */
    private function composerMembership(): array
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Providers/ViewServiceProvider.php');

        $this->assertMatchesRegularExpression('/View::composer\(\[\'public\.landing\',/', $source);

        preg_match('/View::composer\(\[\'public\.landing\'.*?\]/s', $source, $block);
        preg_match_all('/\'([^\']+)\'/', $block[0] ?? '', $names);

        return $names[1] ?? [];
    }
}
