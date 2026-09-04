{{--
    resources/views/public/sections/hero/centered-stack.blade.php

    Everything on the centre axis, media below the copy instead of beside it.
    Reads as a different page from split-left at a glance, which is the point.
--}}
@php
    $h      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $media  = $h['media'] ?? 'photo';
@endphp
<section class="lp-section lp-section--hero relative flex items-center overflow-hidden"
         style="min-height:var(--hero-min-height)">

    @include('public.sections._glow-blobs', ['scope' => 'hero'])

    <div class="landing-container relative z-10 flex flex-col items-center gap-12 text-center">
        <div class="flex flex-col gap-6 items-center {{ $reveal ? 'reveal' : '' }}">
            @if (!empty($h['eyebrow']))
                <p class="lp-eyebrow mx-auto">{{ $h['eyebrow'] }}</p>
            @endif

            <h1 style="font-size:var(--h1-size);font-weight:var(--heading-weight);line-height:var(--hero-line-height);letter-spacing:var(--heading-letter-spacing)">
                {{ $h['title_line1'] ?? '' }}
                @if (!empty($h['title_line2']))
                    <br>
                    @if ($h['gradient_text'] ?? true)
                        <span class="text-transparent bg-clip-text"
                              style="background-image:var(--brand-gradient)">{{ $h['title_line2'] }}</span>
                    @else
                        <span style="color:var(--c-primary)">{{ $h['title_line2'] }}</span>
                    @endif
                @endif
            </h1>

            @if (!empty($h['subtitle']))
                <p class="text-lg leading-relaxed" style="color:var(--c-muted);max-width:var(--measure)">{{ $h['subtitle'] }}</p>
            @endif

            <div class="flex flex-wrap justify-center gap-4 mt-4">
                @foreach ($h['buttons'] ?? [] as $b)
                    @if ($b['visible'] ?? true)
                        @include('public.sections._button', ['label' => $b['label'], 'href' => $b['href'] ?? '#', 'tone' => $b['style'] ?? null])
                    @endif
                @endforeach
            </div>
        </div>

        @if ($media === 'photo' && !empty($h['image']))
            <img src="{{ tenant_asset($h['image']) }}" alt="{{ $h['image_alt'] ?? '' }}"
                 class="lp-hero-media w-full {{ $reveal ? 'reveal' : '' }}"
                 style="max-width:64rem;border-radius:var(--radius-card)" loading="lazy">
        @elseif ($media === 'mockup')
            <div class="relative h-[420px] w-full hidden md:block {{ $reveal ? 'reveal' : '' }}">
                @include('public.sections.hero._mockup', ['float' => $anim['float'] ?? true])
            </div>
        @endif
    </div>
</section>
