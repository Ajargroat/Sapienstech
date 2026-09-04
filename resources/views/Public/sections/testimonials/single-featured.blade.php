{{--
    resources/views/public/sections/testimonials/single-featured.blade.php

    One large quote at a time with the rest as selectable names. A different
    reading rhythm from the grid: the visitor dwells on one story instead of
    scanning three.
--}}
@php
    $tm     = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $items  = array_values(array_filter($tm['items'] ?? [], static fn ($i) => $i['visible'] ?? true));
    $first  = $items[0] ?? null;
@endphp

@if ($first)
<section id="{{ $tm['id'] ?? 'testimonials' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $tm['heading'] ?? null,
            'subheading' => $tm['subheading'] ?? null,
        ])

        <div class="lp-featured {{ $reveal ? 'reveal' : '' }}">
            @if (($q = site('decoration.quote_mark', 'none')) !== 'none')
                <span class="lp-featured__mark" data-quote="{{ $q }}" aria-hidden="true"></span>
            @endif

            <blockquote class="lp-featured__text" style="color:var(--c-text)">
                {{ $first['text'] }}
            </blockquote>

            <footer class="lp-featured__meta">
                <span class="lp-avatar"
                      style="background:linear-gradient(45deg, var(--c-{{ $first['from'] ?? 'primary' }}), var(--c-{{ $first['to'] ?? 'secondary' }}))">
                    {{ $first['initials'] }}
                </span>
                <span>
                    <strong class="block">{{ $first['name'] }}</strong>
                    <span class="text-sm" style="color:var(--c-primary)">{{ $first['result'] }}</span>
                </span>
            </footer>
        </div>

        @if (count($items) > 1)
            <div class="flex flex-wrap justify-center gap-3 mt-10">
                @foreach (array_slice($items, 1) as $item)
                    <span class="lp-chip">{{ $item['name'] }} · {{ $item['result'] }}</span>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
