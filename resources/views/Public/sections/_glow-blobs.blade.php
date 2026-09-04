{{--
    resources/views/components/landing/glow-blobs.blade.php

    Renders the decorative blurred glows a section asks for. Previously hero,
    advisor, ecosystem and cta each hand-placed their own absolutely-positioned
    rounded-full divs with their own sizes and opacities, so "change the
    background treatment" meant editing four templates.

    Now they come from theme.background.glow_blobs, filtered by scope. An
    archetype that wants no glows sets `glow_blobs => []` and every section
    goes quiet at once.
--}}
@php
    $blobs = site('background.glow_blobs', []);
    $mode  = site('background.mode', 'glow');

    // `flat`/`grid`/`noise` modes own the ground themselves; blobs are a
    // glow-mode affordance.
    if ($mode !== 'glow' || ! is_array($blobs)) {
        $blobs = [];
    }

    $visible = array_filter($blobs, static fn ($b) => is_array($b) && ($b['scope'] ?? 'hero') === ($scope ?? 'hero'));
@endphp

@foreach ($visible as $blob)
    @php
        $color   = 'var(--c-'.($blob['color'] ?? 'primary').')';
        $size    = $blob['size']    ?? 'var(--glow-size)';
        $opacity = $blob['opacity'] ?? 'var(--glow-opacity)';
        $blur    = $blob['blur']    ?? 'var(--glow-blur)';
        $at      = $blob['at']      ?? 'center';

        // Logical positions so the layout mirrors correctly in RTL.
        $pos = [
            'top-start'     => 'top:15%;inset-inline-start:20%',
            'top-end'       => 'top:15%;inset-inline-end:20%',
            'bottom-start'  => 'bottom:15%;inset-inline-start:20%',
            'bottom-end'    => 'bottom:15%;inset-inline-end:20%',
            'center'        => 'top:50%;inset-inline-start:50%;transform:translate(-50%,-50%)',
            'hero-start'    => 'top:25%;inset-inline-start:25%',
            'hero-end'      => 'bottom:25%;inset-inline-end:25%',
            'page-end'      => 'bottom:0;inset-inline-end:0',
        ][$at] ?? 'top:50%;inset-inline-start:50%';
    @endphp
    <div class="absolute rounded-full pointer-events-none lp-glow"
         style="{{ $pos }};width:{{ $size }};height:{{ $size }};background:{{ $color }};opacity:{{ $opacity }};filter:blur({{ $blur }})"></div>
@endforeach
