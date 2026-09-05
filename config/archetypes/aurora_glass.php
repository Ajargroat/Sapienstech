<?php

/*
|--------------------------------------------------------------------------
| Archetype: aurora_glass
|--------------------------------------------------------------------------
|
| The platform's current identity, restated as an explicit bundle so baseline
| and archetype agree. Translucent surfaces, generous radii, floating
| brand-tinted glows, soft geometric type.
|
| This is the reference archetype: tenant-one renders comparable to the
| pre-archetype site, which is what makes the migration verifiable.
|
| NOTE ON FONTS: every tenant so far is Persian (locale `fa`), so a heading
| face must carry Arabic-script coverage — Latin display fonts render tofu.
| The families used across archetypes all ship Persian/Arabic coverage from
| Google Fonts. Override per tenant in config/tenants/{slug}.php.
|
*/

return [

    'theme' => [

        'colors' => [
            'primary'     => '#06B6D4',
            'secondary'   => '#A855F7',
            'background'  => '#000000',
            'surface'     => '#111111',
            'surface_alt' => '#1A1A1A',
            'text'        => '#FFFFFF',
        ],

        'typography' => [
            'font_family'    => 'Vazirmatn',
            'font_heading'   => 'Vazirmatn',
            'heading_weight' => '800',
            'h1_size'        => 'clamp(2.75rem, 6vw, 4.5rem)',
            'h2_size'        => 'clamp(1.875rem, 4vw, 3rem)',
        ],

        'surface' => [
            'material' => 'glass',
        ],

        'depth' => [
            'elevation'   => 'soft',
            'shadow_tint' => true,
        ],

        'shape' => [
            'radius_scale'  => 'soft',
            'button_radius' => '999px',
            'card_radius'   => '24px',
        ],

        'scale' => [
            'density'         => 'normal',
            'container_width' => 'content',
            'section_rhythm'  => '6rem',
        ],

        'motion' => [
            'reveal'   => 'fade-up',
            'stagger'  => 'sequential',
            'parallax' => 'subtle',
            'hover'    => 'lift',
        ],

        'background' => [
            'mode'                => 'glow',
            'section_alternation' => 'none',
            // Replaces the blobs hero/advisor/ecosystem/cta used to hand-place.
            // `scope` decides which section renders it, so a section that wants
            // no glow simply gets no matching entry.
            'glow_blobs'          => [
                ['scope' => 'hero',     'at' => 'hero-start', 'color' => 'primary',   'size' => '24rem', 'opacity' => '.10'],
                ['scope' => 'hero',     'at' => 'hero-end',   'color' => 'secondary', 'size' => '24rem', 'opacity' => '.10'],
                ['scope' => 'advisor',  'at' => 'top-end',    'color' => 'primary',   'size' => '18rem', 'opacity' => '.05'],
                ['scope' => 'advisor',  'at' => 'bottom-start', 'color' => 'secondary', 'size' => '18rem', 'opacity' => '.05'],
                ['scope' => 'cta',      'at' => 'center',     'color' => 'primary',   'size' => '50rem', 'opacity' => '.05', 'blur' => '150px'],
            ],
        ],

        'decoration' => [
            'section_divider' => 'none',
            'heading_rule'    => 'none',
            'heading_align'   => 'center',
            'accent_shapes'   => 'none',
            'icon_backdrop'   => 'soft-square',
        ],

        'buttons' => [
            'variant' => 'solid',
            'size'    => 'md',
            'weight'  => '600',
            'icon'    => 'none',
            'hover'   => 'lift',
        ],

        'assets' => [
            'font_url' => 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
        ],
    ],

    // Structure as well as styling: an archetype that only changed colours
    // would not stop two tenants looking like the same product.
    //
    // aurora_glass names no variants on purpose — it *is* the platform default,
    // so every section resolves to sections/{name}/default.blade.php. The other
    // archetypes exist to differ from this one.
    'public' => [
        'landing' => [
            'sections' => ['hero', 'advisor', 'ecosystem', 'services', 'stats', 'testimonials', 'blog', 'cta'],
        ],
    ],

];
