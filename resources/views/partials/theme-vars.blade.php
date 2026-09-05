{{--
    resources/views/partials/theme-vars.blade.php

    Single source of truth for tenant branding CSS variables. Included by the
    landing page, login, consultant layout and student layout so every entry
    point renders with the exact same palette, type and shape tokens.

    The list is no longer hand-written: App\Support\ThemeTokens flattens the
    resolved token tree into `theme.vars` (see its VARS constant for the
    path => custom-property mapping) and this partial just loops over it. Adding
    a token now means adding it to the config, not editing this file — which is
    how `radius_2xl`, `sidebar_radius` and `stagger_ms` were silently dropped
    when every variable had to be typed out by hand.
--}}
@php
    $t       = site('theme');
    $vars    = $t['vars'] ?? [];
    $schemes = $t['schemes'] ?? [];
    $animOff = empty($t['effects']['enable_animations']);
@endphp
<style>
    :root {
@foreach ($vars as $name => $value)
        --{{ $name }}: {{ $value }};
@endforeach
    }

@foreach ($schemes as $scheme => $overrides)
    {{--
        Resolved against the tenant's *current* palette, not on its own: a light
        scheme only names backgrounds and text, but border/glass/muted must be
        recomputed from the new ink so they stay visible on a light surface.

        Filtered to colour tokens. resolve() also fills typography, spacing and
        shape with platform defaults, and emitting those here would override the
        tenant's own fonts and radii whenever light mode was active.
    --}}
    @php
        $schemeVars = array_filter(
            \App\Support\ThemeTokens::resolve([
                'colors' => array_replace($t['colors'] ?? [], is_array($overrides) ? $overrides : []),
            ])['vars'],
            static fn (string $name): bool => str_starts_with($name, 'c-'),
            ARRAY_FILTER_USE_KEY
        );
    @endphp
    [data-color-scheme="{{ $scheme }}"] {
        @foreach ($schemeVars as $name => $value)
        --{{ $name }}: {{ $value }};
        @endforeach
    }
@endforeach

@if ($animOff)
    :root { --reveal-duration: 0ms; --float-duration: 0ms; --animation-duration: 0ms; }
@endif

@if (!empty($t['custom']['css']))
    {{-- Admin-only escape hatch for one-off brand requests. --}}
    {!! $t['custom']['css'] !!}
@endif
</style>
