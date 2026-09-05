{{--
    resources/views/public/sections/hero/oversized-type.blade.php

    Type IS the hero: no image, no mockup, no decoration — just a headline at
    display scale and a rule. Only works with a heavy display face, which is why
    brutalist_mono is the archetype that ships it.
--}}
@php
    $h      = $cfg;
    $reveal = $anim['reveal'] ?? true;
@endphp
<section class="lp-section lp-section--hero relative flex items-center overflow-hidden"
         style="min-height:var(--hero-min-height)">
    <div class="landing-container relative z-10">
        <div class="flex flex-col gap-8 {{ $reveal ? 'reveal' : '' }}" style="max-width:60rem">
            <h1 style="font-size:var(--h1-size);font-weight:var(--heading-weight);line-height:var(--hero-line-height);letter-spacing:var(--heading-letter-spacing);text-transform:var(--heading-transform)">
                {{ $h['title_line1'] ?? '' }}
                <span style="color:var(--c-primary)">{{ $h['title_line2'] ?? '' }}</span>
            </h1>

            <hr class="lp-rule" style="border:0;border-block-start:var(--surface-border-w) solid var(--c-border);max-width:8rem;margin:0">

            @if (!empty($h['subtitle']))
                <p class="text-lg leading-relaxed" style="color:var(--c-muted);max-width:var(--measure)">{{ $h['subtitle'] }}</p>
            @endif

            <div class="flex flex-wrap gap-4">
                @foreach ($h['buttons'] ?? [] as $b)
                    @if ($b['visible'] ?? true)
                        @include('public.sections._button', ['label' => $b['label'], 'href' => $b['href'] ?? '#', 'tone' => $b['style'] ?? null])
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</section>
