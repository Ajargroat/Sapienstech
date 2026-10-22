<?php

/*
|--------------------------------------------------------------------------
| The core token sheet — the brand contract for bespoke pages
|--------------------------------------------------------------------------
|
| ThemeTokens::VARS currently maps 193 properties. That is the right size for
| a dashboard, which has to express every component the platform ships. It is
| the wrong size for a hand-written landing page, for one specific reason:
|
|   Layout tokens are what make two different pages look the same.
|
| A page that reads --card-gap, --hero-cols, --nav-height and --sidebar-width
| is being told how to lay itself out, and every page told the same thing
| comes out looking like the same page. The 193-property sheet does not only
| describe the brand — it describes the *skeleton*, and the skeleton is the
| thing a bespoke page exists to escape.
|
| So this file declares the smaller sheet a bespoke page is expected to use:
| colour, type, shape, rhythm, state and motion. Everything in it already
| exists in ThemeTokens::VARS — this is a curated subset, not a second token
| system, so a bespoke page and a dashboard can never disagree about what
| "primary" means.
|
| What is deliberately NOT here, and why:
|   - layout/sidebar/topnav/hero geometry — the page decides its own;
|   - per-accent colours (12 of them) — accents are illustration, not brand;
|   - shadows, blurs, glass, noise, specular — depth is a design decision the
|     page can make with plain CSS;
|   - gradients and glows — decoration, and the strongest "same page" signal.
|
| A page may still use any token the full sheet emits (they are all in :root).
| The core sheet is the contract the generator writes against and the linter
| checks, which is what keeps generated pages both on-brand and free.
|
*/

return [

    /*
     * Colour: the identity of the brand. Seven values that must all come from
     * the tenant, plus the two on-* pairs that keep text readable on them.
     */
    'color' => [
        'colors.background' => 'c-background',
        'colors.surface' => 'c-surface',
        'colors.text' => 'c-text',
        'colors.text_muted' => 'c-muted',
        'colors.primary' => 'c-primary',
        'colors.secondary' => 'c-secondary',
        'colors.border' => 'c-border',
        'colors.on_primary' => 'c-on-primary',
    ],

    /*
     * Type: the voice. Families come from the tenant's font folders; the sizes
     * are the only scale the page should lean on, so headings stay in
     * proportion across every tenant.
     */
    'type' => [
        'typography.font_family' => 'font-body',
        'typography.font_heading' => 'font-heading',
        'typography.body_size' => 'body-size',
        'typography.body_weight' => 'body-weight',
        'typography.heading_weight' => 'heading-weight',
        'typography.h1_size' => 'h1-size',
        'typography.h2_size' => 'h2-size',
        'typography.line_height' => 'line-height',
        'typography.measure' => 'measure',
    ],

    /*
     * Shape: how soft the brand is. Radii only — a page that wants square
     * corners sets its own; a page that wants the tenant's language uses these.
     */
    'shape' => [
        'shape.radius_md' => 'radius-md',
        'shape.radius_lg' => 'radius-lg',
        'shape.button_radius' => 'radius-button',
        'shape.card_radius' => 'radius-card',
        'shape.border_width' => 'surface-border-w',
    ],

    /*
     * Rhythm: the two numbers that make two pages feel like one site — the
     * content width and the gap between blocks. Deliberately no section count,
     * no column count, no hero height.
     */
    'rhythm' => [
        'layout.content_max_width' => 'content-max',
        'layout.content_padding' => 'content-padding',
        'layout.card_gap' => 'card-gap',
    ],

    /*
     * State: what a control looks like when touched. A page that invents its
     * own hover colour is a page that starts to drift.
     */
    'state' => [
        'colors.primary_hover' => 'c-primary-hover',
        'colors.link' => 'c-link',
        'colors.danger' => 'c-danger',
        'colors.success' => 'c-success',
    ],

    /*
     * Motion: the tenant's own sense of speed. The `reduced-motion` flag is the
     * accessibility switch — a page that animates must honour it.
     */
    'motion' => [
        'motion.easing' => 'ease',
        'motion.duration_scale' => 'duration-scale',
        'landing.reveal_duration' => 'reveal-duration',
    ],
];
