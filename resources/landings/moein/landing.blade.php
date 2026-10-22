{{--
    Bespoke landing — مؤسسه معین (moein).

    The editorial_serif tenant, written as a printed prospectus rather than a
    platform page: warm paper, a masthead rule, Amiri display headings, numbered
    process and service rows, and a colophon footer. Structure is owned here;
    every word, number and portrait comes from the resolved tenant config or
    from the tenant's own rows (TenantRoster) — nothing is invented.

    Token contract: every colour is var(--c-*) or color-mix() over it. No inline
    script and no inline handler; the FAQ is <details>, so the page needs no JS.
    Both theme partials appear exactly once.
--}}
@php
    $L    = $public['landing'] ?? [];
    $nav  = $public['nav'] ?? [];
    $foot = $public['footer'] ?? [];
    $adv  = $L['advisor'] ?? [];

    // A button may carry a route NAME ({route: 'login'}) or a URL ({href: …}).
    // A '#'-prefixed route value is an in-page destination.
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

    $toneOf = static fn (array $b): string => match ($b['style'] ?? 'primary') {
        'ghost', 'outline' => 'me-btn--ghost',
        default => 'me-btn--solid',
    };

    // Config accented names use underscores (`accent_teal`) while the token
    // sheet names them with hyphens (`--c-accent-teal`).
    $accentOf = static fn (?string $name): string => 'var(--c-'.str_replace('_', '-', $name ?: 'primary').')';

    $metrics    = \App\Support\TenantRoster::resolveMetrics($L['stats']['items'] ?? [], tenant());
    $faculty    = \App\Support\TenantRoster::staff(tenant(), (int) ($L['faculty']['limit'] ?? 9));
    $classrooms = \App\Support\TenantRoster::classrooms(tenant());
    $blog       = $L['blog']['items'] ?? [];

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
        /* ---------- paper ground + rules --------------------------------- */
        .me {
            margin: 0;
            background: var(--c-background);
            color: var(--c-text);
            font-family: var(--font-body);
            font-size: var(--body-size);
            line-height: var(--line-height);
            min-block-size: 100dvh;
        }
        .me *, .me *::before, .me *::after { box-sizing: border-box; }

        .me-wrap {
            max-inline-size: var(--content-max);
            margin-inline: auto;
            padding-inline: clamp(1.1rem, 4vw, 3.5rem);
        }

        .me-rule { border: 0; border-block-start: 1px solid var(--c-border); margin: 0; }

        .me-kicker {
            margin: 0;
            color: var(--c-primary);
            font-size: .78rem;
            letter-spacing: .08em;
            line-height: 1.9;
        }

        .me-display {
            margin: 0;
            font-family: var(--font-heading);
            font-weight: 700;
            line-height: 1.28;
            letter-spacing: -.01em;
        }

        .me-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            min-block-size: 2.85rem;
            padding-inline: 1.2rem;
            border: 1px solid var(--c-primary);
            border-radius: var(--radius-button);
            font: inherit;
            font-size: .92rem;
            text-decoration: none;
            transition: background-color 160ms ease, color 160ms ease;
        }
        .me-btn--solid { background: var(--c-primary); color: var(--c-on-primary); }
        .me-btn--solid:hover { background: var(--c-primary-hover); }
        .me-btn--ghost { background: transparent; color: var(--c-primary); }
        .me-btn--ghost:hover { background: color-mix(in srgb, var(--c-primary) 9%, transparent); }

        /* ---------- masthead + nav -------------------------------------- */
        .me-mast { border-block-end: 2px solid var(--c-text); }
        .me-mast__top {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: .35rem 1rem;
            padding-block: .65rem;
            border-block-end: 1px solid var(--c-border);
            color: var(--c-muted);
            font-size: .72rem;
            letter-spacing: .05em;
        }
        .me-mast__name { color: var(--c-text); font-weight: 700; letter-spacing: 0; }

        .me-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem 1rem;
            padding-block: 1rem;
        }
        .me-nav__links {
            display: flex;
            flex-wrap: wrap;
            /* A flex row's min-content floor is the widest item, but the gap
               between items is added on top and cannot shrink — at 390px that
               pushed the whole RTL page wider than the viewport. */
            min-inline-size: 0;
            gap: .35rem 1.1rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .me-nav__links a, .me-nav__cta {
            color: var(--c-text);
            font-size: .86rem;
            text-decoration: none;
            text-underline-offset: .35em;
        }
        .me-nav__links a:hover { text-decoration: underline; text-decoration-color: var(--c-primary); }
        .me-nav__cta {
            display: inline-flex;
            align-items: center;
            min-block-size: 2.55rem;
            padding-inline: 1rem;
            border: 1px solid var(--c-text);
            border-radius: 999px;
            font-weight: 700;
        }
        .me-nav__cta:hover { background: var(--c-text); color: var(--c-background); }

        /* ---------- hero ------------------------------------------------- */
        .me-hero { padding-block: clamp(2.2rem, 6vw, 5.25rem) clamp(2rem, 5vw, 4rem); }
        .me-hero__grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: clamp(1.75rem, 4vw, 3.5rem);
            align-items: end;
        }
        @media (min-width: 58rem) {
            .me-hero__grid { grid-template-columns: minmax(0, 1.45fr) minmax(17rem, .55fr); }
            /* With no live roster rows the ledger is not rendered, and the
               fixed second column would sit there as dead space. */
            .me-hero__grid--single { grid-template-columns: minmax(0, 1fr); }
        }
        .me-hero h1 {
            margin: .7rem 0 0;
            font-family: var(--font-heading);
            font-size: clamp(2.4rem, 6vw, 4.7rem);
            font-weight: 700;
            line-height: 1.16;
        }
        .me-hero h1 em { display: block; color: var(--c-primary); font-style: normal; }
        .me-hero__lede {
            max-inline-size: 42rem;
            margin: 1.2rem 0 0;
            color: var(--c-muted);
            font-size: 1.02rem;
            line-height: 2;
        }
        .me-actions { display: flex; flex-wrap: wrap; gap: .7rem; margin-block-start: 1.6rem; }

        .me-ledger {
            border: 1px solid var(--c-text);
            background: color-mix(in srgb, var(--c-surface) 72%, transparent);
        }
        .me-ledger__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .7rem .9rem;
            border-block-end: 1px solid var(--c-text);
            font-size: .72rem;
            letter-spacing: .05em;
        }
        .me-ledger__stamp { color: var(--c-primary); font-weight: 700; }
        .me-ledger__rows { margin: 0; padding: 0; list-style: none; }
        .me-ledger__row { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; padding: .85rem .9rem; }
        .me-ledger__row + .me-ledger__row { border-block-start: 1px solid var(--c-border); }
        .me-ledger__v { font-family: var(--font-heading); font-size: 1.55rem; font-weight: 700; }
        .me-ledger__l { color: var(--c-muted); font-size: .78rem; }

        /* ---------- grades strip ---------------------------------------- */
        .me-strip { padding-block: 1rem; border-block: 1px solid var(--c-border); }
        .me-strip__in { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1.4rem; }
        .me-strip__h { margin: 0; color: var(--c-muted); font-size: .76rem; }
        .me-strip__item { font-family: var(--font-heading); font-size: 1.02rem; }
        .me-strip__sep { color: var(--c-primary); }

        /* ---------- shared section chrome ------------------------------- */
        .me-sec { padding-block: clamp(2.5rem, 6vw, 4.5rem); }
        .me-sec + .me-sec { border-block-start: 1px solid var(--c-border); }
        .me-sec__head {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: .6rem;
            margin-block-end: clamp(1.5rem, 4vw, 2.6rem);
        }
        @media (min-width: 52rem) {
            .me-sec__head { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: end; }
        }
        .me-sec__h { font-family: var(--font-heading); font-size: clamp(1.75rem, 3.3vw, 2.7rem); font-weight: 700; line-height: 1.3; margin: 0; }
        .me-sec__sub { margin: 0; color: var(--c-muted); font-size: .88rem; line-height: 1.95; }

        /* ---------- advisor --------------------------------------------- */
        .me-adv { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; }
        @media (min-width: 56rem) { .me-adv { grid-template-columns: 13rem minmax(0, 1fr); } }
        .me-adv__mark {
            display: flex;
            align-items: center;
            justify-content: center;
            min-block-size: 13rem;
            border: 1px solid var(--c-text);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--c-primary) 12%, transparent), transparent 60%),
                var(--c-surface);
            font-family: var(--font-heading);
            font-size: 5rem;
            color: var(--c-primary);
        }
        .me-adv__name { margin: 0; font-family: var(--font-heading); font-size: clamp(1.6rem, 3vw, 2.3rem); }
        .me-adv__tag { margin: .3rem 0 0; color: var(--c-primary); font-size: .9rem; }
        .me-adv__bio { margin: 1rem 0 0; color: var(--c-muted); line-height: 2; max-inline-size: 52rem; }
        .me-adv__stats { display: flex; flex-wrap: wrap; gap: 1.5rem 2.5rem; margin: 1.4rem 0 0; padding: 1rem 0 0; border-block-start: 1px solid var(--c-border); list-style: none; }
        .me-adv__stat b { display: block; font-family: var(--font-heading); font-size: 1.35rem; }
        .me-adv__stat span { color: var(--c-muted); font-size: .76rem; }

        /* ---------- process --------------------------------------------- */
        .me-steps { margin: 0; padding: 0; list-style: none; border-block-start: 1px solid var(--c-text); }
        .me-step { display: grid; grid-template-columns: 3.4rem minmax(0, 1fr); gap: 1rem; padding-block: 1.2rem; border-block-end: 1px solid var(--c-border); }
        .me-step__n { font-family: var(--font-heading); font-size: 1.5rem; color: var(--c-primary); }
        .me-step__t { margin: 0; font-size: 1.02rem; font-weight: 700; }
        .me-step__x { margin: .35rem 0 0; color: var(--c-muted); font-size: .86rem; line-height: 1.95; }

        /* ---------- services -------------------------------------------- */
        .me-services { display: grid; grid-template-columns: minmax(0, 1fr); gap: 0 2.5rem; }
        @media (min-width: 58rem) { .me-services { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .me-svc { display: grid; grid-template-columns: 2.6rem minmax(0, 1fr); gap: .8rem; padding-block: 1.1rem; border-block-end: 1px solid var(--c-border); }
        .me-svc__n { font-family: var(--font-heading); color: var(--c-primary); font-size: 1.15rem; }
        .me-svc__t { margin: 0; font-size: .98rem; font-weight: 700; }
        .me-svc__x { margin: .3rem 0 0; color: var(--c-muted); font-size: .84rem; line-height: 1.9; }

        /* ---------- faculty --------------------------------------------- */
        .me-faculty { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1rem; }
        .me-teacher { padding: 1rem; border: 1px solid var(--c-border); background: var(--c-surface); }
        .me-teacher__head { display: flex; align-items: center; gap: .7rem; }
        .me-teacher__ini {
            display: grid; place-items: center;
            inline-size: 2.5rem; block-size: 2.5rem; flex: 0 0 auto;
            border-radius: 999px;
            background: color-mix(in srgb, var(--c-primary) 14%, transparent);
            color: var(--c-primary);
            font-family: var(--font-heading);
            font-size: 1.1rem;
        }
        .me-teacher__name { margin: 0; font-size: .94rem; font-weight: 700; }
        .me-teacher__subject { margin: .15rem 0 0; color: var(--c-muted); font-size: .74rem; }
        .me-teacher__bio { margin: .7rem 0 0; color: var(--c-muted); font-size: .8rem; line-height: 1.9; }

        /* ---------- classrooms ------------------------------------------ */
        .me-classes { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem; }
        .me-class { border: 1px solid var(--c-text); background: var(--c-surface); }
        .me-class__h { display: flex; align-items: baseline; justify-content: space-between; gap: .6rem; padding: .8rem 1rem; border-block-end: 1px solid var(--c-border); }
        .me-class__h b { font-family: var(--font-heading); font-size: 1.1rem; }
        .me-class__h span { color: var(--c-muted); font-size: .74rem; }
        .me-class__rooms { margin: 0; padding: .35rem 1rem; list-style: none; }
        .me-class__room { display: flex; align-items: baseline; justify-content: space-between; gap: .6rem; padding-block: .5rem; border-block-end: 1px dashed var(--c-border); font-size: .84rem; }
        .me-class__room:last-child { border-block-end: 0; }
        .me-class__room span:last-child { color: var(--c-muted); font-size: .74rem; }

        /* ---------- quotes ---------------------------------------------- */
        .me-quotes { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
        @media (min-width: 58rem) { .me-quotes { grid-template-columns: 1.25fr 1fr 1fr; } }
        .me-quote { display: flex; flex-direction: column; gap: 1rem; margin: 0; padding: 1.25rem; border: 1px solid var(--c-border); background: var(--c-surface); }
        .me-quote--lead { border-color: var(--c-text); background: color-mix(in srgb, var(--c-primary) 7%, var(--c-surface)); }
        .me-quote__mark { font-family: var(--font-heading); font-size: 3rem; line-height: .8; color: var(--c-primary); }
        .me-quote__x { margin: 0; font-size: .9rem; line-height: 2; }
        .me-quote__who { margin-block-start: auto; padding-block-start: .8rem; border-block-start: 1px solid var(--c-border); }
        .me-quote__n { display: block; font-size: .84rem; font-weight: 700; }
        .me-quote__r { display: block; margin-block-start: .15rem; color: var(--c-muted); font-size: .74rem; }

        /* ---------- faq / blog / cta ------------------------------------ */
        .me-faq { border-block-start: 1px solid var(--c-text); }
        .me-faq details { border-block-end: 1px solid var(--c-border); }
        .me-faq summary { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-block: 1rem; cursor: pointer; font-size: .94rem; font-weight: 700; }
        .me-faq summary::after { content: '+'; color: var(--c-primary); font-size: 1.2rem; }
        .me-faq details[open] summary::after { content: '−'; }
        .me-faq p { margin: 0; padding-block-end: 1.1rem; color: var(--c-muted); font-size: .86rem; line-height: 2; }

        .me-blog { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem; }
        .me-post { display: flex; flex-direction: column; gap: .8rem; padding: 1rem; border: 1px solid var(--c-border); background: var(--c-surface); color: inherit; text-decoration: none; }
        .me-post:hover { border-color: var(--c-primary); }
        .me-post__img { inline-size: 100%; aspect-ratio: 16 / 10; object-fit: cover; filter: saturate(.9); }
        .me-post__t { margin: 0; font-family: var(--font-heading); font-size: 1.05rem; }
        .me-post__x { margin: 0; color: var(--c-muted); font-size: .8rem; line-height: 1.9; }

        .me-cta { border: 1px solid var(--c-text); padding: clamp(1.4rem, 4vw, 2.4rem); background: color-mix(in srgb, var(--c-primary) 6%, var(--c-surface)); }
        .me-cta__h { margin: 0; font-family: var(--font-heading); font-size: clamp(1.5rem, 3vw, 2.2rem); }
        .me-cta__x { margin: .7rem 0 0; color: var(--c-muted); line-height: 2; max-inline-size: 46rem; }

        /* ---------- footer ---------------------------------------------- */
        .me-foot { border-block-start: 2px solid var(--c-text); padding-block: 2rem 1.2rem; }
        .me-foot__grid { display: grid; grid-template-columns: minmax(0, 1.3fr) repeat(auto-fit, minmax(9rem, .7fr)); gap: 1.5rem; }
        .me-foot__blurb { max-inline-size: 34rem; color: var(--c-muted); font-size: .82rem; line-height: 1.95; }
        .me-foot__h { margin: 0 0 .6rem; font-size: .8rem; font-weight: 700; }
        .me-foot__links { display: grid; gap: .35rem; }
        .me-foot__links a { color: var(--c-muted); font-size: .78rem; text-decoration: none; }
        .me-foot__links a:hover { color: var(--c-primary); text-decoration: underline; }
        .me-foot__social { display: flex; gap: .6rem; margin-block-start: .8rem; }
        .me-foot__social a { color: var(--c-muted); font-size: .76rem; text-decoration: none; }
        .me-foot__base { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-block-start: 1.6rem; padding-block-start: .8rem; border-block-start: 1px solid var(--c-border); color: var(--c-muted); font-size: .72rem; }
    </style>
</head>
<body class="me">
    <header class="me-mast">
        <div class="me-wrap">
            <div class="me-mast__top">
                <span class="me-mast__name">{{ $tenant['name'] }}</span>
                <span>{{ $L['meta']['title'] ?? $tenant['short_name'] }}</span>
            </div>
            <nav class="me-nav" aria-label="{{ $tenant['name'] }}">
                <ul class="me-nav__links">
                    @foreach (($nav['links'] ?? []) as $link)
                        @if (($link['visible'] ?? true))
                            <li><a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] ?? '' }}</a></li>
                        @endif
                    @endforeach
                </ul>
                @if (($nav['cta']['visible'] ?? true) && !empty($nav['cta']['label']))
                    <a class="me-nav__cta" href="{{ $hrefOf($nav['cta']) }}">{{ $nav['cta']['label'] }}</a>
                @endif
            </nav>
        </div>
    </header>

    <main>
        <section class="me-hero">
            <div class="me-wrap">
                <div class="me-hero__grid {{ $metrics === [] ? 'me-hero__grid--single' : '' }}">
                    <div>
                        @if (!empty($L['hero']['eyebrow']))
                            <p class="me-kicker">{{ $L['hero']['eyebrow'] }}</p>
                        @endif
                        <h1 class="me-display">
                            {{ $L['hero']['title_line1'] ?? '' }}
                            <em>{{ $L['hero']['title_line2'] ?? $tenant['name'] }}</em>
                        </h1>
                        <p class="me-hero__lede">{{ $L['hero']['subtitle'] ?? '' }}</p>
                        <div class="me-actions">
                            @foreach (($L['hero']['buttons'] ?? []) as $b)
                                @if (($b['visible'] ?? true))
                                    <a class="me-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @if ($metrics !== [])
                        <aside class="me-ledger" aria-label="آمار مدرسه">
                            <div class="me-ledger__head">
                                <span>در یک نگاه</span>
                                <span class="me-ledger__stamp">{{ $adv['badge']['label'] ?? 'سال تحصیلی' }}</span>
                            </div>
                            <ul class="me-ledger__rows">
                                @foreach ($metrics as $m)
                                    <li class="me-ledger__row">
                                        <span class="me-ledger__v">{{ persian_digits($m['value']) }}</span>
                                        <span class="me-ledger__l">{{ $m['label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </aside>
                    @endif
                </div>
            </div>
        </section>

        @if (!empty($L['logos']['items']))
            <div class="me-strip">
                <div class="me-wrap">
                    <div class="me-strip__in">
                        @if (!empty($L['logos']['heading']))
                            <p class="me-strip__h">{{ $L['logos']['heading'] }}</p>
                        @endif
                        @foreach ($L['logos']['items'] as $grade)
                            @if (($grade['visible'] ?? true))
                                <span class="me-strip__item">{{ $grade['name'] ?? '' }}</span>
                                @if (! $loop->last)<span class="me-strip__sep" aria-hidden="true">/</span>@endif
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if (!empty($adv['name']))
            <section class="me-sec" id="{{ $adv['id'] ?? 'about' }}">
                <div class="me-wrap">
                    <div class="me-adv">
                        <div class="me-adv__mark" aria-hidden="true">{{ $monogram }}</div>
                        <div>
                            <p class="me-kicker">{{ $adv['eyebrow']['label'] ?? 'مدیریت مدرسه' }}</p>
                            <h2 class="me-adv__name">{{ $adv['name'] }}</h2>
                            <p class="me-adv__tag">{{ $adv['tagline'] ?? '' }}</p>
                            <p class="me-adv__bio">{{ $adv['bio'] ?? '' }}</p>
                            @if (!empty($adv['stats']))
                                <ul class="me-adv__stats">
                                    @foreach ($adv['stats'] as $stat)
                                        <li class="me-adv__stat">
                                            <b>{{ $stat['value'] ?? '' }}</b>
                                            <span>{{ $stat['label'] ?? '' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (!empty($adv['buttons']))
                                <div class="me-actions">
                                    @foreach ($adv['buttons'] as $b)
                                        @if (($b['visible'] ?? true))
                                            <a class="me-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
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
            <section class="me-sec" id="{{ $L['process']['id'] ?? 'process' }}">
                <div class="me-wrap">
                    <div class="me-sec__head">
                        <h2 class="me-sec__h">{{ $L['process']['heading'] ?? 'مسیر همراهی' }}</h2>
                        <p class="me-sec__sub">{{ $L['process']['subheading'] ?? '' }}</p>
                    </div>
                    <ol class="me-steps">
                        @foreach ($L['process']['items'] as $step)
                            @continue(! ($step['visible'] ?? true))
                            <li class="me-step">
                                <span class="me-step__n">{{ persian_digits(str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)) }}</span>
                                <div>
                                    <h3 class="me-step__t">{{ $step['title'] ?? '' }}</h3>
                                    <p class="me-step__x">{{ $step['text'] ?? '' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif

        @if (!empty($L['services']['items']))
            <section class="me-sec" id="{{ $L['services']['id'] ?? 'services' }}">
                <div class="me-wrap">
                    <div class="me-sec__head">
                        <h2 class="me-sec__h">{{ $L['services']['heading'] ?? 'خدمات' }}</h2>
                        <p class="me-sec__sub">{{ $L['services']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="me-services">
                        @foreach ($L['services']['items'] as $i => $svc)
                            @continue(! ($svc['visible'] ?? true))
                            <article class="me-svc">
                                <span class="me-svc__n" style="color: {{ $accentOf($svc['accent'] ?? null) }}">{{ persian_digits(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                                <div>
                                    <h3 class="me-svc__t">{{ $svc['title'] ?? '' }}</h3>
                                    <p class="me-svc__x">{{ $svc['text'] ?? '' }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($faculty !== [])
            <section class="me-sec" id="{{ $L['faculty']['id'] ?? 'faculty' }}">
                <div class="me-wrap">
                    <div class="me-sec__head">
                        <h2 class="me-sec__h">{{ $L['faculty']['heading'] ?? 'دبیران' }}</h2>
                        <p class="me-sec__sub">{{ $L['faculty']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="me-faculty">
                        @foreach ($faculty as $member)
                            <article class="me-teacher">
                                <div class="me-teacher__head">
                                    @if (!empty($member['avatar']))
                                        <img class="me-teacher__ini" src="{{ tenant_asset($member['avatar']) }}" alt="" loading="lazy">
                                    @else
                                        <span class="me-teacher__ini" aria-hidden="true">{{ $member['initials'] ?? '' }}</span>
                                    @endif
                                    <div>
                                        <h3 class="me-teacher__name">{{ $member['name'] ?? '' }}</h3>
                                        @if (!empty($member['subject']))
                                            <p class="me-teacher__subject">{{ $member['subject'] }}</p>
                                        @endif
                                    </div>
                                </div>
                                @if (($L['faculty']['show_bio'] ?? true) && !empty($member['bio']))
                                    <p class="me-teacher__bio">{{ $member['bio'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($classrooms !== [])
            <section class="me-sec" id="{{ $L['classrooms']['id'] ?? 'classrooms' }}">
                <div class="me-wrap">
                    <div class="me-sec__head">
                        <h2 class="me-sec__h">{{ $L['classrooms']['heading'] ?? 'کلاس‌ها' }}</h2>
                        <p class="me-sec__sub">{{ $L['classrooms']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="me-classes">
                        @foreach ($classrooms as $grade)
                            <article class="me-class">
                                <div class="me-class__h">
                                    <b>پایه {{ $grade['grade'] }}</b>
                                    <span>{{ persian_digits($grade['total']) }} دانش‌آموز</span>
                                </div>
                                <ul class="me-class__rooms">
                                    @foreach ($grade['rooms'] as $room)
                                        <li class="me-class__room">
                                            <span>{{ $room['name'] }}</span>
                                            <span>{{ persian_digits($room['students']) }} نفر</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['testimonials']['items']))
            <section class="me-sec" id="{{ $L['testimonials']['id'] ?? 'testimonials' }}">
                <div class="me-wrap">
                    <div class="me-sec__head">
                        <h2 class="me-sec__h">{{ $L['testimonials']['heading'] ?? 'صدای خانواده‌ها' }}</h2>
                        <p class="me-sec__sub">{{ $L['testimonials']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="me-quotes">
                        @foreach ($L['testimonials']['items'] as $i => $q)
                            @continue(! ($q['visible'] ?? true))
                            <figure class="me-quote {{ $i === 0 ? 'me-quote--lead' : '' }}">
                                <span class="me-quote__mark" aria-hidden="true">”</span>
                                <blockquote class="me-quote__x">{{ $q['text'] ?? '' }}</blockquote>
                                <figcaption class="me-quote__who">
                                    <span class="me-quote__n">{{ $q['name'] ?? '' }}</span>
                                    <span class="me-quote__r">{{ $q['result'] ?? '' }}</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['faq']['items']))
            <section class="me-sec" id="{{ $L['faq']['id'] ?? 'faq' }}">
                <div class="me-wrap">
                    <div class="me-sec__head">
                        <h2 class="me-sec__h">{{ $L['faq']['heading'] ?? 'پرسش‌های متداول' }}</h2>
                        <p class="me-sec__sub">{{ $L['faq']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="me-faq">
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

        @if ($blog !== [])
            <section class="me-sec" id="{{ $L['blog']['id'] ?? 'blog' }}">
                <div class="me-wrap">
                    <div class="me-sec__head">
                        <h2 class="me-sec__h">{{ $L['blog']['heading'] ?? 'از دفتر مدرسه' }}</h2>
                        <p class="me-sec__sub">{{ $L['blog']['subheading'] ?? '' }}</p>
                    </div>
                    <div class="me-blog">
                        @foreach ($blog as $post)
                            @continue(! ($post['visible'] ?? true))
                            <a class="me-post" href="{{ $post['url'] ?? '#' }}">
                                @if (!empty($post['image']))
                                    <img class="me-post__img" src="{{ tenant_asset($post['image']) }}" alt="" loading="lazy">
                                @endif
                                <h3 class="me-post__t">{{ $post['title'] ?? '' }}</h3>
                                <p class="me-post__x">{{ $post['excerpt'] ?? '' }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['cta']['text']) || !empty($L['cta']['heading']))
            <section class="me-sec">
                <div class="me-wrap">
                    <div class="me-cta">
                        <h2 class="me-cta__h">{{ $L['cta']['heading'] ?? 'با ما آشنا شوید' }}</h2>
                        @if (!empty($L['cta']['text']))
                            <p class="me-cta__x">{{ $L['cta']['text'] }}</p>
                        @endif
                        <div class="me-actions">
                            @foreach (($L['cta']['buttons'] ?? []) as $b)
                                @if (($b['visible'] ?? true))
                                    <a class="me-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </main>

    <footer class="me-foot">
        <div class="me-wrap">
            <div class="me-foot__grid">
                <div>
                    <p class="me-foot__h">{{ $tenant['name'] }}</p>
                    <p class="me-foot__blurb">{{ $foot['blurb'] ?? '' }}</p>
                    @if (!empty($foot['social']))
                        <div class="me-foot__social">
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
                        <p class="me-foot__h">{{ $col['title'] ?? '' }}</p>
                        <div class="me-foot__links">
                            @foreach (($col['links'] ?? []) as $link)
                                <a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                            @endforeach
                        </div>
                    </nav>
                @endforeach
            </div>
            <div class="me-foot__base">
                <span>{{ $tenant['name'] }}</span>
                <span>{{ date('Y') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
