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
        .(!empty($b['size']) ? ' lp-btn--'.$b['size'] : '')
        .' '.($class ?? ''));

    // Logical directions: in RTL "forward" is left, in LTR it is right.
    $icons = [
        'arrow'      => 'fa-arrow-left',
        'arrow-ltr'  => 'fa-arrow-right',
        'arrow-down' => 'fa-arrow-down',
        'chevron'    => 'fa-chevron-left',
        'plus'       => 'fa-plus',
        'sparkle'    => 'fa-wand-magic-sparkles',
        'download'   => 'fa-download',
        'play'       => 'fa-play',
    ];
@endphp

<a href="{{ $url }}" class="{{ $cls }}">
    {{ $label ?? '' }}
    @if ($icon !== 'none' && isset($icons[$icon]))
        <i class="fa-solid {{ $icons[$icon] }} text-xs" aria-hidden="true"></i>
    @endif
</a>
