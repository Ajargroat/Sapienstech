<?php

namespace Tests\Unit;

use App\Support\Merge;
use App\Support\ThemeFonts;
use App\Support\ThemeTokens;
use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;

/**
 * Pins the static demo under public/demo/ to the theme engine.
 *
 * The demo is a projection of the config: theme.css must be the verbatim
 * ThemeTokens::resolve() output for the demo palette, and the pages must consume
 * tokens rather than literals. Plain TestCase — no app boot, the idiom of
 * ThemeTokensTest — because both the resolver and the files are self-contained.
 */
class DemoThemeTest extends TestCase
{
    /** The six primitives the demo palette overrides on the config baseline. */
    private const PRIMITIVES = [
        'primary' => '#2F5D62',
        'secondary' => '#5C6B73',
        'background' => '#F5F6F4',
        'surface' => '#FFFFFF',
        'surface_alt' => '#E9EBE7',
        'text' => '#14181A',
    ];

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Resolve the demo palette exactly as the generator probe in PROPOSE.md §1 does. */
    private function demoTheme(): array
    {
        $base = require $this->root().'/config/theme.php';
        $t = $base['theme'];
        $t['colors'] = array_merge($t['colors'], self::PRIMITIVES);
        $t['shape']['radius_scale'] = 'slight';
        $t['default_scheme'] = 'light';
        $t['schemes'] = ['light' => []];

        return ThemeTokens::resolve($t);
    }

    /** @return array<string,string> the `--name => value` pairs declared in theme.css's :root */
    private function declaredTokens(string $css): array
    {
        $this->assertSame(1, preg_match('/:root\s*\{(.*?)\}/s', $css, $block), 'theme.css has no :root block');

        preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $block[1], $pairs, PREG_SET_ORDER);

        $out = [];
        foreach ($pairs as $pair) {
            $out[$pair[1]] = trim($pair[2]);
        }

        return $out;
    }

    public function test_the_demo_stylesheet_matches_the_engine_output(): void
    {
        $theme = $this->demoTheme();
        $declared = $this->declaredTokens(file_get_contents($this->root().'/public/demo/theme.css'));

        // The partial's own recipe (theme-vars.blade.php:18): value tokens minus
        // the font keys, plus the validated font stacks ThemeFonts emits.
        $expected = array_diff_key($theme['vars'], ThemeFonts::vars([])) + ThemeFonts::vars($theme['typography']);

        // The demo is a FROZEN artifact: it declares the 115 properties the
        // engine emitted for its palette on the day it was generated. New
        // engine tokens land in the baseline, not in the demo (Q1=A), so this
        // pins the artifact's own size rather than a moving count.
        $this->assertCount(115, $declared, 'theme.css should declare its frozen 115 properties');

        // Every property the demo declares must be one the engine emits, with
        // the same value — the demo may not drift from the engine, but the
        // engine may grow new tokens the frozen demo does not carry.
        foreach ($declared as $name => $value) {
            $this->assertArrayHasKey($name, $expected, "--{$name} is declared in theme.css but the engine never emits it");
            $this->assertSame($expected[$name], $value, "--{$name} drifted from the engine output");
        }

        // The six literals are the whole palette contract.
        foreach (self::PRIMITIVES as $role => $hex) {
            $this->assertSame($hex, $declared['c-'.str_replace('_', '-', $role)], "--c-{$role} must be {$hex}");
        }
    }

    public function test_the_demo_palette_passes_the_tenant_contrast_gate(): void
    {
        $vars = $this->demoTheme()['vars'];

        $pairs = [
            ['c-text', 'c-background'],
            ['c-text', 'c-surface'],
            ['c-on-primary', 'c-primary'],
        ];

        foreach ($pairs as [$fg, $bg]) {
            $ratio = ThemeTokens::contrastRatio($vars[$fg], $vars[$bg]);
            $this->assertNotNull($ratio, "--{$fg} on --{$bg} is not a measurable hex pair");
            $this->assertGreaterThanOrEqual(4.5, $ratio, "--{$fg} on --{$bg} fails the MIN_TEXT_CONTRAST gate");
        }
    }

    public function test_the_demo_pages_declare_no_literal_colours(): void
    {
        foreach (['index.html', 'sign-in.html'] as $file) {
            $path = $this->root().'/public/demo/'.$file;
            $html = (string) file_get_contents($path);
            $visible = preg_replace('/<!--.*?-->/s', '', $html);

            $this->assertStringContainsString('theme.css', $html, "{$file} must load the token layer");
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $visible, "{$file} hardcodes a hex colour");
            $this->assertDoesNotMatchRegularExpression('/\brgba?\(/i', $visible, "{$file} hardcodes an rgb()/rgba() colour");
        }
    }

    public function test_the_demo_stylesheet_declares_no_literal_colours(): void
    {
        $css = (string) file_get_contents($this->root().'/public/demo/demo.css');

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $css, 'demo.css hardcodes a hex colour');
        $this->assertDoesNotMatchRegularExpression('/\b(?:rgba?|hsla?)\(/i', $css, 'demo.css hardcodes an rgb()/hsl() colour');
    }

    /** Resolve the tenant config through the app's own layer order (no app boot). */
    private function tenantConfig(): array
    {
        $config = require $this->root().'/config/theme.php';
        $tenant = require $this->root().'/config/tenants/drkafimentoring.php';

        // Mirrors SiteConfig::build(): baseline, then the archetype the tenant
        // names, then the tenant file, then ThemeTokens::resolve.
        $archetype = $tenant['theme']['archetype'] ?? $config['theme']['archetype'];

        $config = Merge::structural($config, require $this->root().'/config/archetypes/'.$archetype.'.php');
        $config = Merge::structural($config, $tenant);
        $config['theme'] = ThemeTokens::resolve($config['theme']);

        return $config;
    }

    public function test_the_tenant_config_keeps_the_accepted_copy_and_section_order(): void
    {
        $config = $this->tenantConfig();
        $theme = $config['theme'];
        $landing = $config['public']['landing'];

        // The look is no longer the demo's: the tenant names the rich
        // archetype instead of mirroring the frozen demo's tokens (Q1=A).
        $this->assertSame('dark_luminous', $theme['archetype']);

        // What the demo DID contribute — the five sections and the real copy —
        // is still the tenant's.
        $this->assertSame(['hero', 'advisor', 'process', 'services', 'cta'], $landing['sections']);
        $this->assertSame('علیرضا کافی', $landing['advisor']['name']);
        $this->assertCount(1, $landing['advisor']['stats'], 'only the one real fact may be stated');
        $this->assertSame('۴', $landing['advisor']['stats'][0]['value']);

        // And the theme resolves to a complete palette rather than a partial one.
        foreach (['c-primary', 'c-background', 'c-text', 'c-surface'] as $var) {
            $this->assertArrayHasKey($var, $theme['vars']);
        }
    }

    public function test_the_dark_luminous_tenant_resolves_a_rich_identity(): void
    {
        $theme = $this->tenantConfig()['theme'];

        $this->assertSame('dark_luminous', $theme['archetype']);
        $this->assertSame('aurora', $theme['background']['mode']);
        $this->assertSame('glass', $theme['surface']['material']);
        $this->assertSame('neon', $theme['depth']['elevation']);
        $this->assertNotEmpty($theme['background']['glow_blobs'], 'the rich look needs its glows');
        $this->assertTrue($theme['motion']['tilt']);
        $this->assertTrue($theme['effects']['enable_animations']);
    }

    public function test_the_dark_luminous_tenant_passes_the_contrast_gate(): void
    {
        $theme = $this->tenantConfig()['theme'];

        $this->assertSame('dark', $theme['default_scheme']);
        $this->assertSame(['dark', 'light'], $theme['scheme_names'], 'the toggle needs two real destinations');

        $schemes = ThemeTokens::schemeVars($theme);

        foreach (['dark', 'light'] as $name) {
            $this->assertArrayHasKey($name, $schemes, "the {$name} scheme resolved to nothing");

            foreach ([['c-text', 'c-background'], ['c-text', 'c-surface'], ['c-on-primary', 'c-primary']] as [$fg, $bg]) {
                $ratio = ThemeTokens::contrastRatio($schemes[$name][$fg], $schemes[$name][$bg]);

                $this->assertNotNull($ratio, "{$name}: --{$fg} on --{$bg} is not a measurable hex pair");
                $this->assertGreaterThanOrEqual(4.5, $ratio, "{$name}: --{$fg} on --{$bg} fails the contrast gate");
            }
        }
    }

    public function test_the_new_levers_are_declared_in_the_baseline(): void
    {
        $baseline = require $this->root().'/config/theme.php';

        foreach ([
            'theme.background.spotlight',
            'theme.background.glow_animation',
            'theme.background.glow_duration',
            'theme.effects.panel_shadow',
            'theme.portal.glow_size',
            'theme.portal.glow_blur',
            'theme.portal.glow_opacity',
            'theme.portal.ground',
        ] as $path) {
            $this->assertTrue(Arr::has($baseline, $path), "{$path} must exist in the baseline or tenant:validate rejects it");
        }
    }

    public function test_the_demo_sign_in_uses_the_login_card_tokens(): void
    {
        $html = (string) file_get_contents($this->root().'/public/demo/sign-in.html');

        foreach (['--radius-card', '--card-padding', '--c-surface', '--c-border'] as $token) {
            $this->assertStringContainsString($token, $html, "sign-in.html must style the card with {$token}");
        }

        $base = require $this->root().'/config/theme.php';
        $maxWidth = $base['public']['login']['card']['max_width'];

        $this->assertStringContainsString($maxWidth, $html, "the card max-width must track config theme.public.login.card.max_width ({$maxWidth})");
    }
}
