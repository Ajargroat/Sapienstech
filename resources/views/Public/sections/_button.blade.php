{{--
    resources/views/public/sections/_button.blade.php

    One button, one vocabulary. Previously hero and cta used style='solid' to
    mean "inverted text-on-background" while advisor used style='primary' to
    mean "brand fill" — the same word meant opposite things in two sections, and
    every section restated the padding, radius and transition inline.

    `tone` is the per-button colour role; shape, size, weight and hover come from
    theme.buttons, so an archetype can make every button on a site square,
    uppercase and hard-edged without touching a template.

    Expects: $label, optional $href / $route / $tone / $icon / $block / $class.
--}}
@php
    $b    = site('buttons', []);
    $tone = $tone ?? ($b['variant'] ?? 'solid');

    // Translate the legacy config vocabulary onto the new roles.
    $tone = ['solid' => 'inverted', 'ghost' => 'glass', 'primary' => 'primary'][$tone] ?? $tone;

    $icon = $icon ?? ($b['icon'] ?? 'none');
    $url  = !empty($route) ? route($route) : ($href ?? '#');
    $cls  = trim('lp-btn lp-btn--'.$tone
        .(!empty($block) ? ' lp-btn--block' : '')
        .' '.($class ?? ''));
@endphp

<a href="{{ $url }}" class="{{ $cls }}">
    {{ $label ?? '' }}
    @if ($icon !== 'none')
        <i class="fa-solid {{ ['arrow' => 'fa-arrow-left', 'chevron' => 'fa-chevron-left'][$icon] ?? 'fa-plus' }} text-xs" aria-hidden="true"></i>
    @endif
</a>
