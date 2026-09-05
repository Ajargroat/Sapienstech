{{--
    resources/views/partials/theme-attrs.blade.php

    The non-value half of the theme. Anything that selects a *behaviour* rather
    than a length or colour cannot be a CSS custom property, so it goes on the
    element as a data attribute and the stylesheets key off it
    (e.g. `[data-reveal="line-mask"] .reveal { ... }`).

    Pass `selector` to choose which element carries them: `html` for the public
    pages, `body` for the dashboard shells. Defaults to body.
--}}
@php
    $t   = $t ?? site('theme');
    $m   = $t['motion'] ?? [];
    $bg  = $t['background'] ?? [];
    $dec = $t['decoration'] ?? [];
    $btn = $t['buttons'] ?? [];
    $acc = $t['accessibility'] ?? [];
@endphp
data-archetype="{{ $t['archetype'] ?? 'default' }}"
data-surface="{{ $t['surface']['material'] ?? 'glass' }}"
data-bg-mode="{{ $bg['mode'] ?? 'glow' }}"
data-alternation="{{ $bg['section_alternation'] ?? 'none' }}"
data-reveal="{{ $m['reveal'] ?? 'fade-up' }}"
data-stagger="{{ $m['stagger'] ?? 'sequential' }}"
data-parallax="{{ $m['parallax'] ?? 'none' }}"
data-tilt="{{ ($m['tilt'] ?? false) ? 'on' : 'off' }}"
data-magnetic="{{ ($m['magnetic'] ?? false) ? 'on' : 'off' }}"
data-hover="{{ $m['hover'] ?? 'lift' }}"
data-divider="{{ $dec['section_divider'] ?? 'none' }}"
data-heading-rule="{{ $dec['heading_rule'] ?? 'none' }}"
data-heading-align="{{ $dec['heading_align'] ?? 'center' }}"
data-accent-shapes="{{ $dec['accent_shapes'] ?? 'none' }}"
data-btn="{{ $btn['variant'] ?? 'solid' }}"
data-contrast="{{ $acc['contrast'] ?? 'normal' }}"
data-focus-ring="{{ $acc['focus_ring'] ?? 'outline' }}"
data-reduce-motion="{{ $acc['reduce_motion'] ?? 'respect' }}"
