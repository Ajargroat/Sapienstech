<?php

namespace Tests\Unit;

use App\Support\ThemeTokens;
use PHPUnit\Framework\TestCase;

class ThemeTokensTest extends TestCase
{
    /** The four primitives every tenant is expected to provide. */
    private function primitives(array $overrides = []): array
    {
        return ThemeTokens::resolve([
            'colors' => array_merge([
                'primary'    => '#06B6D4',
                'secondary'  => '#A855F7',
                'background' => '#000000',
                'surface'    => '#111111',
                'surface_alt'=> '#1A1A1A',
                'text'       => '#FFFFFF',
            ], $overrides),
        ]);
    }

    public function test_derived_colors_are_filled_from_the_primitives(): void
    {
        $theme = $this->primitives();

        foreach (['primary_hover', 'border', 'glass', 'text_muted', 'text_subtle', 'on_primary'] as $token) {
            $this->assertNotNull($theme['colors'][$token], "{$token} should have been derived");
        }
    }

    /**
     * The regression this whole change exists to fix: the old `presets` array
     * was never merged into `colors`, so picking a preset changed nothing.
     */
    public function test_an_explicit_value_beats_the_derivation(): void
    {
        $theme = $this->primitives(['primary_hover' => '#123456']);

        $this->assertSame('#123456', $theme['colors']['primary_hover']);
    }

    public function test_muted_text_is_derived_from_text_and_background_not_hardcoded(): void
    {
        $dark  = $this->primitives();
        $light = $this->primitives(['background' => '#FFFFFF', 'text' => '#111827']);

        $this->assertStringContainsString('#FFFFFF', $dark['colors']['text_muted']);
        $this->assertStringContainsString('#111827', $light['colors']['text_muted']);
    }

    public function test_every_declared_var_is_emitted_when_the_group_is_present(): void
    {
        $theme = $this->primitives();

        // These three existed in config but were silently dropped by the
        // hand-written partial.
        foreach (['radius-2xl', 'radius-sidebar', 'stagger-ms'] as $var) {
            $this->assertArrayHasKey($var, $theme['vars'], "{$var} must reach the browser");
        }
    }

    public function test_no_var_is_emitted_as_null_or_empty(): void
    {
        foreach ($this->primitives()['vars'] as $name => $value) {
            $this->assertNotSame('', $value, "--{$name} resolved to an empty value");
            $this->assertNotNull($value, "--{$name} resolved to null");
        }
    }

    public function test_radius_scale_regenerates_the_whole_shape_group(): void
    {
        $sharp = ThemeTokens::resolve(['colors' => ['primary' => '#000'], 'shape' => ['radius_scale' => 'sharp']]);
        $round = ThemeTokens::resolve(['colors' => ['primary' => '#000'], 'shape' => ['radius_scale' => 'round']]);

        $this->assertSame('2px', $sharp['vars']['radius-card']);
        $this->assertSame('38px', $round['vars']['radius-card']);
        $this->assertSame('0px', $sharp['vars']['radius-sm']);
    }

    public function test_an_explicit_radius_survives_a_radius_scale(): void
    {
        $theme = ThemeTokens::resolve([
            'shape' => ['radius_scale' => 'sharp', 'card_radius' => '99px'],
        ]);

        $this->assertSame('99px', $theme['vars']['radius-card']);
        $this->assertSame('0px', $theme['vars']['radius-sm']);
    }

    public function test_density_scales_the_spacing_group(): void
    {
        $compact  = ThemeTokens::resolve(['scale' => ['density' => 'compact']]);
        $spacious = ThemeTokens::resolve(['scale' => ['density' => 'spacious']]);

        // card_padding's baseline is 2rem.
        $this->assertSame('1.7rem', $compact['vars']['card-padding']);
        $this->assertSame('2.36rem', $spacious['vars']['card-padding']);
    }

    public function test_container_width_selects_the_container(): void
    {
        $narrow = ThemeTokens::resolve(['scale' => ['container_width' => 'narrow']]);

        $this->assertSame('68rem', $narrow['vars']['container-max']);
    }

    public function test_surface_material_drives_the_legacy_blur_and_opacity(): void
    {
        $flat = ThemeTokens::resolve(['surface' => ['material' => 'flat']]);

        $this->assertSame('0px', $flat['vars']['backdrop-blur']);
        $this->assertSame('1', $flat['vars']['glass-opacity']);
    }

    public function test_elevation_changes_the_shadow_and_default_is_not_opaque(): void
    {
        $flat = ThemeTokens::resolve(['depth' => ['elevation' => 'flat']]);
        $soft = ThemeTokens::resolve(['depth' => ['elevation' => 'soft']]);

        $this->assertSame('none', $flat['vars']['shadow']);

        // A shadow built from an opaque color-mix() would render as a solid
        // black slab; the alpha has to live in the template.
        $this->assertStringContainsString('transparent', $soft['vars']['shadow']);
    }

    public function test_duration_scale_retimes_reveal_and_stagger_together(): void
    {
        $slow = ThemeTokens::resolve(['motion' => ['duration_scale' => '2']]);

        $this->assertSame('1400ms', $slow['vars']['reveal-duration']);
        $this->assertSame('200', $slow['vars']['stagger-ms']);
    }

    public function test_brand_gradient_is_derived_from_the_palette(): void
    {
        $theme = $this->primitives(['primary' => '#FF0000', 'secondary' => '#00FF00']);

        $this->assertStringContainsString('#FF0000', $theme['vars']['brand-gradient']);
        $this->assertStringContainsString('#00FF00', $theme['vars']['brand-gradient']);
    }

    // =========================================================================
    // Contrast helpers
    // =========================================================================

    public function test_readable_on_picks_black_for_bright_backgrounds(): void
    {
        $this->assertSame('#000000', ThemeTokens::readableOn('#FFFFFF'));
        $this->assertSame('#000000', ThemeTokens::readableOn('#FBBF24'));
    }

    public function test_readable_on_picks_white_for_dark_backgrounds(): void
    {
        $this->assertSame('#FFFFFF', ThemeTokens::readableOn('#000000'));
        $this->assertSame('#FFFFFF', ThemeTokens::readableOn('#1E3A5F'));
    }

    /** rgba() and color-mix() cannot be evaluated without a browser. */
    public function test_readable_on_falls_back_for_non_hex_input(): void
    {
        $this->assertSame('#000000', ThemeTokens::readableOn('rgba(0,0,0,.5)'));
        $this->assertSame('#FFFFFF', ThemeTokens::readableOn(null, '#FFFFFF'));
    }

    public function test_contrast_ratio_matches_known_wcag_values(): void
    {
        $this->assertSame(21.0, ThemeTokens::contrastRatio('#000000', '#FFFFFF'));
        $this->assertSame(1.0, ThemeTokens::contrastRatio('#FFFFFF', '#FFFFFF'));
        $this->assertNull(ThemeTokens::contrastRatio('nope', '#FFFFFF'));
    }

    public function test_hex_to_rgb_accepts_shorthand_and_longhand(): void
    {
        $this->assertSame([255, 255, 255], ThemeTokens::hexToRgb('#fff'));
        $this->assertSame([255, 255, 255], ThemeTokens::hexToRgb('#FFFFFF'));
        $this->assertNull(ThemeTokens::hexToRgb('#ff'));
    }
}
