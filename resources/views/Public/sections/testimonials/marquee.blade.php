{{--
    resources/views/public/sections/testimonials/marquee.blade.php

    Auto-scrolling ticker. Motion is the identity here, so it is deliberately
    the variant that most changes how a page feels. Honours prefers-reduced-motion
    via the shared .lp-marquee rule.
--}}
@php
    $tm    = $cfg;
    $items = array_values(array_filter($tm['items'] ?? [], static fn ($i) => $i['visible'] ?? true));
@endphp

@if ($items !== [])
<section id="{{ $tm['id'] ?? 'testimonials' }}" class="lp-section overflow-hidden">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $tm['heading'] ?? null,
            'subheading' => $tm['subheading'] ?? null,
        ])
    </div>

    {{-- Duplicated track so the loop has no visible seam. --}}
    <div class="lp-marquee" style="--marquee-duration:{{ site('motion.marquee_speed', '38s') }}">
        @foreach ([0, 1] as $pass)
            <div class="lp-marquee__track" @if($pass) aria-hidden="true" @endif>
                @foreach ($items as $item)
                    <figure class="lp-marquee__item">
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
