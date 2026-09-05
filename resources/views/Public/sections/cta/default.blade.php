{{-- resources/views/public/sections/cta/default.blade.php — full-width band --}}
@php
    $cta    = $cfg;
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $cta['id'] ?? 'cta' }}" class="lp-section lp-section--cta relative overflow-hidden">
    <div class="absolute inset-0 bg-linear-to-b from-transparent to-(--c-surface-alt) pointer-events-none z-0"></div>

    @include('public.sections._glow-blobs', ['scope' => 'cta'])

    <div class="landing-container relative z-10 {{ $reveal ? 'reveal' : '' }}" style="max-width:var(--measure);margin-inline:auto;text-align:center">
        {{-- Was `text-4xl md:text-6xl`, which ignored --h2-size entirely: this
             heading alone did not respond to the theme's type scale. --}}
        <h2 style="font-size:var(--h2-size);font-weight:var(--heading-weight);text-transform:var(--heading-transform);letter-spacing:var(--heading-letter-spacing)"
            class="mb-6">{{ $cta['heading'] }}</h2>
        <p class="mb-10" style="color:var(--c-muted);max-width:var(--measure);margin-inline:auto">{{ $cta['text'] }}</p>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
            @foreach ($cta['buttons'] ?? [] as $b)
                @if ($b['visible'] ?? true)
                    @include('public.sections._button', [
                        'label' => $b['label'],
                        'route' => $b['route'] ?? null,
                        'href'  => $b['href'] ?? '#',
                        'tone'  => $b['style'] ?? null,
                    ])
                @endif
            @endforeach
        </div>
    </div>
</section>
