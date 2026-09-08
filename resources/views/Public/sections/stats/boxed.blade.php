{{--
    resources/views/public/sections/stats/boxed.blade.php — one card per number

    Same counters as the default grid, but each stat is framed. Suits themes
    that want the numbers to read as separate claims rather than one band.
--}}
@php
    $st       = $cfg;
    $reveal   = $anim['reveal'] ?? true;
    $counters = $anim['counters'] ?? true;
    $cols     = $st['columns'] ?? 4;
@endphp
<section id="{{ $st['id'] ?? 'stats' }}" class="lp-section">
    <div class="landing-container grid grid-cols-2 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-[var(--grid-gap)] text-center"
         style="--cols:{{ $cols }}">
        @foreach ($st['items'] ?? [] as $i => $s)
            @if ($s['visible'] ?? true)
                <div class="lp-card {{ $reveal ? 'reveal' : '' }}"
                     style="transition-delay:{{ $i * (int) site('landing.stagger_ms', 100) }}ms">
                    <span class="lp-stat__value {{ !empty($s['gradient']) ? 'text-transparent bg-clip-text bg-linear-to-l from-(--c-primary) to-(--c-secondary)' : '' }}">
                        <span @if($counters) data-counter="{{ $s['value'] }}" @endif>{{ persian_digits($s['value']) }}</span>{{ $s['suffix'] ?? '' }}
                    </span>
                    <span class="lp-stat__label" style="color:var(--c-muted)">{{ $s['label'] }}</span>
                </div>
            @endif
        @endforeach
    </div>
</section>
