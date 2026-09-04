{{--
    resources/views/public/sections/_icon.blade.php

    The icon chip shared by services and ecosystem. Its backdrop follows
    theme.decoration.icon_backdrop so an archetype can drop the plate entirely
    (paper / brutalist) or make it a circle, in one place.

    Expects: $icon (fa class), $accent (a CSS colour value).
--}}
@php($backdrop = site('decoration.icon_backdrop', 'soft-square'))

@if (!empty($icon))
    <div class="lp-icon lp-icon--{{ $backdrop }}"
         style="color:{{ $accent ?? 'var(--c-primary)' }}">
        <i class="{{ $icon }}" aria-hidden="true"></i>
    </div>
@endif
