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
                <p class="lp-eyebrow mx-auto" data-studio-path="public.landing.hero.eyebrow">{{ $h['eyebrow'] }}</p>
            @endif

            <h1 data-studio-path="public.landing.hero.title_line1"
                style="font-size:var(--h1-size);font-weight:var(--heading-weight);line-height:var(--hero-line-height);letter-spacing:var(--heading-letter-spacing)">
                {{ $h['title_line1'] ?? '' }}
                @if (!empty($h['title_line2']))
                    <br>
                    @if ($h['gradient_text'] ?? true)
                        <span class="lp-hero__accent text-transparent bg-clip-text"
                              data-studio-path="public.landing.hero.title_line2"
                              style="background-image:var(--brand-gradient)">{{ $h['title_line2'] }}</span>
                    @else
                        <span class="lp-hero__accent" data-studio-path="public.landing.hero.title_line2" style="color:var(--c-primary)">{{ $h['title_line2'] }}</span>
                    @endif
                @endif
            </h1>

            @if (!empty($h['subtitle']))
                <p class="text-lg leading-relaxed" data-studio-path="public.landing.hero.subtitle" style="color:var(--c-muted);max-width:var(--measure)">{{ $h['subtitle'] }}</p>
            @endif

            <div class="flex flex-wrap justify-center gap-4 mt-4" data-studio-path="public.landing.hero.buttons">
                @foreach ($h['buttons'] ?? [] as $b)
                    @if ($b['visible'] ?? true)
                        @include('public.sections._button', ['label' => $b['label'], 'href' => $b['href'] ?? '#', 'tone' => $b['style'] ?? null, 'icon' => $b['icon'] ?? null, 'block' => false, 'path' => 'public.landing.hero.buttons.'.$loop->index])
                    @endif
                @endforeach
            </div>
        </div>

        @if ($media === 'photo' && !empty($h['image']))
            <img src="{{ tenant_asset($h['image']) }}" alt="{{ $h['image_alt'] ?? '' }}"
                 data-studio-path="public.landing.hero.image"
                 class="lp-hero-media w-full {{ $reveal ? 'reveal' : '' }}"
                 style="max-width:64rem;border-radius:var(--radius-card)" loading="lazy">
        @elseif ($media === 'mockup')
            <div class="relative h-[420px] w-full hidden md:block {{ $reveal ? 'reveal' : '' }}" data-studio-path="public.landing.hero.media">
                @include('public.sections.hero._mockup', ['float' => $anim['float'] ?? true])
            </div>
        @endif
    </div>
</section>
