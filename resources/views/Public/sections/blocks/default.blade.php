{{--
    resources/views/public/sections/blocks/default.blade.php

    The free-composition section: a vertical stack of tenant-authored blocks.
    Each row's `type` decides which keys it carries (see the `blocks` group in
    config/studio.php); normalization guarantees a row only holds cells its
    type owns, so templates can read them without cross-checking. An unknown
    or missing type renders nothing rather than breaking the page.

    Headings, buttons and icon chips are the shared primitives — a block
    section composed in the studio still looks like the rest of the tenant's
    page because it renders through the same partials.
--}}
@php
    $b      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $items  = array_values(array_filter($b['items'] ?? [], static fn ($i) => $i['visible'] ?? true));

    $aligns  = ['start' => 'text-start', 'center' => 'text-center', 'end' => 'text-end'];
    $spacers = ['sm' => '2rem', 'md' => '4rem', 'lg' => '7rem'];
@endphp

@if ($items !== [])
<section id="{{ $b['id'] ?? 'blocks' }}" class="lp-section">
    <div class="landing-container lp-blocks">
        @foreach ($items as $blk)
            @php($type = $blk['type'] ?? '')

            @switch($type)
                @case('heading')
                    @if (!empty($blk['title']))
                        @include('public.sections._heading', [
                            'heading'    => $blk['title'],
                            'subheading' => $blk['text'] ?? null,
                            'align'      => $blk['align'] ?? null,
                        ])
                    @endif
                    @break

                @case('text')
                    @if (!empty($blk['text']))
                        <p class="lp-blocks__text {{ $aligns[$blk['align'] ?? ''] ?? '' }} {{ ($blk['align'] ?? '') === 'center' ? 'is-center' : '' }} {{ $reveal ? 'reveal' : '' }}">{{ $blk['text'] }}</p>
                    @endif
                    @break

                @case('button')
                    @if (!empty($blk['title']))
                        {{-- 'block' => false: _button reads a $block variable as its
                             full-width flag, and @include would leak this loop's
                             row into it. --}}
                        <div class="lp-blocks__buttons {{ $reveal ? 'reveal' : '' }}">
                            @include('public.sections._button', [
                                'label' => $blk['title'],
                                'href'  => $blk['href'] ?? '#',
                                'tone'  => $blk['style'] ?? null,
                                'icon'  => $blk['icon'] ?? null,
                                'block' => false,
                            ])
                        </div>
                    @endif
                    @break

                @case('card')
                    @if (!empty($blk['title']))
                        @php($accent = 'var(--c-' . ($blk['accent'] ?? 'primary') . ')')
                        <div class="lp-card lp-blocks__card {{ $reveal ? 'reveal' : '' }}">
                            @include('public.sections._icon', ['icon' => $blk['fa_icon'] ?? null, 'accent' => $accent])
                            <h3 class="font-semibold mb-3" style="font-size:var(--h3-size)">{{ $blk['title'] }}</h3>
                            @if (!empty($blk['text']))
                                <p class="text-sm leading-relaxed" style="color:var(--c-muted)">{{ $blk['text'] }}</p>
                            @endif
                        </div>
                    @endif
                    @break

                @case('image')
                    @if (!empty($blk['src']))
                        <figure class="lp-blocks__image {{ $reveal ? 'reveal' : '' }}">
                            <img src="{{ preg_match('#^(https?://|/)#', $blk['src']) ? $blk['src'] : tenant_asset($blk['src']) }}"
                                 alt="{{ $blk['text'] ?? '' }}" loading="lazy">
                            @if (!empty($blk['text']))
                                <figcaption>{{ $blk['text'] }}</figcaption>
                            @endif
                        </figure>
                    @endif
                    @break

                @case('spacer')
                    <div class="lp-blocks__spacer" style="height:{{ $spacers[$blk['size'] ?? ''] ?? $spacers['md'] }}" aria-hidden="true"></div>
                    @break

                @case('divider')
                    <hr class="lp-blocks__divider">
                    @break
            @endswitch
        @endforeach
    </div>
</section>
@endif
