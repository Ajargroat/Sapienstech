<?php

/*
|--------------------------------------------------------------------------
| Security headers
|--------------------------------------------------------------------------
|
| Every tenant answers on its own registrable domain, so these headers are
| per-response and never per-session. The Content-Security-Policy is built
| from this file alone: nothing here is client-controlled, and no value is
| concatenated from request input.
|
| `csp.mode` keeps the rollout honest. In `report-only` the browser evaluates
| the policy and reports violations without blocking anything, so a policy
| that is too tight shows up as report noise instead of a broken tenant. Only
| `enforce` actually blocks. Flip it per environment, not per tenant.
|
*/

return [

    'csp' => [

        // 'off' | 'report-only' | 'enforce'
        'mode' => env('SECURITY_CSP_MODE', 'report-only'),

        /*
         * Script origins. 'self' covers the Vite bundle; everything else in
         * the app is inline <script> and is authorised by hash or nonce below.
         * No 'unsafe-inline' — that is the whole point of the policy.
         */
        'script_src' => ["'self'"],

        // Styles are emitted inline by the token partials (theme-vars,
        // theme-fonts, element overrides), so 'unsafe-inline' is required for
        // style-src only. This is a deliberate, bounded concession: a style
        // cannot execute script, and the tenant-supplied CSS escape hatch is
        // already admin-only (theme-vars.blade.php:49-52).
        'style_src' => ["'self'", "'unsafe-inline'"],

        // Font Awesome is loaded from cdnjs by partials/theme-icons; the
        // tenant's chosen icon set is served same-origin from public/icons.
        'font_src' => ["'self'", 'data:', 'https://cdnjs.cloudflare.com'],

        // MathJax's SVG output inlines data: URIs for glyphs.
        'img_src' => ["'self'", 'data:', 'blob:'],

        /*
         * Connect origins for fetch/XHR/WebSocket. Reverb broadcasts over a
         * WebSocket, whose origin is not always the page's own, so it is
         * configured rather than guessed. 'self' plus the wss: scheme keeps
         * the common case working; narrow this per deployment if the
         * broadcaster lives on a separate host.
         */
        'connect_src' => ["'self'", 'ws:', 'wss:'],

        /*
         * MathJax 3 (tex-svg.js) is fetched from jsdelivr. It is the only
         * script origin the app needs today; a tenant that does not use math
         * never loads it, and removing this entry after a self-host migration
         * is a one-line change.
         */
        'script_src_extra' => ['https://cdn.jsdelivr.net'],

        /*
         * Hash-authorized inline scripts. Filled by
         * `php artisan security:hashes` from the actual rendered pages, so the
         * list is measured rather than hand-typed. Keys are the human name of
         * the surface (for review), values are 'sha256-…' tokens.
         */
        'inline_script_hashes' => [],

        // Per-response nonce for inline scripts that must carry request-bound
        // data. Enabled because the five inline scripts left in the app
        // (color-scheme, preview-banner, mathjax config, login tabs, deal
        // drawer) all embed Blade-resolved values or must run before Vite's
        // bundle — a static hash cannot cover them, a nonce can.
        'nonce' => true,

        // Sent by the browser on every violation in report-only mode.
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),
    ],

    'headers' => [
        // No tenant page is meant to be framed; the Studio previews with an
        // iframe on the *same* origin, hence SAMEORIGIN rather than DENY.
        'x-frame-options' => 'SAMEORIGIN',

        // Stop the browser from guessing a type we did not declare.
        'x-content-type-options' => 'nosniff',

        // Send the full URL to same-origin destinations, only the origin
        // cross-origin, nothing at all when downgrading to http.
        'referrer-policy' => 'strict-origin-when-cross-origin',

        // Nothing in the app asks for these; deny them at the header so an
        // injected script cannot either.
        'permissions-policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ],
];
