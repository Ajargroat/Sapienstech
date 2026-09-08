{{--
    resources/views/public/sections/services/bento.blade.php — one wide lead tile

    The first service gets a double-width card with the icon beside the copy;
    the rest form a regular grid. Reads as curated rather than catalogued,
    which is the point of a different *structure*, not just a different colour.
--}}
@php
    $s      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $cols   = $s['columns'] ?? 3;
    $items  = array_values($s['items'] ?? []);
@endphp
<section id="{{ $s['id'] ?? 'services' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $s['heading'] ?? null,
            'subheading' => $s['subheading'] ?? null,
        ])

        <div class="lp-bento" style="--cols:{{ $cols }}">
            @foreach ($items as $i => $item)
                @php($accent = 'var(--c-' . ($item['accent'] ?? 'primary') . ')')
                <div class="lp-card {{ $i === 0 ? 'lp-bento__lead' : '' }} {{ $reveal ? 'reveal' : '' }}"
                     style="{{ $reveal ? 'transition-delay:' . ($i % $cols) * (int) site('landing.stagger_ms', 100) . 'ms;' : '' }}">
                    @include('public.sections._icon', ['icon' => $item['icon'] ?? null, 'accent' => $accent])
                    <div>
                        <h3 class="font-semibold mb-3" style="font-size:var(--h3-size)">{{ $item['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color:var(--c-muted)">{{ $item['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
