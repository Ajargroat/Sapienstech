{{-- resources/views/public/sections/hero/default.blade.php — split text + media --}}
@php
    $h         = $cfg;
    $textFirst = ($h['text_side'] ?? 'start') === 'start';
    $reveal    = $anim['reveal'] ?? true;
    $float     = $anim['float'] ?? true;
    $media     = $h['media'] ?? 'mockup';
@endphp
<section class="lp-section lp-section--hero relative flex items-center overflow-hidden"
         style="min-height:var(--hero-min-height)">

    @include('public.sections._glow-blobs', ['scope' => 'hero'])

    <div class="landing-container w-full grid md:grid-cols-2 gap-[var(--grid-gap)] items-center relative z-10">

        {{-- Text column --}}
        <div class="flex flex-col gap-6 {{ $reveal ? 'reveal' : '' }}"
             style="text-align:{{ $h['text_align'] ?? 'start' }};{{ $textFirst ? '' : 'order:2' }}">
            <h1 style="font-size:var(--h1-size);font-weight:var(--heading-weight);line-height:var(--hero-line-height);letter-spacing:var(--heading-letter-spacing)">
                {{ $h['title_line1'] }} <br>
                @if ($h['gradient_text'] ?? true)
                    {{-- Inline (not a utility class) because the direction comes
                         from config and Tailwind can't see runtime-built names. --}}
                    <span class="text-transparent bg-clip-text"
                          style="background-image:linear-gradient({{ ($h['gradient_dir'] ?? 'to-l') === 'to-r' ? 'to right' : 'to left' }}, var(--c-primary), var(--c-secondary))">
                        {{ $h['title_line2'] }}
                    </span>
                @else
                    <span style="color:var(--c-primary)">{{ $h['title_line2'] }}</span>
                @endif
            </h1>
            <p class="text-lg leading-relaxed" style="color:var(--c-muted);max-width:var(--measure)">{{ $h['subtitle'] }}</p>
            <div class="flex flex-wrap gap-4 mt-4">
                @foreach ($h['buttons'] ?? [] as $b)
                    @if ($b['visible'] ?? true)
                        @include('public.sections._button', ['label' => $b['label'], 'href' => $b['href'] ?? '#', 'tone' => $b['style'] ?? null])
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Media column --}}
        @if ($media === 'mockup')
            <div class="relative h-[500px] w-full {{ $reveal ? 'reveal' : '' }} hidden md:block"
                 style="{{ $textFirst ? 'order:2' : 'order:1' }}">
                @include('public.sections.hero._mockup', ['float' => $float, 'reveal' => $reveal])
            </div>
        @elseif ($media === 'photo' && !empty($h['image']))
            <div class="{{ $reveal ? 'reveal' : '' }}" style="{{ $textFirst ? 'order:2' : 'order:1' }}">
                <img src="{{ tenant_asset($h['image']) }}" alt="{{ $h['image_alt'] ?? '' }}"
                     class="w-full rounded-[var(--radius-card)]" loading="lazy">
            </div>
        @endif
    </div>
</section>
