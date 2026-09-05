{{--
    resources/views/public/sections/process/default.blade.php

    Numbered timeline: how the service actually works, step by step. Reads as a
    fundamentally different page from a card grid because the layout is a
    connected sequence rather than an unordered set.
--}}
@php
    $p      = $cfg;
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $p['id'] ?? 'process' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $p['heading'] ?? null,
            'subheading' => $p['subheading'] ?? null,
        ])

        <ol class="lp-process list-none p-0 m-0">
            @foreach ($p['items'] ?? [] as $i => $item)
                @if ($item['visible'] ?? true)
                    <li class="lp-process__step {{ $reveal ? 'reveal' : '' }}"
                        style="{{ $reveal ? 'transition-delay:' . $i * (int) site('landing.stagger_ms', 100) . 'ms;' : '' }}">
                        <span class="lp-process__num" aria-hidden="true">{{ persian_digits($i + 1) }}</span>
                        <div>
                            <h3 style="font-size:var(--h3-size)" class="font-semibold mb-2">{{ $item['title'] }}</h3>
                            <p class="text-sm leading-relaxed" style="color:var(--c-muted)">{{ $item['text'] }}</p>
                        </div>
                    </li>
                @endif
            @endforeach
        </ol>
    </div>
</section>
