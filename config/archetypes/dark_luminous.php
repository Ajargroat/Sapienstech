<?php

/*
|--------------------------------------------------------------------------
| Archetype: dark_luminous
|--------------------------------------------------------------------------
|
| Deep-navy ground, aurora washes, glass cards and glowing accents — the
| "premium dark" identity. Every value below is a lever the engine already
| reads; the archetype only chooses them, so a tenant can retune any one of
| them from config/tenants/{slug}.php.
|
| Fonts: Vazirmatn carries Persian/Arabic coverage (see aurora_glass's note);
| the platform already ships it under public/fonts/vazirmatn.
|
*/

return [

    'theme' => [

        'colors' => [
            'primary' => '#2DD4BF',   // teal glow
            'secondary' => '#818CF8',   // indigo glow
            'background' => '#04070D',   // near-black navy
            'surface' => '#0B1220',
            'surface_alt' => '#131C2E',
            'text' => '#E8EEF9',
        ],

        'typography' => [
            'font_family' => 'Vazirmatn',
            'font_heading' => 'Vazirmatn',
            'heading_weight' => '800',
            'heading_line_height' => '1.15',
            'heading_letter_spacing' => '-.01em',
            'h1_size' => 'clamp(2.75rem, 6vw, 4.5rem)',
            'h2_size' => 'clamp(1.875rem, 4vw, 3rem)',
        ],

        // Glass, not paper: the aurora ground shows through every card.
        'surface' => ['material' => 'glass'],

        // `neon` is the existing elevation row that reads as glow on a dark
        // ground (ThemeTokens::ELEVATION).
        'depth' => ['elevation' => 'neon', 'shadow_tint' => true],

        'shape' => [
            'radius_scale' => 'soft',
            'button_radius' => '999px',
            'card_radius' => '24px',
        ],

        'scale' => ['density' => 'normal', 'container_width' => 'content', 'section_rhythm' => '7rem'],

        'motion' => [
            'reveal' => 'blur-in',
            'stagger' => 'sequential',
            'parallax' => 'subtle',
            'tilt' => true,
            'hover' => 'glow',
            'text_effect' => 'gradient-shift',
            'scroll_progress' => true,
            'marquee_pause' => true,
        ],

        'background' => [
            'mode' => 'aurora',
            'section_alternation' => 'none',
            // A radial vignette behind hero content.
            'spotlight' => 'soft',
            // The blobs breathe instead of sitting still.
            'glow_animation' => 'breathe',
            'glow_blobs' => [
                ['scope' => 'hero',    'at' => 'hero-start',  'color' => 'primary',   'size' => '28rem', 'opacity' => '.12'],
                ['scope' => 'hero',    'at' => 'hero-end',    'color' => 'secondary', 'size' => '28rem', 'opacity' => '.12'],
                ['scope' => 'advisor', 'at' => 'top-end',     'color' => 'primary',   'size' => '20rem', 'opacity' => '.07'],
                ['scope' => 'advisor', 'at' => 'bottom-start', 'color' => 'secondary', 'size' => '20rem', 'opacity' => '.07'],
                ['scope' => 'cta',     'at' => 'center',      'color' => 'secondary', 'size' => '50rem', 'opacity' => '.07', 'blur' => '150px'],
            ],
        ],

        'decoration' => [
            'section_divider' => 'gradient-band',
            'heading_rule' => 'short-bar',
            'heading_align' => 'center',
            'accent_shapes' => 'corner-brackets',
            'icon_backdrop' => 'gradient',
            'card_edge' => 'glow-border',
        ],

        'buttons' => [
            'variant' => 'gradient',
            'size' => 'md',
            'weight' => '700',
            'icon' => 'arrow',
            'hover' => 'lift',
            'shadow' => true,
        ],

        // The portal shells' ground glow, tokenised.
        'portal' => ['glow_size' => '46rem', 'glow_blur' => '160px', 'glow_opacity' => '.12', 'ground' => true],

        // The base palette IS dark, so a first-time visitor boots here and the
        // toggle offers the light reading (ThemeTokens::schemes).
        'default_scheme' => 'dark',
        'schemes' => [
            'dark' => [],
            'light' => [
                'background' => '#F6F8FB',
                'surface' => '#FFFFFF',
                'surface_alt' => '#EDF1F7',
                'surface_elevated' => '#FFFFFF',
                'text' => '#0A1020',
                'primary' => '#0F766E',
                'secondary' => '#4338CA',
            ],
        ],
    ],

    'public' => [
        'landing' => [
            // Same five sections the tenant's copy already fills, so the
            // archetype adds no section the tenant has no content for.
            'sections' => ['hero', 'advisor', 'process', 'services', 'cta'],

            // `default` + `mockup` is the only hero combination that renders a
            // structure the tenant already has copy for.
            'hero' => ['layout' => 'default', 'media' => 'mockup', 'gradient_text' => true],

            'advisor' => ['variant' => 'default'],
            'services' => ['variant' => 'bento', 'columns' => 3],
            'cta' => ['variant' => 'boxed-card'],
        ],
    ],

];
