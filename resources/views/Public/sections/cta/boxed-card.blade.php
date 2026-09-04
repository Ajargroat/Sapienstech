{{-- resources/views/public/sections/cta/boxed-card.blade.php — heading inside a card --}}
@php
    $cta    = $cfg;
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $cta['id'] ?? 'cta' }}" class="lp-section">
    <div class="landing-container">
        <div class="lp-card lp-card--featured relative overflow-hidden p-10 md:p-16 text-center {{ $reveal ? 'reveal' : '' }}"
             style="background:var(--c-surface);border:var(--surface-border-w) solid var(--c-border)">
            @include('public.sections._glow-blobs', ['scope' => 'cta'])

            <h2 class="relative z-10 mb-4"
                style="font-size:var(--h2-size);font-weight:var(--heading-weight);text-transform:var(--heading-transform);letter-spacing:var(--heading-letter-spacing)">
                {{ $cta['heading'] }}
            </h2>
            <p class="relative z-10 mb-8" style="color:var(--c-muted);max-width:var(--measure);margin-inline:auto">{{ $cta['text'] }}</p>
            <div class="relative z-10 flex flex-col sm:flex-row justify-center items-center gap-4">
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
    </div>
</section>
