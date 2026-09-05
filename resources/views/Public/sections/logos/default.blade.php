{{--
    resources/views/public/sections/logos/default.blade.php

    Trust bar. A row of partner/school marks under the hero — the cheapest way
    to make a landing page look like a different company's landing page, and a
    section the original template had no equivalent of.
--}}
@php
    $g      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $items  = array_values(array_filter($g['items'] ?? [], static fn ($i) => $i['visible'] ?? true));
@endphp

@if ($items !== [])
<section id="{{ $g['id'] ?? 'logos' }}" class="lp-section lp-section--tight">
    <div class="landing-container">
        @if (!empty($g['heading']))
            <p class="text-center mb-8 text-sm" style="color:var(--c-subtle)">{{ $g['heading'] }}</p>
        @endif

        <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6 {{ $reveal ? 'reveal' : '' }}">
            @foreach ($items as $item)
                @if (!empty($item['image']))
                    <img src="{{ tenant_asset($item['image']) }}" alt="{{ $item['name'] ?? '' }}"
                         class="lp-logo-mark h-8 w-auto" loading="lazy">
                @else
                    <span class="lp-logo-text">{{ $item['name'] ?? '' }}</span>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif
