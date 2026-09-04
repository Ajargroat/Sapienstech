{{-- resources/views/public/sections/stats/default.blade.php — counter grid --}}
@php
    $st       = $cfg;
    $reveal   = $anim['reveal'] ?? true;
    // This switch existed in config but was never read, so the counters
    // animated even with `animations.counters => false`.
    $counters = $anim['counters'] ?? true;
    $cols     = $st['columns'] ?? 4;
@endphp
<section id="{{ $st['id'] ?? 'stats' }}" class="lp-section">
    <div class="landing-container grid grid-cols-2 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-8 text-center"
         style="--cols:{{ $cols }}">
        @foreach ($st['items'] ?? [] as $i => $s)
            @if ($s['visible'] ?? true)
                <div class="flex flex-col gap-2 {{ $reveal ? 'reveal' : '' }}"
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
