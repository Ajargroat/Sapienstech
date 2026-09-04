{{--
    resources/views/public/sections/faq/default.blade.php

    Accordion built on <details>, so it works with no JavaScript at all and is
    keyboard- and screen-reader-accessible for free.
--}}
@php
    $f      = $cfg;
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $f['id'] ?? 'faq' }}" class="lp-section">
    <div class="landing-container" style="max-width:var(--measure);margin-inline:auto">
        @include('public.sections._heading', [
            'heading'    => $f['heading'] ?? null,
            'subheading' => $f['subheading'] ?? null,
        ])

        <div class="flex flex-col gap-4">
            @foreach ($f['items'] ?? [] as $item)
                @if ($item['visible'] ?? true)
                    <details class="lp-faq {{ $reveal ? 'reveal' : '' }}">
                        <summary class="lp-faq__q">
                            {{ $item['question'] }}
                            <span class="lp-faq__chevron" aria-hidden="true"></span>
                        </summary>
                        <p class="lp-faq__a">{{ $item['answer'] }}</p>
                    </details>
                @endif
            @endforeach
        </div>
    </div>
</section>
