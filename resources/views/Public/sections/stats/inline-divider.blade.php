{{--
    resources/views/public/sections/stats/inline-divider.blade.php

    Stats as a single hairline-separated row instead of a grid of boxes — the
    editorial treatment, where the numbers sit in the text column rather than
    being promoted into cards.
--}}
@php
    $st       = $cfg;
    $reveal   = $anim['reveal'] ?? true;
    $counters = $anim['counters'] ?? true;
    $items    = array_values(array_filter($st['items'] ?? [], static fn ($s) => $s['visible'] ?? true));
@endphp

@if ($items !== [])
<section id="{{ $st['id'] ?? 'stats' }}" class="lp-section lp-section--tight">
    <div class="landing-container lp-inline-stats {{ $reveal ? 'reveal' : '' }}">
        @foreach ($items as $i => $s)
            <div class="lp-inline-stats__item">
                <span class="lp-inline-stats__value {{ !empty($s['gradient']) ? 'text-transparent bg-clip-text' : '' }}"
                      @if(!empty($s['gradient'])) style="background-image:var(--brand-gradient)" @endif>
                    <span @if($counters) data-counter="{{ $s['value'] }}" @endif>{{ persian_digits($s['value']) }}</span>{{ $s['suffix'] ?? '' }}
                </span>
                <span class="lp-inline-stats__label">{{ $s['label'] }}</span>
            </div>
        @endforeach
    </div>
</section>
@endif
