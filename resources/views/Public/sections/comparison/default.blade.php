{{--
    resources/views/public/sections/comparison/default.blade.php

    Feature table: this platform against the status quo. A genuinely different
    rhetorical device from a card grid, which is the point of having variants.
--}}
@php
    $c      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $cols   = $c['columns'] ?? ['' , 'روش سنتی', 'پلتفرم ما'];
@endphp
<section id="{{ $c['id'] ?? 'comparison' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $c['heading'] ?? null,
            'subheading' => $c['subheading'] ?? null,
        ])

        <div class="overflow-x-auto hide-scrollbar">
            <table class="lp-compare {{ $reveal ? 'reveal' : '' }}">
                <thead>
                    <tr>
                        @foreach ($cols as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($c['items'] ?? [] as $row)
                        @if ($row['visible'] ?? true)
                            <tr>
                                <th scope="row">{{ $row['label'] }}</th>
                                @foreach ($row['cells'] ?? [] as $cell)
                                    <td>
                                        @if ($cell === true)
                                            <span class="lp-compare__yes" aria-label="✅">✓</span>
                                        @elseif ($cell === false)
                                            <span class="lp-compare__no" aria-label="✖">—</span>
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
