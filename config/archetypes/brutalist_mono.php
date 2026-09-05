<?php

/*
|--------------------------------------------------------------------------
| Archetype: brutalist_mono
|--------------------------------------------------------------------------
|
| Hard edges, no shadows, no blur, no easing. Monospace labels, oversized
| headings, a visible grid, and buttons that are literally blocks of the brand
| colour. The most distant archetype from aurora_glass on purpose: if two
| tenants land on these two bundles there is nothing left in common to spot.
|
*/

return [

    'theme' => [

        'colors' => [
            'primary'     => '#D4FF3F',
            'secondary'   => '#FF5C1A',
            'background'  => '#0A0A0A',
            'surface'     => '#111111',
            'surface_alt' => '#161616',
            'text'        => '#F2F2F2',
            'danger'      => '#FF3B30',
        ],

        'typography' => [
            // Lalezar is a heavy Persian display face; Tajawal gives a plain,
            // unadorned body so the display type carries all the character.
            'font_family'            => 'Tajawal',
            'font_heading'           => 'Lalezar',
            'font_mono'              => 'JetBrains Mono, ui-monospace, monospace',
            'body_size'              => '16px',
            'heading_weight'         => '400',
            'heading_transform'      => 'uppercase',
            'heading_letter_spacing' => '.02em',
            'letter_spacing'         => '.01em',
            'line_height'            => '1.5',
            'measure'                => '40rem',
            'h1_size'                => 'clamp(3rem, 9vw, 6.5rem)',
            'h2_size'                => 'clamp(2rem, 5vw, 3.5rem)',
        ],

        'surface' => [
            'material' => 'flat',
        ],

        'depth' => [
            // Offset solid blocks rather than blurred shadows.
            'elevation'   => 'brutal',
            'shadow_tint' => false,
        ],

        'shape' => [
            'radius_scale' => 'sharp',
            'border_width' => '2px',
        ],

        'scale' => [
            'density'         => 'compact',
            'container_width' => 'wide',
            'section_rhythm'  => '5rem',
        ],

        'motion' => [
            // No easing, no drift: state changes are instant.
            'reveal'         => 'none',
            'stagger'        => 'none',
            'duration_scale' => '0',
            'easing'         => 'steps(1)',
            'parallax'       => 'none',
            'hover'          => 'invert',
        ],

        'background' => [
            'mode'                => 'grid',
            'section_alternation' => 'tint',
            'glow_blobs'          => [],
            'grid_size'           => '40px',
        ],

        'decoration' => [
            'section_divider' => 'slant',
            'heading_rule'    => 'number',
            'heading_align'   => 'start',
            'accent_shapes'   => 'plus',
            'icon_backdrop'   => 'none',
        ],

        'buttons' => [
            'variant'   => 'brutal',
            'size'      => 'md',
            'weight'    => '700',
            'transform' => 'uppercase',
            'icon'      => 'none',
            'hover'     => 'invert',
        ],

        'accessibility' => [
            'contrast'      => 'high',
            'focus_ring'    => 'outline',
            'reduce_motion' => 'force-off',
        ],

        'assets' => [
            'font_url' => 'https://fonts.googleapis.com/css2?family=Lalezar&family=Tajawal:wght@400;500;700&family=JetBrains+Mono:wght@400;700&display=swap',
        ],
    ],

    'public' => [
        'landing' => [
            // No advisor portrait, no blog feed: a numbered manifesto and a
            // comparison table instead.
            'sections' => ['hero', 'logos', 'services', 'process', 'comparison', 'stats', 'faq', 'cta'],

            'hero'      => ['layout' => 'oversized-type', 'media' => 'none', 'text_align' => 'start'],
            'services'  => ['variant' => 'numbered-list', 'columns' => 2],
            'stats'     => ['variant' => 'band'],
            'cta'       => ['variant' => 'split'],
            'testimonials' => ['variant' => 'marquee'],
        ],

        'nav' => [
            'position' => 'top',
            'style'    => 'solid',
            'sticky'   => false,
        ],
    ],

];
