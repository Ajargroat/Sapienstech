{{--
    resources/views/public/sections/_heading.blade.php

    The heading + subheading block that services and testimonials used to
    duplicate byte-for-byte, blog rendered as a left-aligned near-copy, and cta
    bypassed entirely with hardcoded `text-4xl md:text-6xl` — so that heading
    ignored --h2-size and resized with the viewport instead of the theme.

    Alignment and the rule under the heading come from theme.decoration, so an
    archetype restyles every section heading in one place.

    Expects: $heading, optional $subheading / $eyebrow / $align / $rule / $action.
--}}
@php
    // @include gives no defaults, so every optional prop has to be guarded.
    $dec     = site('decoration', []);
    $align   = $align   ?? ($dec['heading_align'] ?? 'center');
    $rule    = $rule    ?? ($dec['heading_rule'] ?? 'none');

    // `between` is the blog layout: title on one side, action link on the other.
    $isBetween = $align === 'between';
    $textAlign   = ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'][$align] ?? 'text-start';
    $mxAuto      = $align === 'center' ? 'mx-auto' : '';

    $titleStyle = 'font-size:var(--h2-size);font-weight:var(--heading-weight);'
                 .'text-transform:var(--heading-transform);letter-spacing:var(--heading-letter-spacing)';
@endphp

<div class="mb-12 {{ $isBetween ? '' : $textAlign }}">
    @if (!empty($eyebrow))
        <p class="lp-eyebrow {{ $mxAuto }} mb-4">{!! $eyebrow !!}</p>
    @endif

    <div class="{{ $isBetween ? 'flex flex-col md:flex-row justify-between items-end gap-4' : '' }}">
        <div>
            <h2 class="lp-heading__title" style="{{ $titleStyle }}">{{ $heading }}</h2>

            @if ($rule !== 'none')
                <span class="lp-heading__rule lp-heading__rule--{{ $rule }} {{ $mxAuto }}" aria-hidden="true"></span>
            @endif

            @if (!empty($subheading))
                <p class="{{ $isBetween ? 'mt-3' : 'mt-4' }} {{ $mxAuto }}"
                   style="color:var(--c-muted);max-width:var(--measure)">{{ $subheading }}</p>
            @endif
        </div>

        @if (!empty($action))
            <div class="shrink-0">{!! $action !!}</div>
        @endif
    </div>
</div>
