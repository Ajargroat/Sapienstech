<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Resolves the raw `theme` config block into the final token tree plus the flat
 * CSS custom-property map that partials/theme-vars.blade.php emits.
 *
 * Layer precedence lives in SiteConfig (baseline -> archetype -> tenant file ->
 * database), so this class has exactly one job: fill in every token the layers
 * left `null` by deriving it from the primitives that *were* set. That is what
 * lets a tenant file with six colors produce a coherent palette instead of a
 * palette with holes in it.
 *
 * Every derive method takes the whole tree by reference, because several
 * derivations legitimately cross group boundaries (surface material drives the
 * legacy effects blur/opacity; scale density drives spacing; motion duration
 * drives the landing timings).
 */
final class ThemeTokens
{
    /**
     * `group.key` path => CSS custom property name (without the `--`).
     *
     * The names are historical — `text_muted` publishes as `--c-muted`,
     * `font_family` as `--font-body` — so the map is explicit rather than
     * auto-derived. A token absent from this map never reaches the browser,
     * which is how `radius_2xl`, `sidebar_radius` and `stagger_ms` went missing
     * when the partial listed them by hand.
     */
    public const VARS = [
        // ---- colors -------------------------------------------------------
        'colors.primary'              => 'c-primary',
        'colors.primary_hover'        => 'c-primary-hover',
        'colors.secondary'            => 'c-secondary',
        'colors.secondary_hover'      => 'c-secondary-hover',
        'colors.background'           => 'c-background',
        'colors.surface'              => 'c-surface',
        'colors.surface_alt'          => 'c-surface-alt',
        'colors.surface_elevated'     => 'c-surface-elevated',
        'colors.text'                 => 'c-text',
        'colors.text_muted'           => 'c-muted',
        'colors.text_subtle'          => 'c-subtle',
        'colors.border'               => 'c-border',
        'colors.border_strong'        => 'c-border-strong',
        'colors.success'              => 'c-success',
        'colors.info'                 => 'c-info',
        'colors.warning'              => 'c-warning',
        'colors.danger'               => 'c-danger',
        'colors.primary_soft'         => 'c-primary-soft',
        'colors.secondary_soft'       => 'c-secondary-soft',
        'colors.accent_blue'          => 'c-accent-blue',
        'colors.accent_emerald'       => 'c-accent-emerald',
        'colors.accent_orange'        => 'c-accent-orange',
        'colors.accent_teal'          => 'c-accent-teal',
        'colors.accent_red'           => 'c-accent-red',
        'colors.glass'                => 'c-glass',
        'colors.glass_hover'          => 'c-glass-hover',
        'colors.glass_border'         => 'c-glass-border',
        'colors.on_primary'           => 'c-on-primary',
        'colors.on_surface'           => 'c-on-surface',
        'colors.selection_background' => 'c-selection-bg',
        'colors.selection_text'       => 'c-selection-text',

        // ---- typography ---------------------------------------------------
        'typography.font_family'            => 'font-body',
        'typography.font_heading'           => 'font-heading',
        'typography.font_accent'            => 'font-accent',
        'typography.font_mono'              => 'font-mono',
        'typography.body_size'              => 'body-size',
        'typography.body_weight'            => 'body-weight',
        'typography.heading_weight'         => 'heading-weight',
        'typography.heading_transform'      => 'heading-transform',
        'typography.heading_letter_spacing' => 'heading-letter-spacing',
        'typography.letter_spacing'         => 'letter-spacing',
        'typography.line_height'            => 'line-height',
        'typography.measure'                => 'measure',
        'typography.h1_size'                => 'h1-size',
        'typography.h2_size'                => 'h2-size',
        'typography.h3_size'                => 'h3-size',
        'typography.hero_line_height'       => 'hero-line-height',

        // ---- shape --------------------------------------------------------
        'shape.radius_sm'      => 'radius-sm',
        'shape.radius_md'      => 'radius-md',
        'shape.radius_lg'      => 'radius-lg',
        'shape.radius_xl'      => 'radius-xl',
        'shape.radius_2xl'     => 'radius-2xl',
        'shape.button_radius'  => 'radius-button',
        'shape.input_radius'   => 'radius-input',
        'shape.card_radius'    => 'radius-card',
        'shape.sidebar_radius' => 'radius-sidebar',
        'shape.border_width'   => 'surface-border-w',

        // ---- layout -------------------------------------------------------
        'layout.sidebar_width'     => 'sidebar-width',
        'layout.content_max_width' => 'content-max',
        'layout.content_padding'   => 'content-padding',
        'layout.sidebar_top'       => 'sidebar-top',
        'layout.sidebar_bottom'    => 'sidebar-bottom',
        'layout.sidebar_offset'    => 'sidebar-offset',
        'layout.card_gap'          => 'card-gap',
        'layout.topnav_height'     => 'topnav-height',

        // ---- spacing (density-scaled, see spacing()) ----------------------
        'spacing.section_gap'       => 'section-gap',
        'spacing.container_max'     => 'container-max',
        'spacing.container_padding' => 'container-padding',
        'spacing.grid_gap'          => 'grid-gap',
        'spacing.card_padding'      => 'card-padding',

        // ---- landing ------------------------------------------------------
        'landing.nav_height'      => 'nav-height',
        'landing.hero_min_height' => 'hero-min-height',
        'landing.glow_size'       => 'glow-size',
        'landing.glow_blur'       => 'glow-blur',
        'landing.glow_opacity'    => 'glow-opacity',
        'landing.reveal_duration' => 'reveal-duration',
        'landing.reveal_offset'   => 'reveal-offset',
        'landing.float_distance'  => 'float-distance',
        'landing.float_duration'  => 'float-duration',
        'landing.stagger_ms'      => 'stagger-ms',

        // ---- effects / depth ----------------------------------------------
        'effects.shadow'             => 'shadow',
        'effects.card_shadow'        => 'card-shadow',
        'effects.sidebar_shadow'     => 'sidebar-shadow',
        'effects.backdrop_blur'      => 'backdrop-blur',
        'effects.glass_opacity'      => 'glass-opacity',
        'effects.hover_lift'         => 'hover-lift',
        'effects.animation_duration' => 'animation-duration',

        // ---- gradients ----------------------------------------------------
        'gradients.brand'       => 'brand-gradient',
        'gradients.page_glow_1' => 'page-glow-1',
        'gradients.page_glow_2' => 'page-glow-2',

        // ---- surface material ---------------------------------------------
        'surface.noise'        => 'surface-noise',
        'surface.specular'     => 'surface-specular',
        'surface.inner_border' => 'surface-inner-border',

        // ---- motion -------------------------------------------------------
        'motion.easing'        => 'ease',
        'motion.duration_scale'=> 'duration-scale',
        'motion.marquee_speed' => 'marquee-duration',

        // ---- buttons --------------------------------------------------------
        'buttons.weight'    => 'btn-weight',
        'buttons.transform' => 'btn-transform',

        // ---- background -----------------------------------------------------
        'background.grid_size'      => 'grid-size',
        'background.gradient_angle' => 'gradient-angle',

        // ---- scale ----------------------------------------------------------
        'scale.section_rhythm' => 'section-rhythm',
    ];

    /**
     * Elevation name => [resting, card, sidebar] shadow templates.
     *
     * %shadowNN% is the (optionally brand-tinted) shadow colour at NN% alpha.
     * The alpha has to live in the template: a shadow built from an opaque
     * color-mix() would render as a solid black slab instead of a soft falloff.
     */
    private const ELEVATION = [
        'flat'    => ['none', 'none', 'none'],
        'ambient' => ['0 2px 24px %shadow12%', '0 1px 12px %shadow12%', '0 2px 24px %shadow12%'],
        'soft'    => ['0 25px 60px %shadow35%', '0 15px 40px %shadow18%', '0 25px 60px %shadow45%'],
        'medium'  => ['0 28px 70px %shadow45%', '0 18px 46px %shadow25%', '0 28px 70px %shadow50%'],
        'heavy'   => ['0 40px 110px %shadow55%', '0 26px 64px %shadow35%', '0 44px 120px %shadow65%'],
        'neon'    => ['0 0 42px %primary40%', '0 0 26px %primary30%', '0 0 54px %primary40%'],
        'brutal'  => ['6px 6px 0 %primary%', '4px 4px 0 %primary%', '6px 6px 0 %primary%'],
    ];

    /** radius_scale name => [sm, md, lg, xl, 2xl, button, input, card, sidebar]. */
    private const RADIUS_SCALES = [
        'sharp'  => ['0px', '0px', '1px', '2px', '2px', '2px', '2px', '2px', '0px'],
        'slight' => ['4px', '6px', '8px', '10px', '12px', '8px', '6px', '10px', '12px'],
        'soft'   => ['10px', '14px', '20px', '24px', '32px', '14px', '14px', '24px', '24px'],
        'round'  => ['16px', '22px', '30px', '38px', '48px', '999px', '22px', '38px', '38px'],
        'pill'   => ['999px', '999px', '999px', '999px', '999px', '999px', '999px', '32px', '32px'],
    ];

    /** density name => multiplier applied to the spacing group. */
    private const DENSITIES = ['compact' => 0.85, 'normal' => 1.0, 'spacious' => 1.18];

    /** container_width name => max-width value. */
    private const CONTAINERS = [
        'narrow' => '68rem', 'content' => '80rem', 'wide' => '92rem', 'full' => '100%',
    ];

    /**
     * @param  array  $t  the raw `theme` block
     * @return array  the same tree with every token resolved, plus `vars`
     */
    public static function resolve(array $t): array
    {
        // Order matters: later groups derive from the finished palette.
        self::colors($t);
        self::typography($t);
        self::scaleAndSpacing($t);
        self::shape($t);
        self::effects($t);
        self::surface($t);
        self::gradients($t);
        self::motion($t);
        self::background($t);
        self::decoration($t);
        self::buttons($t);
        self::brand($t);
        self::i18n($t);
        self::accessibility($t);

        $t['schemes'] ??= [];
        $t['vars'] = self::toVars($t);

        return $t;
    }

    /** Flatten the token tree into the CSS custom-property map. */
    public static function toVars(array $t): array
    {
        $vars = [];

        foreach (self::VARS as $path => $name) {
            $value = Arr::get($t, $path);

            // Booleans and arrays are consumed by Blade directly, not as CSS.
            if ($value === null || is_bool($value) || is_array($value)) {
                continue;
            }

            $vars[$name] = $value;
        }

        return $vars;
    }

    /**
     * Fill keys that are missing *or* explicitly null.
     *
     * `array +=` is not enough: the baseline deliberately writes `null` for
     * every derived token, and `+=` leaves an existing null key untouched.
     */
    private static function fill(array $target, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (($target[$key] ?? null) === null) {
                $target[$key] = $value;
            }
        }

        return $target;
    }

    // =========================================================================
    // Derivation
    // =========================================================================

    /**
     * Fills every unset color from the primitives (primary, secondary,
     * background, text, surface_alt) using color-mix(), so hovers, borders,
     * glass tints and muted text stay correct in light *and* dark schemes
     * without a tenant restating them.
     */
    private static function colors(array &$t): void
    {
        $c   = $t['colors'] ?? [];
        $p   = $c['primary']     ?? '#06B6D4';
        $s   = $c['secondary']   ?? '#A855F7';
        $bg  = $c['background']  ?? '#000000';
        $ink = $c['text']        ?? '#FFFFFF';
        $alt = $c['surface_alt'] ?? '#1A1A1A';

        $derived = [
            'primary_hover'        => "color-mix(in oklab, {$p} 85%, black)",
            'secondary_hover'      => "color-mix(in oklab, {$s} 85%, black)",
            'primary_soft'         => "color-mix(in oklab, {$p} 12%, transparent)",
            'secondary_soft'       => "color-mix(in oklab, {$s} 12%, transparent)",
            'border'               => "color-mix(in oklab, {$ink} 8%, transparent)",
            'border_strong'        => "color-mix(in oklab, {$ink} 18%, transparent)",
            'glass'                => "color-mix(in oklab, {$ink} 5%, transparent)",
            'glass_hover'          => "color-mix(in oklab, {$ink} 10%, transparent)",
            'glass_border'         => "color-mix(in oklab, {$ink} 10%, transparent)",
            'text_muted'           => "color-mix(in oklab, {$ink} 62%, {$bg})",
            'text_subtle'          => "color-mix(in oklab, {$ink} 42%, {$bg})",
            'surface_elevated'     => "color-mix(in oklab, {$alt} 88%, {$ink})",
            'on_primary'           => self::readableOn($p),
            'on_surface'           => $ink,
            'selection_background' => $p,
            'selection_text'       => self::readableOn($p),
        ];

        foreach ($derived as $key => $value) {
            if (($c[$key] ?? null) === null) {
                $c[$key] = $value;
            }
        }

        // Write the primitives back with their defaults too. Without this, a
        // theme that omits `background` derives fine here but then trips an
        // undefined-key read in effects() and gradients(), which both consume
        // the finished palette.
        foreach (['primary' => $p, 'secondary' => $s, 'background' => $bg, 'text' => $ink, 'surface_alt' => $alt] as $key => $value) {
            if (($c[$key] ?? null) === null) {
                $c[$key] = $value;
            }
        }

        $t['colors'] = $c;
    }

    private static function typography(array &$t): void
    {
        $f    = $t['typography'] ?? [];
        $body = $f['font_family'] ?? 'Vazirmatn';

        // A theme naming one font gets it everywhere; naming a heading face gets
        // it on headings without having to restate the body font.
        foreach (['font_heading' => $body, 'font_accent' => $body] as $key => $fallback) {
            if (($f[$key] ?? null) === null) {
                $f[$key] = $fallback;
            }
        }

        $f = self::fill($f, [
            'font_family'              => $body,
            'font_mono'                => 'ui-monospace, SFMono-Regular, monospace',
            'body_size'                => '15px',
            'body_weight'              => '400',
            'heading_weight'           => '800',
            'heading_transform'        => 'none',
            'heading_letter_spacing'   => '-.02em',
            'letter_spacing'           => '0',
            'line_height'              => '1.8',
            'measure'                  => '46rem',
            'h1_size'                  => 'clamp(2.75rem, 6vw, 4.5rem)',
            'h2_size'                  => 'clamp(1.875rem, 4vw, 3rem)',
            'h3_size'                  => '1.25rem',
            'hero_line_height'         => '1.2',
        ]);

        $t['typography'] = $f;
    }

    /**
     * Owns both `scale` and `spacing`: density and container_width are the
     * levers, the spacing lengths are what they resolve to.
     */
    private static function scaleAndSpacing(array &$t): void
    {
        $sc  = self::fill($t['scale'] ?? [], [
            'density'         => 'normal',
            'container_width' => 'content',
            'section_rhythm'  => '6rem',
        ]);

        $mul = self::DENSITIES[$sc['density']] ?? 1.0;

        $sp = self::fill($t['spacing'] ?? [], [
            'section_gap'       => $sc['section_rhythm'],
            'container_max'     => self::CONTAINERS[$sc['container_width']] ?? '80rem',
            'container_padding' => self::scaleLength('1.5rem', $mul),
            'grid_gap'          => self::scaleLength('2rem', $mul),
            'card_padding'      => self::scaleLength('2rem', $mul),
        ]);

        $t['scale']   = $sc;
        $t['spacing'] = $sp;
    }

    /** Multiplies a `1.5rem`-style length, keeping its unit. */
    private static function scaleLength(string $value, float $mul): string
    {
        if ($mul === 1.0 || ! preg_match('/^([\d.]+)([a-z%]+)$/', $value, $m)) {
            return $value;
        }

        $scaled = (float) $m[1] * $mul;

        return rtrim(rtrim(number_format($scaled, 3, '.', ''), '0'), '.') . $m[2];
    }

    /**
     * `radius_scale` regenerates the whole shape group at once so an archetype
     * can say "sharp" instead of restating nine lengths. Radii set explicitly
     * (non-null) always survive.
     */
    private static function shape(array &$t): void
    {
        $s     = $t['shape'] ?? [];
        $scale = $s['radius_scale'] ?? null;
        $keys  = ['radius_sm', 'radius_md', 'radius_lg', 'radius_xl', 'radius_2xl',
                  'button_radius', 'input_radius', 'card_radius', 'sidebar_radius'];

        if ($scale !== null && isset(self::RADIUS_SCALES[$scale])) {
            foreach (self::RADIUS_SCALES[$scale] as $i => $value) {
                if (($s[$keys[$i]] ?? null) === null) {
                    $s[$keys[$i]] = $value;
                }
            }
        }

        $s = self::fill($s, [
            'radius_sm'      => '10px',
            'radius_md'      => '14px',
            'radius_lg'      => '20px',
            'radius_xl'      => '24px',
            'radius_2xl'     => '32px',
            'button_radius'  => '999px',
            'input_radius'   => '14px',
            'card_radius'    => '24px',
            'sidebar_radius' => '24px',
            'border_width'   => '1px',
        ]);

        $t['shape'] = $s;
    }

    /**
     * Shadows. `shadow_tint` mixes the brand background into the black so depth
     * reads as brand-colored rather than generic.
     */
    private static function effects(array &$t): void
    {
        $e   = $t['effects'] ?? [];
        $d   = $t['depth'] ?? [];
        $c   = $t['colors'] ?? [];
        $lvl = $d['elevation'] ?? 'soft';
        $row = self::ELEVATION[$lvl] ?? self::ELEVATION['soft'];

        $tinted = $d['shadow_tint'] ?? true;

        // Tinting mixes the brand background toward black so shadows sit on the
        // page instead of reading as grey smog; untinted is plain black.
        $tint = $tinted
            ? "color-mix(in oklab, {$c['background']} 72%, black)"
            : '#000000';

        $replace = static function (string $v) use ($c, $tint): string {
            $v = str_replace(
                ['%primary40%', '%primary30%', '%primary%'],
                [
                    "color-mix(in oklab, {$c['primary']} 40%, transparent)",
                    "color-mix(in oklab, {$c['primary']} 30%, transparent)",
                    $c['primary'],
                ],
                $v
            );

            // %shadowNN% -> the tint at NN% alpha.
            return preg_replace_callback(
                '/%shadow(\d+)%/',
                static fn (array $m): string => "color-mix(in oklab, {$tint} {$m[1]}%, transparent)",
                $v
            );
        };

        $e = self::fill($e, [
            'shadow'             => $replace($row[0]),
            'card_shadow'        => $replace($row[1]),
            'sidebar_shadow'     => $replace($row[2]),
            'backdrop_blur'      => '20px',
            'glass_opacity'      => '.94',
            'hover_lift'         => '3px',
            'animation_duration' => '180ms',
            'enable_animations'  => true,
        ]);

        $d = self::fill($d, ['elevation' => $lvl, 'shadow_tint' => $tinted]);

        $t['effects'] = $e;
        $t['depth']   = $d;
    }

    /**
     * The "material" a surface is made of. Bridges to the legacy
     * effects.backdrop_blur / effects.glass_opacity tokens the existing markup
     * already reads, so six hardcoded backdrop recipes can collapse to one.
     */
    private static function surface(array &$t): void
    {
        $s        = $t['surface'] ?? [];
        $material = $s['material'] ?? 'glass';

        $presets = [
            'glass'   => ['blur' => '20px', 'opacity' => '.94', 'noise' => '0',    'specular' => 'inset 0 1px 0 rgba(255,255,255,.06)', 'inner_border' => '1px'],
            'acrylic' => ['blur' => '34px', 'opacity' => '.82', 'noise' => '.025', 'specular' => 'inset 0 1px 0 rgba(255,255,255,.12)', 'inner_border' => '1px'],
            'matte'   => ['blur' => '0px',  'opacity' => '1',   'noise' => '0',    'specular' => 'none', 'inner_border' => '0px'],
            'paper'   => ['blur' => '0px',  'opacity' => '1',   'noise' => '.035', 'specular' => 'none', 'inner_border' => '0px'],
            'flat'    => ['blur' => '0px',  'opacity' => '1',   'noise' => '0',    'specular' => 'none', 'inner_border' => '0px'],
        ];

        foreach ($presets[$material] ?? $presets['glass'] as $key => $value) {
            if (($s[$key] ?? null) === null) {
                $s[$key] = $value;
            }
        }

        $s['material'] = $material;

        $t['surface'] = $s;
        $t['effects']['backdrop_blur'] = $s['blur'];
        $t['effects']['glass_opacity'] = $s['opacity'];
    }

    /**
     * Owns `motion` and scales the legacy landing timings by duration_scale,
     * so one lever retimes reveal + stagger together.
     */
    private static function motion(array &$t): void
    {
        $m = self::fill($t['motion'] ?? [], [
            'reveal'         => 'fade-up',
            'stagger'        => 'sequential',
            'easing'         => 'cubic-bezier(.22,1,.36,1)',
            'duration_scale' => '1',
            'parallax'       => 'none',
            'tilt'           => false,
            'magnetic'       => false,
            'marquee_speed'  => '38s',
            'hover'          => 'lift',
        ]);

        $scale = max(0.0, (float) $m['duration_scale']);

        $ld = self::fill($t['landing'] ?? [], [
            'nav_height'       => '4.5rem',
            'hero_min_height'  => '100svh',
            'glow_size'        => '24rem',
            'glow_blur'        => '120px',
            'glow_opacity'     => '.10',
            'reveal_duration'  => '700ms',
            'reveal_offset'    => '40px',
            'float_distance'   => '15px',
            'float_duration'   => '6s',
            'counter_duration' => '2000',
            'stagger_ms'       => '100',
        ]);

        if ($scale !== 1.0) {
            $ld['reveal_duration'] = round((float) rtrim($ld['reveal_duration'], 'ms') * $scale) . 'ms';
            $ld['stagger_ms']      = (string) (int) round((float) $ld['stagger_ms'] * $scale);
        }

        $t['motion']  = $m;
        $t['landing'] = $ld;
    }

    /**
     * Brand gradient + page glows, derived from the finished palette. These
     * were hardcoded to a palette that no longer existed.
     */
    private static function gradients(array &$t): void
    {
        $c = $t['colors'];
        $p = $c['primary'];
        $s = $c['secondary'];

        $t['gradients'] = self::fill($t['gradients'] ?? [], [
            'brand'       => "linear-gradient(135deg, {$p} 0%, {$s} 100%)",
            'page_glow_1' => "color-mix(in oklab, {$p} 6%, transparent)",
            'page_glow_2' => "color-mix(in oklab, {$s} 6%, transparent)",
        ]);
    }

    private static function background(array &$t): void
    {
        $t['background'] = self::fill($t['background'] ?? [], [
            'mode'                => 'glow',
            'gradient_angle'      => '180deg',
            'glow_blobs'          => [],
            'grid_size'           => '48px',
            'noise'               => '0',
            'section_alternation' => 'none',
        ]);
    }

    private static function decoration(array &$t): void
    {
        $t['decoration'] = self::fill($t['decoration'] ?? [], [
            'section_divider' => 'none',
            'heading_rule'    => 'none',
            'heading_align'   => 'center',
            'accent_shapes'   => 'none',
            'quote_mark'      => 'none',
            'icon_backdrop'   => 'soft-square',
        ]);
    }

    private static function buttons(array &$t): void
    {
        $t['buttons'] = self::fill($t['buttons'] ?? [], [
            'variant'   => 'solid',
            'size'      => 'md',
            'weight'    => '600',
            'transform' => 'none',
            'icon'      => 'none',
            'hover'     => 'lift',
        ]);
    }

    private static function brand(array &$t): void
    {
        $t['brand'] = self::fill($t['brand'] ?? [], [
            'src'      => null,
            'height'   => '2rem',
            'variant'  => 'mark+word',
            'position' => 'start',
        ]);
    }

    private static function i18n(array &$t): void
    {
        $t['i18n'] = self::fill($t['i18n'] ?? [], [
            'numerals'    => 'auto',
            'calendar'    => 'gregorian',
            'date_format' => 'Y-m-d',
        ]);
    }

    private static function accessibility(array &$t): void
    {
        $t['accessibility'] = self::fill($t['accessibility'] ?? [], [
            'contrast'      => 'normal',
            'focus_ring'    => 'outline',
            'reduce_motion' => 'respect',
        ]);
    }

    // =========================================================================
    // Contrast helpers
    // =========================================================================

    /**
     * Picks black or white for text sitting on $hex by comparing WCAG contrast
     * ratios. Non-hex input (rgba(), color-mix(), …) returns $fallback, because
     * those cannot be evaluated without a browser.
     */
    public static function readableOn(?string $hex, string $fallback = '#000000'): string
    {
        $rgb = self::hexToRgb($hex);

        if ($rgb === null) {
            return $fallback;
        }

        $l = self::luminance($rgb);

        return (($l + 0.05) / 0.05) >= (1.05 / ($l + 0.05)) ? '#000000' : '#FFFFFF';
    }

    /** WCAG contrast ratio between two hex colors (1.0 – 21.0), or null. */
    public static function contrastRatio(?string $a, ?string $b): ?float
    {
        $ra = self::hexToRgb($a);
        $rb = self::hexToRgb($b);

        if ($ra === null || $rb === null) {
            return null;
        }

        $la = self::luminance($ra);
        $lb = self::luminance($rb);

        return round((max($la, $lb) + 0.05) / (min($la, $lb) + 0.05), 2);
    }

    /** @return array{int,int,int}|null */
    public static function hexToRgb(?string $hex): ?array
    {
        if (! is_string($hex) || ! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($hex), $m)) {
            return null;
        }

        $hex = $m[1];

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @param array{int,int,int} $rgb */
    private static function luminance(array $rgb): float
    {
        $channels = array_map(static function (int $channel): float {
            $channel /= 255;

            return $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
