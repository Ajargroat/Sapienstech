{{-- resources/views/public/sections/cta/split.blade.php — copy left, actions right --}}
@php
    $cta    = $cfg;
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $cta['id'] ?? 'cta' }}" class="lp-section" style="border-block-start:var(--surface-border-w) solid var(--c-border)">
    <div class="landing-container flex flex-col md:flex-row md:items-center justify-between gap-8 {{ $reveal ? 'reveal' : '' }}">
        <div style="max-width:var(--measure)">
            <h2 style="font-size:var(--h2-size);font-weight:var(--heading-weight);line-height:var(--heading-line-height);text-transform:var(--heading-transform);letter-spacing:var(--heading-letter-spacing)"
                data-studio-path="public.landing.cta.heading" class="mb-3">{{ $cta['heading'] }}</h2>
            <p style="color:var(--c-muted)" data-studio-path="public.landing.cta.text">{{ $cta['text'] }}</p>
        </div>
        <div class="flex flex-wrap gap-4 shrink-0" data-studio-path="public.landing.cta.buttons">
            @foreach ($cta['buttons'] ?? [] as $b)
                @if ($b['visible'] ?? true)
                    @include('public.sections._button', [
                        'label' => $b['label'],
                        'route' => $b['route'] ?? null,
                        'href'  => $b['href'] ?? '#',
                        'tone'  => $b['style'] ?? null,
                        'icon'  => $b['icon'] ?? null,
                        'block' => false,
                        'path'  => 'public.landing.cta.buttons.'.$loop->index,
                    ])
                @endif
            @endforeach
        </div>
    </div>
</section>
