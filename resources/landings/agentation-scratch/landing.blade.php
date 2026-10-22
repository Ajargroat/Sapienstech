{{--
    Bespoke landing — SP Agentation Scratch (agentation-scratch).

    This is the reference scaffold `php artisan tenant:make` copies for a new
    tenant (resources/stubs/landing.blade.php). It is deliberately generic: a
    clean, token-driven surface that renders every section the tenant config
    actually supplies, and drops the ones it does not. A new tenant can edit
    the copy in config and this file, but the page already works — and is
    published — from the first request.

    No inline script, no inline handler, no hard-coded colour. Both theme
    partials appear exactly once.
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

    $toneOf = static fn (array $b): string => match ($b['style'] ?? 'solid') {
        'ghost', 'outline' => 'st-btn--ghost',
        default => 'st-btn--solid',
    };

    $accentOf = static fn (?string $name): string => 'var(--c-'.str_replace('_', '-', $name ?: 'primary').')';

    $metrics = \App\Support\TenantRoster::resolveMetrics($L['stats']['items'] ?? [], tenant());
    $monogram = mb_substr((string) ($adv['name'] ?? $tenant['name']), 0, 1);
    $hasPortrait = !empty($adv['image']) && file_exists(public_path('tenants/'.(tenant()?->slug ?? '').'/'.ltrim((string) $adv['image'], '/')));
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
        /* ---------- shell ------------------------------------------------- */
        .st {
            margin: 0;
            min-block-size: 100dvh;
            background:
                radial-gradient(60rem 34rem at 85% -10%,
                    color-mix(in srgb, var(--c-primary) 18%, transparent), transparent 65%),
                radial-gradient(46rem 30rem at 8% 30%,
                    color-mix(in srgb, var(--c-secondary) 12%, transparent), transparent 70%),
                var(--c-background);
            color: var(--c-text);
            font-family: var(--font-body);
            font-size: var(--body-size);
            line-height: var(--line-height);
        }
        .st *, .st *::before, .st *::after { box-sizing: border-box; }
        .st-wrap { max-inline-size: var(--content-max); margin-inline: auto; padding-inline: clamp(1.1rem, 4vw, 3.25rem); }

        .st-card {
            border: 1px solid var(--c-border);
            border-radius: var(--radius-card);
            background: color-mix(in srgb, var(--c-surface) 82%, transparent);
            box-shadow: var(--card-shadow);
        }

        .st-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            min-block-size: 2.85rem;
            padding-inline: 1.25rem;
            border: 1px solid transparent;
            border-radius: var(--radius-button);
            font: inherit;
            font-size: .9rem;
            font-weight: 700;
            text-decoration: none;
            transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
        }
        .st-btn--solid { background: var(--c-primary); color: var(--c-on-primary); }
        .st-btn--solid:hover { background: var(--c-primary-hover); }
        .st-btn--ghost { border-color: var(--c-border); color: var(--c-text); }
        .st-btn--ghost:hover { border-color: var(--c-primary); color: var(--c-primary); }

        /* ---------- nav --------------------------------------------------- */
        .st-top { position: sticky; inset-block-start: 0; z-index: 40; border-block-end: 1px solid var(--c-border); background: color-mix(in srgb, var(--c-background) 82%, transparent); backdrop-filter: blur(14px); }
        .st-top__in { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .8rem 1rem; padding-block: .8rem; }
        .st-brand { display: flex; align-items: center; gap: .6rem; color: var(--c-text); font-weight: 800; text-decoration: none; }
        .st-brand__mark {
            display: grid; place-items: center;
            inline-size: 2.35rem; block-size: 2.35rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--c-primary) 18%, transparent);
            color: var(--c-primary);
            font-family: var(--font-heading);
        }
        .st-nav { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem 1.05rem; }
        .st-nav a { color: var(--c-muted); font-size: .82rem; text-decoration: none; }
        .st-nav a:hover { color: var(--c-text); }
        .st-nav .st-btn { color: var(--c-on-primary); }

        /* ---------- hero -------------------------------------------------- */
        .st-hero { padding-block: clamp(2.4rem, 7vw, 5.6rem); }
        .st-hero__grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 2rem; align-items: center; }
        @media (min-width: 58rem) { .st-hero__grid { grid-template-columns: minmax(0, 1.2fr) minmax(20rem, .8fr); } }
        .st-eyebrow {
            display: inline-flex; align-items: center; gap: .4rem;
            margin: 0; padding: .25rem .65rem;
            border: 1px solid color-mix(in srgb, var(--c-primary) 35%, transparent);
            border-radius: 999px;
            color: var(--c-primary);
            font-size: .72rem;
        }
        .st-hero h1 {
            margin: .9rem 0 0;
            font-family: var(--font-heading);
            font-size: clamp(2.2rem, 5.6vw, 4.4rem);
            font-weight: 800;
            line-height: 1.18;
            letter-spacing: -.015em;
        }
        .st-hero h1 em { display: block; color: var(--c-primary); font-style: normal; }
        .st-hero__sub { max-inline-size: 42rem; margin: 1.1rem 0 0; color: var(--c-muted); line-height: 2; }
        .st-actions { display: flex; flex-wrap: wrap; gap: .7rem; margin-block-start: 1.5rem; }

        .st-art { position: relative; padding: 1rem; inline-size: 100%; max-inline-size: 22rem; justify-self: center; }
        .st-art__panel { padding: 1.1rem; }
        .st-art__portrait {
            inline-size: 100%;
            aspect-ratio: 4 / 5;
            object-fit: cover;
            border-radius: calc(var(--radius-card) - .35rem);
            filter: saturate(.95);
        }
        .st-art__mono {
            display: grid; place-items: center;
            aspect-ratio: 4 / 5;
            border-radius: calc(var(--radius-card) - .35rem);
            background:
                linear-gradient(150deg, color-mix(in srgb, var(--c-primary) 22%, transparent), transparent 60%),
                var(--c-surface-alt);
            color: var(--c-primary);
            font-family: var(--font-heading);
            font-size: clamp(3rem, 5vw, 4.2rem);
            font-weight: 800;
        }
        .st-art__cap { display: flex; align-items: baseline; justify-content: space-between; gap: .8rem; margin-block-start: .8rem; }
        .st-art__n { font-size: .88rem; font-weight: 800; }
        .st-art__r { color: var(--c-muted); font-size: .72rem; }

        .st-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; margin-block-start: .8rem; }
        .st-metric { padding: .6rem; border: 1px solid var(--c-border); border-radius: calc(var(--radius-card) - .35rem); text-align: center; }
        .st-metric b { display: block; font-family: var(--font-heading); font-size: 1.15rem; color: var(--c-primary); }
        .st-metric span { display: block; margin-block-start: .1rem; color: var(--c-muted); font-size: .64rem; }

        /* ---------- sections --------------------------------------------- */
        .st-sec { padding-block: clamp(2.2rem, 6vw, 4.2rem); }
        .st-sec + .st-sec { border-block-start: 1px solid var(--c-border); }
        .st-head { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: .8rem; margin-block-end: 1.6rem; }
        .st-h2 { margin: 0; font-family: var(--font-heading); font-size: clamp(1.6rem, 3.5vw, 2.6rem); font-weight: 800; line-height: 1.25; }
        .st-sub { margin: .4rem 0 0; max-inline-size: 48rem; color: var(--c-muted); font-size: .86rem; line-height: 1.95; }

        /* A cap keeps one or two cards from stretching across the whole row,
           which is what a fresh tenant config produces. */
        .st-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem; }
        .st-grid > * { max-inline-size: 30rem; }
        .st-svc { padding: 1.15rem; }
        .st-svc__n { display: inline-block; font-family: var(--font-heading); font-size: 1rem; font-weight: 800; }
        .st-svc__t { margin: .55rem 0 0; font-size: 1rem; font-weight: 800; }
        .st-svc__x { margin: .4rem 0 0; color: var(--c-muted); font-size: .82rem; line-height: 1.9; }

        .st-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 1rem; }
        .st-step { padding: 1.1rem; }
        .st-step__n { font-family: var(--font-heading); font-size: 1.9rem; font-weight: 800; color: var(--c-primary); }
        .st-step__t { margin: .5rem 0 0; font-size: .96rem; font-weight: 800; }
        .st-step__x { margin: .35rem 0 0; color: var(--c-muted); font-size: .8rem; line-height: 1.9; }

        .st-band { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 1px; overflow: hidden; background: var(--c-border); border: 1px solid var(--c-border); border-radius: var(--radius-card); }
        .st-band__cell { padding: 1.2rem; background: var(--c-surface); text-align: center; }
        .st-band__v { font-family: var(--font-heading); font-size: clamp(1.7rem, 4vw, 2.6rem); font-weight: 800; }
        .st-band__l { margin-block-start: .3rem; color: var(--c-muted); font-size: .74rem; }

        .st-quote { padding: 1.2rem; }
        .st-quote__x { margin: 0; font-size: .9rem; line-height: 2; }
        .st-quote__who { display: flex; align-items: center; gap: .65rem; margin-block-start: 1rem; padding-block-start: .8rem; border-block-start: 1px solid var(--c-border); }
        .st-quote__ini { display: grid; place-items: center; inline-size: 2.2rem; block-size: 2.2rem; border-radius: 999px; background: var(--c-primary); color: var(--c-on-primary); font-size: .74rem; font-weight: 800; }
        .st-quote__n { display: block; font-size: .8rem; font-weight: 700; }
        .st-quote__r { display: block; color: var(--c-muted); font-size: .7rem; }

        .st-faq { border-block-start: 1px solid var(--c-border); }
        .st-faq details { border-block-end: 1px solid var(--c-border); }
        .st-faq summary { display: flex; justify-content: space-between; gap: 1rem; padding-block: 1rem; cursor: pointer; font-size: .9rem; font-weight: 700; }
        .st-faq summary::after { content: '+'; color: var(--c-primary); font-size: 1.15rem; }
        .st-faq details[open] summary::after { content: '−'; }
        .st-faq p { margin: 0; padding-block-end: 1rem; color: var(--c-muted); font-size: .82rem; line-height: 1.95; }

        .st-posts { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: 1rem; }
        .st-post { display: flex; flex-direction: column; gap: .7rem; padding: 1rem; color: inherit; text-decoration: none; }
        .st-post:hover { border-color: var(--c-primary); }
        .st-post__img { inline-size: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: calc(var(--radius-card) - .4rem); }
        .st-post__t { margin: 0; font-size: 1rem; font-weight: 800; }
        .st-post__x { margin: 0; color: var(--c-muted); font-size: .8rem; line-height: 1.9; }

        .st-cta { padding: clamp(1.4rem, 4vw, 2.4rem); background: linear-gradient(135deg, color-mix(in srgb, var(--c-primary) 16%, var(--c-surface)), var(--c-surface)); }
        .st-cta__h { margin: 0; font-family: var(--font-heading); font-size: clamp(1.5rem, 3.4vw, 2.3rem); font-weight: 800; }
        .st-cta__x { margin: .6rem 0 0; color: var(--c-muted); line-height: 2; max-inline-size: 46rem; }

        /* ---------- footer ----------------------------------------------- */
        .st-foot { border-block-start: 1px solid var(--c-border); padding-block: 2rem 1.2rem; }
        .st-foot__grid { display: grid; grid-template-columns: minmax(0, 1.3fr) repeat(auto-fit, minmax(9rem, .6fr)); gap: 1.5rem; }
        .st-foot__h { margin: 0 0 .55rem; font-size: .78rem; font-weight: 800; }
        .st-foot__blurb { max-inline-size: 34rem; color: var(--c-muted); font-size: .8rem; line-height: 1.95; }
        .st-foot__links { display: grid; gap: .35rem; }
        .st-foot__links a { color: var(--c-muted); font-size: .76rem; text-decoration: none; }
        .st-foot__links a:hover { color: var(--c-primary); }
        .st-foot__social { display: flex; gap: .7rem; margin-block-start: .7rem; }
        .st-foot__social a { color: var(--c-muted); font-size: .72rem; text-decoration: none; }
        .st-foot__base { display: flex; justify-content: space-between; gap: 1rem; margin-block-start: 1.4rem; padding-block-start: .8rem; border-block-start: 1px solid var(--c-border); color: var(--c-muted); font-size: .7rem; }
    </style>
</head>
<body class="st">
    <header class="st-top">
        <div class="st-wrap">
            <div class="st-top__in">
                <a class="st-brand" href="{{ route('home') }}">
                    <span class="st-brand__mark" aria-hidden="true">{{ $monogram }}</span>
                    <span>{{ $tenant['name'] }}</span>
                </a>
                <nav class="st-nav" aria-label="{{ $tenant['name'] }}">
                    @foreach (($nav['links'] ?? []) as $link)
                        @if (($link['visible'] ?? true))
                            <a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                        @endif
                    @endforeach
                    @if (($nav['cta']['visible'] ?? true) && !empty($nav['cta']['label']))
                        <a class="st-btn st-btn--solid" href="{{ $hrefOf($nav['cta']) }}">{{ $nav['cta']['label'] }}</a>
                    @endif
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="st-hero">
            <div class="st-wrap">
                <div class="st-hero__grid">
                    <div>
                        @if (!empty($L['hero']['eyebrow']))
                            <p class="st-eyebrow">{{ $L['hero']['eyebrow'] }}</p>
                        @endif
                        <h1>
                            {{ $L['hero']['title_line1'] ?? '' }}
                            <em>{{ $L['hero']['title_line2'] ?? $tenant['name'] }}</em>
                        </h1>
                        <p class="st-hero__sub">{{ $L['hero']['subtitle'] ?? '' }}</p>
                        <div class="st-actions">
                            @foreach (($L['hero']['buttons'] ?? []) as $b)
                                @if (($b['visible'] ?? true))
                                    <a class="st-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <aside class="st-art st-card">
                        <div class="st-art__panel">
                            @if ($hasPortrait)
                                <img class="st-art__portrait" src="{{ tenant_asset($adv['image']) }}" alt="{{ $adv['name'] ?? '' }}" loading="lazy">
                            @else
                                <div class="st-art__mono" aria-hidden="true">{{ $monogram }}</div>
                            @endif
                            <div class="st-art__cap">
                                <span class="st-art__n">{{ $adv['name'] ?? $tenant['name'] }}</span>
                                <span class="st-art__r">{{ $adv['tagline'] ?? ($tenant['role_label'] ?? '') }}</span>
                            </div>
                            @if ($metrics !== [])
                                <div class="st-metrics">
                                    @foreach (array_slice($metrics, 0, 3) as $m)
                                        <div class="st-metric">
                                            <b>{{ persian_digits($m['value']) }}</b>
                                            <span>{{ $m['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        @if (!empty($L['logos']['items']))
            <div class="st-sec" style="padding-block: 1rem">
                <div class="st-wrap">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1.3rem;color:var(--c-muted);font-size:.76rem">
                        @if (!empty($L['logos']['heading']))
                            <span>{{ $L['logos']['heading'] }}</span>
                        @endif
                        @foreach ($L['logos']['items'] as $item)
                            @if (($item['visible'] ?? true))
                                <span style="color:var(--c-text)">{{ $item['name'] ?? '' }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if (!empty($L['services']['items']))
            <section class="st-sec" id="{{ $L['services']['id'] ?? 'services' }}">
                <div class="st-wrap">
                    <div class="st-head">
                        <div>
                            <h2 class="st-h2">{{ $L['services']['heading'] ?? 'خدمات' }}</h2>
                            <p class="st-sub">{{ $L['services']['subheading'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="st-grid">
                        @foreach ($L['services']['items'] as $i => $svc)
                            @continue(! ($svc['visible'] ?? true))
                            <article class="st-card st-svc">
                                <span class="st-svc__n" style="color: {{ $accentOf($svc['accent'] ?? null) }}">{{ persian_digits(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                                <h3 class="st-svc__t">{{ $svc['title'] ?? '' }}</h3>
                                <p class="st-svc__x">{{ $svc['text'] ?? '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['process']['items']))
            <section class="st-sec" id="{{ $L['process']['id'] ?? 'process' }}">
                <div class="st-wrap">
                    <div class="st-head">
                        <div>
                            <h2 class="st-h2">{{ $L['process']['heading'] ?? 'فرایند' }}</h2>
                            <p class="st-sub">{{ $L['process']['subheading'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="st-steps">
                        @foreach ($L['process']['items'] as $step)
                            @continue(! ($step['visible'] ?? true))
                            <article class="st-card st-step">
                                <span class="st-step__n">{{ persian_digits(str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)) }}</span>
                                <h3 class="st-step__t">{{ $step['title'] ?? '' }}</h3>
                                <p class="st-step__x">{{ $step['text'] ?? '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['stats']['items']))
            @php
                // A brand-new tenant inherits baseline metric rows whose live
                // value is 0. A headline "+0" reads as broken, so a row with
                // no measurable value is dropped rather than printed.
                $bandStats = collect($L['stats']['items'])
                    ->filter(static fn ($s) => (bool) ($s['visible'] ?? true))
                    ->filter(static function ($s) {
                        $raw = trim((string) ($s['value'] ?? ''));

                        return $raw !== '' && ! preg_match('/^[+\-]?0*$/', $raw);
                    })
                    ->values();
            @endphp
            @if ($bandStats->isNotEmpty())
            <section class="st-sec">
                <div class="st-wrap">
                    <div class="st-band">
                        @foreach ($bandStats as $stat)
                            <div class="st-band__cell">
                                <div class="st-band__v">{{ $stat['value'] ?? '' }}{{ $stat['suffix'] ?? '' }}</div>
                                <div class="st-band__l">{{ $stat['label'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
            @endif
        @endif

        @if (!empty($adv['name']))
            <section class="st-sec" id="{{ $adv['id'] ?? 'about' }}">
                <div class="st-wrap">
                    <div class="st-head">
                        <div>
                            <h2 class="st-h2">{{ $adv['eyebrow']['label'] ?? 'درباره' }}</h2>
                            <p class="st-sub">{{ $adv['tagline'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="st-card" style="padding:1.3rem">
                        <p style="margin:0;line-height:2;color:var(--c-muted)">{{ $adv['bio'] ?? '' }}</p>
                        @if (!empty($adv['buttons']))
                            <div class="st-actions">
                                @foreach ($adv['buttons'] as $b)
                                    @if (($b['visible'] ?? true))
                                        <a class="st-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['testimonials']['items']))
            <section class="st-sec" id="{{ $L['testimonials']['id'] ?? 'testimonials' }}">
                <div class="st-wrap">
                    <div class="st-head">
                        <div>
                            <h2 class="st-h2">{{ $L['testimonials']['heading'] ?? 'نظرها' }}</h2>
                            <p class="st-sub">{{ $L['testimonials']['subheading'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="st-grid">
                        @foreach ($L['testimonials']['items'] as $q)
                            @continue(! ($q['visible'] ?? true))
                            <figure class="st-card st-quote">
                                <blockquote class="st-quote__x">{{ $q['text'] ?? '' }}</blockquote>
                                <figcaption class="st-quote__who">
                                    <span class="st-quote__ini" aria-hidden="true">{{ $q['initials'] ?? '' }}</span>
                                    <span>
                                        <span class="st-quote__n">{{ $q['name'] ?? '' }}</span>
                                        <span class="st-quote__r">{{ $q['result'] ?? '' }}</span>
                                    </span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['faq']['items']))
            <section class="st-sec" id="{{ $L['faq']['id'] ?? 'faq' }}">
                <div class="st-wrap">
                    <div class="st-head">
                        <div>
                            <h2 class="st-h2">{{ $L['faq']['heading'] ?? 'پرسش‌های متداول' }}</h2>
                            <p class="st-sub">{{ $L['faq']['subheading'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="st-faq">
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
            <section class="st-sec" id="{{ $L['blog']['id'] ?? 'blog' }}">
                <div class="st-wrap">
                    <div class="st-head">
                        <div>
                            <h2 class="st-h2">{{ $L['blog']['heading'] ?? 'وبلاگ' }}</h2>
                            <p class="st-sub">{{ $L['blog']['subheading'] ?? '' }}</p>
                        </div>
                    </div>
                    <div class="st-posts">
                        @foreach ($blog as $post)
                            @continue(! ($post['visible'] ?? true))
                            <a class="st-card st-post" href="{{ $post['url'] ?? '#' }}">
                                @if (!empty($post['image']))
                                    <img class="st-post__img" src="{{ tenant_asset($post['image']) }}" alt="" loading="lazy">
                                @endif
                                <h3 class="st-post__t">{{ $post['title'] ?? '' }}</h3>
                                <p class="st-post__x">{{ $post['excerpt'] ?? '' }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['cta']['heading']) || !empty($L['cta']['text']))
            <section class="st-sec">
                <div class="st-wrap">
                    <div class="st-card st-cta">
                        <h2 class="st-cta__h">{{ $L['cta']['heading'] ?? '' }}</h2>
                        @if (!empty($L['cta']['text']))
                            <p class="st-cta__x">{{ $L['cta']['text'] }}</p>
                        @endif
                        <div class="st-actions">
                            @foreach (($L['cta']['buttons'] ?? []) as $b)
                                @if (($b['visible'] ?? true))
                                    <a class="st-btn {{ $toneOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </main>

    <footer class="st-foot">
        <div class="st-wrap">
            <div class="st-foot__grid">
                <div>
                    <p class="st-foot__h">{{ $tenant['name'] }}</p>
                    <p class="st-foot__blurb">{{ $foot['blurb'] ?? '' }}</p>
                    @if (!empty($foot['social']))
                        <div class="st-foot__social">
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
                        <p class="st-foot__h">{{ $col['title'] ?? '' }}</p>
                        <div class="st-foot__links">
                            @foreach (($col['links'] ?? []) as $link)
                                <a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                            @endforeach
                        </div>
                    </nav>
                @endforeach
            </div>
            <div class="st-foot__base">
                <span>{{ $tenant['name'] }}</span>
                <span>{{ date('Y') }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
