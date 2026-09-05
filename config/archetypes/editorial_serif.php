<?php

/*
|--------------------------------------------------------------------------
| Archetype: editorial_serif
|--------------------------------------------------------------------------
|
| Print-inspired: warm paper ground, naskh display headings over a clean body
| face, hairline rules instead of shadows, no floating glows, no rounded
| corners. Deliberately shares nothing with aurora_glass — different surface
| material, different type pairing, different section rhythm, and a different
| hero/services/testimonials structure.
|
*/

return [

    'theme' => [

        'colors' => [
            'primary'     => '#1E3A5F',
            'secondary'   => '#B45309',
            'background'  => '#FBF9F5',
            'surface'     => '#FFFFFF',
            'surface_alt' => '#F3EFE7',
            'text'        => '#14161A',
        ],

        'typography' => [
            // Amiri is a naskh serif with real Persian coverage; Vazirmatn
            // stays on body copy so long text keeps its legibility.
            'font_family'            => 'Vazirmatn',
            'font_heading'           => 'Amiri',
            'font_accent'            => 'Amiri',
            'body_size'              => '17px',
            'heading_weight'         => '700',
            'heading_transform'      => 'none',
            'heading_letter_spacing' => '0',
            'line_height'            => '1.9',
            'measure'                => '38rem',
            'h1_size'                => 'clamp(2.5rem, 5vw, 3.75rem)',
            'h2_size'                => 'clamp(1.75rem, 3.5vw, 2.5rem)',
        ],

        'surface' => [
            'material' => 'paper',
        ],

        'depth' => [
            // No drop shadows at all — hierarchy comes from rules and space.
            'elevation'   => 'ambient',
            'shadow_tint' => false,
        ],

        'shape' => [
            'radius_scale' => 'sharp',
        ],

        'scale' => [
            'density'         => 'spacious',
            'container_width' => 'narrow',
            'section_rhythm'  => '8rem',
        ],

        'motion' => [
            'reveal'         => 'line-mask',
            'stagger'        => 'none',
            'duration_scale' => '1.4',
            'easing'         => 'cubic-bezier(.32,.72,0,1)',
            'parallax'       => 'none',
            'hover'          => 'underline',
        ],

        'background' => [
            'mode'                => 'flat',
            'section_alternation' => 'rule',
            'glow_blobs'          => [],
            'noise'               => '.03',
        ],

        'decoration' => [
            'section_divider' => 'line',
            'heading_rule'    => 'short-bar',
            'heading_align'   => 'start',
            'accent_shapes'   => 'none',
            'quote_mark'      => 'serif',
            'icon_backdrop'   => 'none',
        ],

        'buttons' => [
            'variant'   => 'underline',
            'size'      => 'sm',
            'weight'    => '600',
            'transform' => 'none',
            'icon'      => 'arrow',
            'hover'     => 'underline',
        ],

        'assets' => [
            'font_url' => 'https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Vazirmatn:wght@300;400;500;600;700&display=swap',
        ],
    ],

    'public' => [
        'landing' => [
            // Different lineup: a trust bar and a process section replace the
            // ecosystem orbit, and the stats band moves up next to the hero.
            'sections' => ['hero', 'stats', 'logos', 'advisor', 'process', 'services', 'testimonials', 'blog', 'cta'],

            'hero'         => [
                'layout'    => 'centered-stack',
                'media'     => 'photo',
                'text_align'=> 'center',
            ],
            'services'     => ['variant' => 'numbered-list', 'columns' => 1],
            'stats'        => ['variant' => 'inline-divider'],
            'testimonials' => ['variant' => 'single-featured'],
            'blog'         => ['variant' => 'list'],
            'cta'          => ['variant' => 'boxed-card'],
            'advisor'      => ['variant' => 'quote-first'],
        ],
    ],

];
