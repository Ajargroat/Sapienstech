{{-- resources/views/public/sections/services/default.blade.php — card grid --}}
@php
    $s      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $cols   = $s['columns'] ?? 3;
@endphp
<section id="{{ $s['id'] ?? 'services' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $s['heading'] ?? null,
            'subheading' => $s['subheading'] ?? null,
        ])

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-[var(--grid-gap)]"
             style="--cols:{{ $cols }}">
            @foreach ($s['items'] ?? [] as $i => $item)
                @php($accent = 'var(--c-' . ($item['accent'] ?? 'primary') . ')')
                <div class="lp-card group {{ $reveal ? 'reveal' : '' }}"
                     style="{{ $reveal ? 'transition-delay:' . ($i % $cols) * (int) site('landing.stagger_ms', 100) . 'ms;' : '' }}">
                    @include('public.sections._icon', ['icon' => $item['icon'] ?? null, 'accent' => $accent])
                    <h3 class="font-semibold mb-3" style="font-size:var(--h3-size)">{{ $item['title'] }}</h3>
                    <p class="text-sm leading-relaxed" style="color:var(--c-muted)">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
