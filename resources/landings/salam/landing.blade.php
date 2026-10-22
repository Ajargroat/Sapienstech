{{--
    Bespoke landing — آکادمی سلام (salam).

    The brutalist_mono tenant: hard 2px rules, a visible grid, oversized type,
    numbered rows, flat colour blocks and hard offset shadows. No blur, no
    easing, no reveal — the archetype's own motion contract.

    Structure is owned here; copy, numbers, grades, team members and articles
    come from the resolved tenant config (and the blog composer). No inline
    script and no inline handler: the FAQ is <details>, so nothing needs JS.
    Both theme partials appear exactly once.
--}}
@php
    $L    = $public['landing'] ?? [];
    $nav  = $public['nav'] ?? [];
    $foot = $public['footer'] ?? [];
    $adv  = $L['advisor'] ?? [];
    $blog = $L['blog']['items'] ?? [];

    $hrefOf = static function (array $b): string {
        $name = $b['route'] ?? null;

        if (is_string($name) && str_starts_with($name, '#')) {
            return $name;
        }

        if (is_string($name) && $name !== '') {
            try {
                return route($name);
            } catch (\Throwable) {
                return '#';
            }
        }

        $href = (string) ($b['href'] ?? '#');

        // Tenant config is admin-written, but a surface must not be able to
        // smuggle a script URL into a nav item: only relative, anchor, http(s)
        // and mailto links survive. Anything else degrades to a dead anchor.
        if ($href === '' || str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return $href === '' ? '#' : $href;
        }

        return preg_match('~^(https?:|mailto:|tel:)~i', $href) === 1 ? $href : '#';
    };

    $toneOf = static fn (array $b): string => ($b['style'] ?? 'solid') === 'ghost'
        ? 'sa-btn--ghost'
        : 'sa-btn--solid';

    $accentOf = static fn (?string $name): string => 'var(--c-'.str_replace('_', '-', $name ?: 'primary').')';

    $monogram = mb_substr((string) ($adv['name'] ?? $tenant['name']), 0, 1);
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}"
      data-numerals="{{ ($theme['i18n']['numerals'] ?? 'auto') === 'auto'
            ? (($tenant['locale'] ?? 'fa') === 'fa' ? 'fa' : 'en')
            : $theme['i18n']['numerals'] }}"
      data-animations="{{ ($theme['effects']['enable_animations'] ?? true) ? 'on' : 'off' }}"
      @include('partials.theme-attrs')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant['name'] }} | {{ $L['meta']['title'] ?? $tenant['short_name'] }}</title>
    <meta name="description" content="{{ $L['meta']['description'] ?? '' }}">
    <meta property="og:title" content="{{ $tenant['name'] }} | {{ $L['meta']['title'] ?? $tenant['short_name'] }}">
    <meta property="og:description" content="{{ $L['meta']['description'] ?? '' }}">
    @if (!empty($L['meta']['og_image']))
        <meta property="og:image" content="{{ tenant_asset($L['meta']['og_image']) }}">
    @endif
    @if (!empty($theme['brand']['src']))
        <link rel="icon" href="{{ tenant_asset($theme['brand']['src']) }}">
    @endif

    @include('partials.theme-vars')

    <style>
        /* ---------- ground + grid --------------------------------------- */
        .sa {
            margin: 0;
            min-block-size: 100dvh;
            background:
                linear-gradient(var(--c-border) 1px, transparent 1px) 0 0 / 100% 4rem,
                linear-gradient(90deg, var(--c-border) 1px, transparent 1px) 0 0 / 4rem 100%,
                var(--c-background);
            color: var(--c-text);
            font-family: var(--font-body);
            font-size: var(--body-size);
            line-height: var(--line-height);
        }
        .sa *, .sa *::before, .sa *::after { box-sizing: border-box; }
        .sa-wrap { max-inline-size: var(--content-max); margin-inline: auto; padding-inline: clamp(1rem, 4vw, 3rem); }
        .sa-mono { font-family: var(--font-mono, ui-monospace), monospace; }

        .sa-kicker {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            margin: 0;
            padding: .3rem .55rem;
            border: 2px solid var(--c-primary);
            color: var(--c-primary);
            font-size: .72rem;
            letter-spacing: .04em;
            line-height: 1.6;
        }

        .sa-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-block-size: 2.9rem;
            padding-inline: 1.15rem;
            border: 2px solid var(--c-text);
            font: inherit;
            font-size: .88rem;
            font-weight: 800;
            text-decoration: none;
        }
        .sa-btn--solid { background: var(--c-primary); border-color: var(--c-primary); color: var(--c-on-primary); box-shadow: 5px 5px 0 var(--c-text); }
        .sa-btn--ghost { background: transparent; color: var(--c-text); box-shadow: 5px 5px 0 var(--c-primary); }
        .sa-btn:hover { transform: translate(2px, 2px); box-shadow: 3px 3px 0 var(--c-text); }

        /* ---------- masthead ------------------------------------------- */
        .sa-top { border-block-end: 2px solid var(--c-text); background: var(--c-background); }
        .sa-top__in {
            display: flex;
            align-items: stretch;
            justify-content: space-between;
            gap: 1rem;
            min-block-size: 4.2rem;
        }
        .sa-brand {
            display: flex;
            align-items: center;
            gap: .65rem;
            color: var(--c-text);
            text-decoration: none;
            font-weight: 900;
            font-size: 1.05rem;
        }
        .sa-brand__mark {
            display: grid;
            place-items: center;
            inline-size: 2.3rem;
            block-size: 2.3rem;
            background: var(--c-primary);
            color: var(--c-on-primary);
            font-family: var(--font-heading);
            font-size: 1.05rem;
        }
        .sa-nav { display: flex; align-items: center; gap: 1.1rem; flex-wrap: wrap; }
        .sa-nav a { color: var(--c-text); font-size: .8rem; font-weight: 700; text-decoration: none; }
        .sa-nav a:hover { background: var(--c-primary); color: var(--c-on-primary); }
        .sa-nav__cta { padding: .65rem .85rem; border: 2px solid var(--c-primary); color: var(--c-primary) !important; }
        .sa-nav__cta:hover { background: var(--c-primary); color: var(--c-on-primary) !important; }

        /* ---------- hero ----------------------------------------------- */
        .sa-hero { padding-block: clamp(2.2rem, 6vw, 4.8rem); border-block-end: 2px solid var(--c-text); }
        .sa-hero__grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.6rem; }
        @media (min-width: 60rem) { .sa-hero__grid { grid-template-columns: minmax(0, 1.55fr) minmax(16rem, .45fr); } }
        .sa-hero h1 {
            margin: 1rem 0 0;
            font-family: var(--font-heading);
            font-size: clamp(2.7rem, 8vw, 6.4rem);
            font-weight: 900;
            line-height: 1.02;
            letter-spacing: -.02em;
        }
        .sa-hero h1 em { display: block; color: var(--c-primary); font-style: normal; }
        .sa-hero__sub { max-inline-size: 46rem; margin: 1.3rem 0 0; color: var(--c-muted); font-size: 1rem; line-height: 1.95; }
        .sa-actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-block-start: 1.7rem; }

        .sa-index { border: 2px solid var(--c-text); background: var(--c-surface); align-self: start; }
        .sa-index__h { padding: .6rem .8rem; border-block-end: 2px solid var(--c-text); font-size: .72rem; font-weight: 800; letter-spacing: .04em; }
        .sa-index__rows { margin: 0; padding: 0; list-style: none; }
        .sa-index__row { display: flex; align-items: baseline; justify-content: space-between; gap: .8rem; padding: .8rem; }
        .sa-index__row + .sa-index__row { border-block-start: 1px solid var(--c-border); }
        .sa-index__v { font-family: var(--font-heading); font-size: 2rem; font-weight: 900; color: var(--c-primary); }
        .sa-index__l { color: var(--c-muted); font-size: .74rem; text-align: end; }

        /* ---------- strip ---------------------------------------------- */
        .sa-strip { border-block-end: 2px solid var(--c-text); background: var(--c-primary); color: var(--c-on-primary); }
        .sa-strip__in { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.2rem; padding-block: .75rem; }
        .sa-strip__h { margin: 0; font-size: .72rem; font-weight: 800; }
        .sa-strip__item { font-weight: 900; font-size: .92rem; }
        .sa-strip__sep { opacity: .55; }

        /* ---------- sections ------------------------------------------- */
        .sa-sec { padding-block: clamp(2.4rem, 6vw, 4.3rem); border-block-end: 2px solid var(--c-text); }
        .sa-sec__head { display: grid; gap: .5rem; margin-block-end: 1.7rem; }
        .sa-sec__h { margin: 0; font-family: var(--font-heading); font-size: clamp(1.8rem, 4vw, 3.1rem); font-weight: 900; line-height: 1.08; }
        .sa-sec__sub { margin: 0; max-inline-size: 46rem; color: var(--c-muted); font-size: .88rem; line-height: 1.95; }

        /* ---------- advisor -------------------------------------------- */
        .sa-quote-lead { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.4rem; }
        @media (min-width: 58rem) { .sa-quote-lead { grid-template-columns: 15rem minmax(0, 1fr); } }
        .sa-quote-lead__mark {
            position: relative;
            display: grid;
            place-items: center;
            min-block-size: 15rem;
            border: 2px solid var(--c-text);
            background: var(--c-surface-alt);
            color: var(--c-primary);
            font-family: var(--font-heading);
            font-size: 4.5rem;
            font-weight: 900;
            line-height: 1;
        }
        /* The quote mark is a corner accent, not the plate's only content:
           a 15rem box holding one ” read as an empty panel. */
        .sa-quote-lead__mark::before {
            content: '\201D';
            position: absolute;
            inset-block-start: .1rem;
            inset-inline-start: .6rem;
            font-size: 2.4rem;
            opacity: .45;
            line-height: 1;
        }
        .sa-quote-lead__mono { font-size: clamp(3.4rem, 6vw, 4.8rem); }
        .sa-adv__name { margin: 0; font-family: var(--font-heading); font-size: clamp(1.8rem, 4vw, 3rem); font-weight: 900; }
        .sa-adv__tag { margin: .4rem 0 0; color: var(--c-primary); font-size: .86rem; font-weight: 700; }
        .sa-adv__bio { margin: 1rem 0 0; color: var(--c-muted); line-height: 2; max-inline-size: 54rem; }
        .sa-adv__stats { display: flex; flex-wrap: wrap; gap: 1rem 2rem; margin: 1.3rem 0 0; padding: 1rem 0 0; border-block-start: 2px solid var(--c-text); list-style: none; }
        .sa-adv__stat b { display: block; font-family: var(--font-heading); font-size: 1.5rem; font-weight: 900; color: var(--c-primary); }
        .sa-adv__stat span { color: var(--c-muted); font-size: .72rem; }

        /* ---------- process -------------------------------------------- */
        .sa-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 0; border: 2px solid var(--c-text); }
        .sa-step { padding: 1.1rem; border-inline-end: 2px solid var(--c-border); }
        .sa-step:last-child { border-inline-end: 0; }
        .sa-step__n { display: block; font-family: var(--font-heading); font-size: 2.6rem; font-weight: 900; color: var(--c-primary); line-height: 1; }
        .sa-step__t { margin: .8rem 0 0; font-size: 1rem; font-weight: 900; }
        .sa-step__x { margin: .4rem 0 0; color: var(--c-muted); font-size: .82rem; line-height: 1.9; }

        /* ---------- services ------------------------------------------- */
        .sa-svcs { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0 2rem; }
        @media (min-width: 60rem) { .sa-svcs { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .sa-svc { display: grid; grid-template-columns: 3rem minmax(0, 1fr); gap: .8rem; padding-block: 1.05rem; border-block-start: 1px solid var(--c-border); }
        .sa-svc__n { font-family: var(--font-heading); font-size: 1.1rem; font-weight: 900; }
        .sa-svc__t { margin: 0; font-size: .98rem; font-weight: 900; }
        .sa-svc__x { margin: .3rem 0 0; color: var(--c-muted); font-size: .83rem; line-height: 1.9; }

        /* ---------- comparison ----------------------------------------- */
        .sa-compare { inline-size: 100%; border-collapse: collapse; border: 2px solid var(--c-text); }
        .sa-compare th, .sa-compare td { padding: .85rem .7rem; border: 1px solid var(--c-border); text-align: start; font-size: .82rem; vertical-align: top; }
        .sa-compare thead th { background: var(--c-surface-alt); font-size: .78rem; font-weight: 900; }
        .sa-compare tbody tr:nth-child(even) { background: color-mix(in srgb, var(--c-surface) 65%, transparent); }
        .sa-compare .sa-yes { color: var(--c-primary); font-weight: 900; }
        .sa-compare .sa-no { color: var(--c-muted); }

        /* ---------- stats / quotes ------------------------------------- */
        .sa-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); border: 2px solid var(--c-text); }
        .sa-stat { padding: 1.1rem; border-inline-end: 2px solid var(--c-border); }
        .sa-stat:last-child { border-inline-end: 0; }
        .sa-stat b { display: block; font-family: var(--font-heading); font-size: clamp(2.1rem, 5vw, 3.4rem); font-weight: 900; line-height: 1; }
        .sa-stat--grad b { color: var(--c-primary); }
        .sa-stat span { display: block; margin-block-start: .5rem; color: var(--c-muted); font-size: .76rem; }

        .sa-quotes { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; }
        .sa-quote { margin: 0; border: 2px solid var(--c-text); background: var(--c-surface); }
        .sa-quote__bar { block-size: .45rem; background: var(--c-primary); }
        .sa-quote__in { padding: 1.1rem; }
        .sa-quote__x { margin: 0; font-size: .86rem; line-height: 2; }
        .sa-quote__who { display: flex; align-items: center; gap: .7rem; margin-block-start: 1rem; padding-block-start: .8rem; border-block-start: 1px solid var(--c-border); }
        .sa-quote__ini { display: grid; place-items: center; inline-size: 2.3rem; block-size: 2.3rem; background: var(--c-primary); color: var(--c-on-primary); font-size: .8rem; font-weight: 900; }
        .sa-quote__n { display: block; font-size: .82rem; font-weight: 900; }
        .sa-quote__r { display: block; color: var(--c-muted); font-size: .72rem; }

        /* ---------- faq / blocks / blog -------------------------------- */
        .sa-faq { border-block-start: 2px solid var(--c-text); }
        .sa-faq details { border-block-end: 1px solid var(--c-border); }
        .sa-faq summary { display: flex; justify-content: space-between; gap: 1rem; padding-block: 1rem; cursor: pointer; font-size: .92rem; font-weight: 900; }
        .sa-faq summary::after { content: '+'; color: var(--c-primary); font-size: 1.25rem; }
        .sa-faq details[open] summary::after { content: '−'; }
        .sa-faq p { margin: 0; padding-block-end: 1.1rem; color: var(--c-muted); font-size: .84rem; line-height: 2; }

        .sa-blocks { display: grid; gap: 1rem; }
        .sa-blocks__head { text-align: center; max-inline-size: 44rem; margin-inline: auto; }
        .sa-blocks__h { margin: 0; font-family: var(--font-heading); font-size: clamp(1.6rem, 3.5vw, 2.4rem); font-weight: 900; }
        .sa-blocks__x { margin: .5rem 0 0; color: var(--c-muted); font-size: .86rem; line-height: 1.95; }
        .sa-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem; }
        .sa-card { border: 2px solid var(--c-text); background: var(--c-surface); padding: 1.1rem; }
        .sa-card__t { margin: 0; font-size: 1.02rem; font-weight: 900; }
        .sa-card__x { margin: .5rem 0 0; color: var(--c-muted); font-size: .83rem; line-height: 1.9; }
        .sa-divider { border: 0; border-block-start: 2px solid var(--c-text); margin: 0; }

        .sa-blog { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; }
        .sa-post { display: flex; flex-direction: column; gap: .7rem; padding: 1rem; border: 2px solid var(--c-text); background: var(--c-surface); color: inherit; text-decoration: none; }
        .sa-post:hover { background: var(--c-primary); color: var(--c-on-primary); }
        .sa-post__img { inline-size: 100%; aspect-ratio: 16 / 10; object-fit: cover; border: 1px solid var(--c-border); }
        .sa-post__t { margin: 0; font-family: var(--font-heading); font-size: 1.08rem; font-weight: 900; }
        .sa-post__x { margin: 0; font-size: .8rem; line-height: 1.9; }

        /* ---------- cta / footer --------------------------------------- */
        .sa-cta { border: 2px solid var(--c-text); background: var(--c-primary); color: var(--c-on-primary); padding: clamp(1.4rem, 4vw, 2.6rem); box-shadow: 10px 10px 0 var(--c-text); }
        .sa-cta__h { margin: 0; font-family: var(--font-heading); font-size: clamp(1.7rem, 4vw, 2.9rem); font-weight: 900; }
        .sa-cta__x { margin: .6rem 0 0; line-height: 1.95; max-inline-size: 48rem; }
        .sa-cta .sa-btn { border-color: var(--c-on-primary); box-shadow: 5px 5px 0 var(--c-text); }
        .sa-cta .sa-btn--ghost { color: var(--c-on-primary); background: transparent; }

        .sa-foot { padding-block: 2rem 1.2rem; }
        .sa-foot__grid { display: grid; grid-template-columns: minmax(0, 1.4fr) repeat(auto-fit, minmax(10rem, .6fr)); gap: 1.5rem; }
        .sa-foot__h { margin: 0 0 .6rem; font-size: .8rem; font-weight: 900; }
        .sa-foot__blurb { max-inline-size: 36rem; color: var(--c-muted); font-size: .82rem; line-height: 1.95; }
        .sa-foot__links { display: grid; gap: .4rem; }
        .sa-foot__links a { color: var(--c-muted); font-size: .78rem; text-decoration: none; }
        .sa-foot__links a:hover { color: var(--c-primary); text-decoration: underline; }
        .sa-foot__social { display: flex; gap: .7rem; margin-block-start: .8rem; }
        .sa-foot__social a { color: var(--c-muted); font-size: .74rem; text-decoration: none; }
        .sa-foot__base { display: flex; justify-content: space-between; gap: 1rem; margin-block-start: 1.6rem; padding-block-start: .8rem; border-block-start: 1px solid var(--c-border); color: var(--c-muted); font-size: .72rem; }
    </style>
</head>
<body class="sa">
    <header class="sa-top">
        <div class="sa-wrap">
            <div class="sa-top__in">
                <a class="sa-brand" href="{{ route('home') }}">
                    <span class="sa-brand__mark" aria-hidden="true">{{ $monogram }}</span>
                    <span>{{ $tenant['name'] }}</span>
                </a>
                <nav class="sa-nav" aria-label="{{ $tenant['name'] }}">
                    @foreach (($nav['links'] ?? []) as $link)
                        @if (($link['visible'] ?? true))
                            <a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                        @endif
                    @endforeach
                    @if (($nav['cta']['visible'] ?? true) && !empty($nav['cta']['label']))
                        <a class="sa-nav__cta" href="{{ $hrefOf($nav['cta']) }}">{{ $nav['cta']['label'] }}</a>
                    @endif
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="sa-hero">
            <div class="sa-wrap">
                <div class="sa-hero__grid">
                    <div>
                        @if (!empty($L['hero']['eyebrow']))
                            <p class="sa-kicker">{{ $L['hero']['eyebrow'] }}</p>
                        @endif
                        <h1>
                            {{ $L['hero']['title_line1'] ?? '' }}
                            <em>{{ $L['hero']['title_line2'] ?? $tenant['name'] }}</em>
                        </h1>
                        <p class="sa-hero__sub">{{ $L['hero']['subtitle'] ?? '' }}</p>
                        <div class="sa-actions">
                            @foreach (($L['hero']['buttons'] ?? []) as $b)
                                @if (($b['visible'] ?? true))
                                    <a class="sa-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @if (!empty($L['stats']['items']))
                        <aside class="sa-index" aria-label="آمار آکادمی">
                            <div class="sa-index__h">در یک نگاه</div>
                            <ul class="sa-index__rows">
                                @foreach ($L['stats']['items'] as $stat)
                                    @continue(! ($stat['visible'] ?? true))
                                    <li class="sa-index__row">
                                        <span class="sa-index__v">{{ $stat['value'] ?? '' }}{{ $stat['suffix'] ?? '' }}</span>
                                        <span class="sa-index__l">{{ $stat['label'] ?? '' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </aside>
                    @endif
                </div>
            </div>
        </section>

        @if (!empty($L['logos']['items']))
            <div class="sa-strip">
                <div class="sa-wrap">
                    <div class="sa-strip__in">
                        @if (!empty($L['logos']['heading']))
                            <p class="sa-strip__h">{{ $L['logos']['heading'] }}</p>
                        @endif
                        @foreach ($L['logos']['items'] as $item)
                            @if (($item['visible'] ?? true))
                                <span class="sa-strip__item">{{ $item['name'] ?? '' }}</span>
                                @if (! $loop->last)<span class="sa-strip__sep" aria-hidden="true">+</span>@endif
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if (!empty($adv['name']))
            <section class="sa-sec" id="{{ $adv['id'] ?? 'about' }}">
                <div class="sa-wrap">
                    <div class="sa-quote-lead">
                        <div class="sa-quote-lead__mark" aria-hidden="true">
                            <span class="sa-quote-lead__mono">{{ $monogram }}</span>
                        </div>
                        <div>
                            <p class="sa-kicker">{{ $adv['eyebrow']['label'] ?? 'تیم مشاوره' }}</p>
                            <h2 class="sa-adv__name">{{ $adv['name'] }}</h2>
                            <p class="sa-adv__tag">{{ $adv['tagline'] ?? '' }}</p>
                            <p class="sa-adv__bio">{{ $adv['bio'] ?? '' }}</p>
                            @if (!empty($adv['stats']))
                                <ul class="sa-adv__stats">
                                    @foreach ($adv['stats'] as $stat)
                                        <li class="sa-adv__stat">
                                            <b>{{ $stat['value'] ?? '' }}</b>
                                            <span>{{ $stat['label'] ?? '' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (!empty($adv['buttons']))
                                <div class="sa-actions">
                                    @foreach ($adv['buttons'] as $b)
                                        @if (($b['visible'] ?? true))
                                            <a class="sa-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['process']['items']))
            <section class="sa-sec" id="{{ $L['process']['id'] ?? 'process' }}">
                <div class="sa-wrap">
                    <div class="sa-sec__head">
                        <h2 class="sa-sec__h">{{ $L['process']['heading'] ?? 'مسیر مشاوره' }}</h2>
                        <p class="sa-sec__sub">{{ $L['process']['subheading'] ?? '' }}</p>
                    </div>
                    <ol class="sa-steps">
                        @foreach ($L['process']['items'] as $step)
                            @continue(! ($step['visible'] ?? true))
                            <li class="sa-step">
                                <span class="sa-step__n">{{ persian_digits(str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)) }}</span>
                                <h3 class="sa-step__t">{{ $step['title'] ?? '' }}</h3>
                                <p class="sa-step__x">{{ $step['text'] ?? '' }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif

        @if (!empty($L['services']['items']))
            <section class="sa-sec" id="{{ $L['services']['id'] ?? 'services' }}">
                <div class="sa-wrap">
                    <div class="sa-sec__head">
                        <h2 class="sa-sec__h">{{ $L['services']['heading'] ?? 'خدمات' }}</h2>
                        <p class="sa-sec__sub">{{ $L['services']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="sa-svcs">
                        @foreach ($L['services']['items'] as $i => $svc)
                            @continue(! ($svc['visible'] ?? true))
                            <article class="sa-svc">
                                <span class="sa-svc__n" style="color: {{ $accentOf($svc['accent'] ?? null) }}">{{ persian_digits(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                                <div>
                                    <h3 class="sa-svc__t">{{ $svc['title'] ?? '' }}</h3>
                                    <p class="sa-svc__x">{{ $svc['text'] ?? '' }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['comparison']['items']))
            <section class="sa-sec" id="{{ $L['comparison']['id'] ?? 'comparison' }}">
                <div class="sa-wrap">
                    <div class="sa-sec__head">
                        <h2 class="sa-sec__h">{{ $L['comparison']['heading'] ?? 'مقایسه' }}</h2>
                        <p class="sa-sec__sub">{{ $L['comparison']['subheading'] ?? '' }}</p>
                    </div>
                    <table class="sa-compare">
                        <thead>
                            <tr>
                                @foreach (($L['comparison']['columns'] ?? []) as $col)
                                    <th scope="col">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($L['comparison']['items'] as $row)
                                @continue(! ($row['visible'] ?? true))
                                <tr>
                                    <th scope="row">{{ $row['label'] ?? '' }}</th>
                                    @foreach (($row['cells'] ?? []) as $cell)
                                        <td>
                                            @if (is_bool($cell))
                                                <span class="{{ $cell ? 'sa-yes' : 'sa-no' }}">{{ $cell ? '✓' : '—' }}</span>
                                            @else
                                                {{ $cell }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if (!empty($L['stats']['items']))
            <section class="sa-sec">
                <div class="sa-wrap">
                    <div class="sa-stats">
                        @foreach ($L['stats']['items'] as $stat)
                            @continue(! ($stat['visible'] ?? true))
                            <div class="sa-stat {{ ($stat['gradient'] ?? false) ? 'sa-stat--grad' : '' }}">
                                <b>{{ $stat['value'] ?? '' }}{{ $stat['suffix'] ?? '' }}</b>
                                <span>{{ $stat['label'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['testimonials']['items']))
            <section class="sa-sec" id="{{ $L['testimonials']['id'] ?? 'testimonials' }}">
                <div class="sa-wrap">
                    <div class="sa-sec__head">
                        <h2 class="sa-sec__h">{{ $L['testimonials']['heading'] ?? 'از زبان خودشان' }}</h2>
                        <p class="sa-sec__sub">{{ $L['testimonials']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="sa-quotes">
                        @foreach ($L['testimonials']['items'] as $q)
                            @continue(! ($q['visible'] ?? true))
                            <figure class="sa-quote">
                                <div class="sa-quote__bar" aria-hidden="true"></div>
                                <div class="sa-quote__in">
                                    <blockquote class="sa-quote__x">{{ $q['text'] ?? '' }}</blockquote>
                                    <figcaption class="sa-quote__who">
                                        <span class="sa-quote__ini" aria-hidden="true">{{ $q['initials'] ?? '' }}</span>
                                        <span>
                                            <span class="sa-quote__n">{{ $q['name'] ?? '' }}</span>
                                            <span class="sa-quote__r">{{ $q['result'] ?? '' }}</span>
                                        </span>
                                    </figcaption>
                                </div>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['faq']['items']))
            <section class="sa-sec" id="{{ $L['faq']['id'] ?? 'faq' }}">
                <div class="sa-wrap">
                    <div class="sa-sec__head">
                        <h2 class="sa-sec__h">{{ $L['faq']['heading'] ?? 'سؤال‌های پرتکرار' }}</h2>
                        <p class="sa-sec__sub">{{ $L['faq']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="sa-faq">
                        @foreach ($L['faq']['items'] as $f)
                            @continue(! ($f['visible'] ?? true))
                            <details>
                                <summary>{{ $f['question'] ?? '' }}</summary>
                                <p>{{ $f['answer'] ?? '' }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['blocks']['items']))
            @php
                $blocks = collect($L['blocks']['items'])
                    ->filter(static fn ($b) => (bool) ($b['visible'] ?? true))
                    ->values();
                $blockHead = $blocks->firstWhere('type', 'heading');
                $blockCards = $blocks->where('type', 'card')->values();
                $blockButtons = $blocks->where('type', 'button')->values();
                $blockRules = $blocks->where('type', 'divider')->count();
            @endphp
            <section class="sa-sec" id="blocks">
                <div class="sa-wrap">
                    <div class="sa-blocks">
                        @if ($blockHead)
                            <div class="sa-blocks__head">
                                <h2 class="sa-blocks__h">{{ $blockHead['title'] ?? '' }}</h2>
                                @if (!empty($blockHead['text']))
                                    <p class="sa-blocks__x">{{ $blockHead['text'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if ($blockCards->isNotEmpty())
                            <div class="sa-cards">
                                @foreach ($blockCards as $card)
                                    <article class="sa-card">
                                        <h3 class="sa-card__t">{{ $card['title'] ?? '' }}</h3>
                                        <p class="sa-card__x">{{ $card['text'] ?? '' }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if ($blockButtons->isNotEmpty())
                            <div class="sa-actions">
                                @foreach ($blockButtons as $block)
                                    <a class="sa-btn {{ $toneOf($block) }}" href="{{ $hrefOf($block) }}">{{ $block['title'] ?? '' }}</a>
                                @endforeach
                            </div>
                        @endif

                        @for ($i = 0; $i < $blockRules; $i++)
                            <hr class="sa-divider">
                        @endfor
                    </div>
                </div>
            </section>
        @endif

        @if ($blog !== [])
            <section class="sa-sec" id="{{ $L['blog']['id'] ?? 'blog' }}">
                <div class="sa-wrap">
                    <div class="sa-sec__head">
                        <h2 class="sa-sec__h">{{ $L['blog']['heading'] ?? 'وبلاگ' }}</h2>
                        <p class="sa-sec__sub">{{ $L['blog']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="sa-blog">
                        @foreach ($blog as $post)
                            @continue(! ($post['visible'] ?? true))
                            <a class="sa-post" href="{{ $post['url'] ?? '#' }}">
                                @if (!empty($post['image']))
                                    <img class="sa-post__img" src="{{ tenant_asset($post['image']) }}" alt="" loading="lazy">
                                @endif
                                <h3 class="sa-post__t">{{ $post['title'] ?? '' }}</h3>
                                <p class="sa-post__x">{{ $post['excerpt'] ?? '' }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['cta']['heading']) || !empty($L['cta']['text']))
            <section class="sa-sec" id="cta">
                <div class="sa-wrap">
                    <div class="sa-cta">
                        <h2 class="sa-cta__h">{{ $L['cta']['heading'] ?? '' }}</h2>
                        @if (!empty($L['cta']['text']))
                            <p class="sa-cta__x">{{ $L['cta']['text'] }}</p>
                        @endif
                        <div class="sa-actions">
                            @foreach (($L['cta']['buttons'] ?? []) as $b)
                                @if (($b['visible'] ?? true))
                                    <a class="sa-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </main>

    <footer class="sa-foot">
        <div class="sa-wrap">
            <div class="sa-foot__grid">
                <div>
                    <p class="sa-foot__h">{{ $tenant['name'] }}</p>
                    <p class="sa-foot__blurb">{{ $foot['blurb'] ?? '' }}</p>
                    @if (!empty($foot['social']))
                        <div class="sa-foot__social">
                            @foreach ($foot['social'] as $s)
                                @if (($s['visible'] ?? true))
                                    <a href="{{ $s['url'] ?? '#' }}" rel="noopener noreferrer">{{ $s['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                @foreach (($foot['columns'] ?? []) as $col)
                    <nav aria-label="{{ $col['title'] ?? '' }}">
                        <p class="sa-foot__h">{{ $col['title'] ?? '' }}</p>
                        <div class="sa-foot__links">
                            @foreach (($col['links'] ?? []) as $link)
                                <a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                            @endforeach
                        </div>
                    </nav>
                @endforeach
            </div>
            <div class="sa-foot__base">
                <span>{{ $tenant['name'] }}</span>
                <span>{{ date('Y') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
