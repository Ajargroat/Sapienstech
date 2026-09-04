{{--
    resources/views/public/sections/services/numbered-list.blade.php

    Services as a numbered manifesto rather than a card grid: rules instead of
    boxes, ordinal instead of icon. Same config, completely different page.
--}}
@php
    $s      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $cols   = max(1, (int) ($s['columns'] ?? 1));
@endphp
<section id="{{ $s['id'] ?? 'services' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $s['heading'] ?? null,
            'subheading' => $s['subheading'] ?? null,
        ])

        <ol class="lp-numbered list-none p-0 m-0 grid gap-0"
            style="grid-template-columns:repeat(var(--cols),minmax(0,1fr));--cols:{{ $cols }}">
            @foreach ($s['items'] ?? [] as $i => $item)
                @php($accent = 'var(--c-' . ($item['accent'] ?? 'primary') . ')')
                <li class="lp-numbered__row {{ $reveal ? 'reveal' : '' }}">
                    <span class="lp-numbered__num" style="color:{{ $accent }}">
                        {{ persian_digits(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) }}
                    </span>
                    <div>
                        <h3 style="font-size:var(--h3-size)" class="font-semibold mb-2">{{ $item['title'] }}</h3>
                        <p class="text-sm leading-relaxed" style="color:var(--c-muted)">{{ $item['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
