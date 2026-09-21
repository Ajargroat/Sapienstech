{{--
    resources/views/public/sections/testimonials/marquee.blade.php

    Auto-scrolling ticker. Motion is the identity here, so it is deliberately
    the variant that most changes how a page feels. Honours prefers-reduced-motion
    via the shared .lp-marquee rule.
--}}
@php
    $tm    = $cfg;
    $visible = array_filter($tm['items'] ?? [], static fn ($i) => $i['visible'] ?? true);
    $items  = array_values($visible);
    $slugs  = array_keys($visible);
@endphp

@if ($items !== [])
<section id="{{ $tm['id'] ?? 'testimonials' }}" class="lp-section overflow-hidden">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $tm['heading'] ?? null,
            'subheading' => $tm['subheading'] ?? null,
            'path'       => 'public.landing.testimonials',
        ])
    </div>

    {{-- Duplicated track so the loop has no visible seam. --}}
    <div class="lp-marquee" style="--marquee-duration:{{ site('motion.marquee_speed', '38s') }}">
        @foreach ([0, 1] as $pass)
            <div class="lp-marquee__track" @if($pass) aria-hidden="true" @endif>
                @foreach ($items as $i => $item)
                    <figure class="lp-marquee__item"
                            @if (!$pass) data-studio-path="public.landing.testimonials.items.{{ $slugs[$i] }}" @endif>
                        <span class="lp-avatar lp-avatar--sm"
                              style="background:linear-gradient(45deg, var(--c-{{ $item['from'] ?? 'primary' }}), var(--c-{{ $item['to'] ?? 'secondary' }}))">
                            {{ $item['initials'] }}
                        </span>
                        <figcaption>
                            <strong>{{ $item['name'] }}</strong>
                            <span class="text-xs block" style="color:var(--c-primary)">{{ $item['result'] }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
@endif
