{{-- resources/views/public/sections/stats/band.blade.php — full-bleed colour band --}}
@php
    $st       = $cfg;
    $reveal   = $anim['reveal'] ?? true;
    $counters = $anim['counters'] ?? true;
    $cols     = $st['columns'] ?? 4;
@endphp
<section id="{{ $st['id'] ?? 'stats' }}" class="lp-stats-band" style="padding-block:var(--section-gap)">
    <div class="landing-container grid grid-cols-2 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-8 text-center"
         style="--cols:{{ $cols }}">
        @foreach ($st['items'] ?? [] as $i => $s)
            @if ($s['visible'] ?? true)
                <div class="flex flex-col gap-2 {{ $reveal ? 'reveal' : '' }}"
                     style="transition-delay:{{ $i * (int) site('landing.stagger_ms', 100) }}ms">
                    <span class="lp-stat__value">
                        <span @if($counters) data-counter="{{ $s['value'] }}" @endif>{{ persian_digits($s['value']) }}</span>{{ $s['suffix'] ?? '' }}
                    </span>
                    <span class="lp-stat__label">{{ $s['label'] }}</span>
                </div>
            @endif
        @endforeach
    </div>
</section>
