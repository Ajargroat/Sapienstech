{{--
    resources/views/public/sections/plans/default.blade.php

    The priced plans («طرح‌ها»): a card grid where a price is *determined*, so
    the join form below can ask "which plan?" instead of asking the visitor to
    name an amount. The card's button anchors to the join form rather than
    posting anything, so choosing a plan is not a commitment — the visitor
    still declares their transfer there.

    Prices are display-only here. The authoritative copy is read server-side
    from this same config when a request is submitted (App\Support\Plans), so a
    crafted POST cannot name its own price.

    Renders nothing when the tenant has no visible plans, which is what keeps
    the section a verified no-op for every tenant that has not opted in.
--}}
@php
    $p      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $cols   = max(1, (int) ($p['columns'] ?? 3));
    $base   = 'public.landing.plans.items';

    // Keys are preserved so each card keeps the studio identity of the row it
    // came from; `featured` is read from the row itself.
    $visible = array_filter($p['items'] ?? [], static fn ($i) => ($i['visible'] ?? true) && ($i['name'] ?? '') !== '');
    $items   = array_values($visible);
    $slugs   = array_keys($visible);

    $ctaHref  = $p['cta_href'] ?? '#join';
    $ctaLabel = $p['cta_label'] ?? 'انتخاب این طرح';
@endphp

@if ($items !== [])
<section id="{{ $p['id'] ?? 'plans' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $p['heading'] ?? null,
            'subheading' => $p['subheading'] ?? null,
            'path'       => 'public.landing.plans',
        ])

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-[var(--grid-gap)] items-stretch"
             style="--cols:{{ $cols }}">
            @foreach ($items as $i => $plan)
                @php
                    $featured = (bool) ($plan['featured'] ?? false);
                    $price    = max(0, (int) ($plan['price'] ?? 0));
                    $features = \App\Support\Plans::features($plan);
                @endphp

                <article class="lp-card lp-plan {{ $featured ? 'lp-plan--featured lp-card--featured' : '' }} {{ $reveal ? 'reveal' : '' }}"
                         data-studio-path="{{ $base }}.{{ $slugs[$i] }}"
                         style="{{ $reveal ? 'transition-delay:' . ($i % $cols) * (int) site('landing.stagger_ms', 100) . 'ms;' : '' }}">

                    @if ($featured)
                        <span class="lp-plan__badge">{{ $plan['badge'] ?? 'پیشنهاد ما' }}</span>
                    @endif

                    <h3 class="lp-plan__name" style="font-size:var(--h3-size)">{{ $plan['name'] }}</h3>

                    @if (!empty($plan['period']))
                        <p class="lp-plan__period" style="color:var(--c-muted)">{{ $plan['period'] }}</p>
                    @endif

                    <p class="lp-plan__price" style="color:var(--c-heading)">
                        <span class="lp-plan__amount">{{ persian_digits(number_format($price)) }}</span>
                        <span class="lp-plan__unit">تومان</span>
                    </p>

                    @if ($features !== [])
                        <ul class="lp-plan__features">
                            @foreach ($features as $feature)
                                <li>
                                    <i class="fa-solid fa-check lp-plan__check" aria-hidden="true"></i>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @include('public.sections._button', [
                        'label' => $plan['cta_label'] ?? $ctaLabel,
                        'href'  => $ctaHref,
                        'tone'  => $featured ? 'primary' : 'outline',
                        'block' => true,
                        'path'  => $base.'.'.$slugs[$i],
                    ])
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
