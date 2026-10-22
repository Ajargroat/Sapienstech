@php
    // Same derivation the shared landing does (Public/landing.blade.php:7): the
    // composer supplies `$public`, and the landing subtree is pulled out here.
    // It is NOT injected as $L by the composer, so a bespoke page must do this
    // itself or it silently renders an empty page.
    $L = $public['landing'] ?? [];
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
    <title>{{ $tenant['name'] }} | {{ $L['meta']['title'] ?? '' }}</title>
    <meta name="description" content="{{ $L['meta']['description'] ?? '' }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')

    <style>
        .dl { --dl-pad: clamp(1.25rem, 4vw, 5rem); }
        .dl-wrap { max-width: var(--content-max); margin-inline: auto; padding-inline: var(--dl-pad); }
        .dl-num { font-variant-numeric: tabular-nums; }

        .dl-masthead {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; padding-block: 1.25rem;
            border-block-end: var(--surface-border-w) solid var(--c-border);
        }
        .dl-masthead__brand { font-family: var(--font-heading); font-weight: 700; letter-spacing: -.01em; }
        .dl-masthead__nav { display: flex; align-items: center; gap: clamp(.75rem, 2vw, 1.75rem); font-size: .9rem; }
        .dl-masthead__nav a { color: var(--c-muted); text-decoration: none; }
        .dl-masthead__nav a:hover, .dl-masthead__nav a:focus-visible { color: var(--c-text); }

        .dl-btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: .5rem; padding: .7rem 1.4rem; font: inherit; font-weight: 600;
            text-decoration: none; border: var(--surface-border-w) solid transparent;
            border-radius: var(--radius-button); transition: background-color var(--reveal-duration) var(--ease),
                color var(--reveal-duration) var(--ease), border-color var(--reveal-duration) var(--ease);
        }
        .dl-btn--solid { background: var(--c-primary); color: var(--c-on-primary); }
        .dl-btn--solid:hover, .dl-btn--solid:focus-visible { background: var(--c-primary-hover); }
        .dl-btn--ghost { color: var(--c-text); border-color: var(--c-border); }
        .dl-btn--ghost:hover, .dl-btn--ghost:focus-visible { border-color: var(--c-primary); color: var(--c-primary); }

        /* The signature: an editorial two-column with a hanging rule, not a
           centred hero stack. This is the layout difference the whole system
           exists to allow. */
        .dl-hero { display: grid; gap: clamp(2rem, 6vw, 4.5rem); padding-block: clamp(3rem, 10vw, 7rem); }
        @media (min-width: 60rem) { .dl-hero { grid-template-columns: 1.15fr .85fr; align-items: start; } }
        .dl-hero__eyebrow {
            display: inline-block; margin-block-end: 1.25rem; padding-block-end: .5rem;
            font-size: .8rem; letter-spacing: .12em; text-transform: uppercase;
            color: var(--c-primary); border-block-end: 2px solid var(--c-primary);
        }
        .dl-hero__title {
            font-family: var(--font-heading); font-weight: var(--heading-weight);
            font-size: clamp(2rem, 5.5vw, 3.75rem); line-height: 1.15;
            letter-spacing: -.02em; margin: 0 0 1.25rem;
        }
        .dl-hero__title em { font-style: normal; color: var(--c-primary); }
        .dl-hero__sub { max-width: 46ch; color: var(--c-muted); line-height: 1.8; margin: 0 0 2rem; }
        .dl-hero__actions { display: flex; flex-wrap: wrap; gap: .75rem; }

        /* A file-card, not a glass box: hard edge, ruled lines, quiet. */
        .dl-card {
            padding: clamp(1.5rem, 3vw, 2rem); background: var(--c-surface);
            border: var(--surface-border-w) solid var(--c-border);
            border-radius: var(--radius-card);
        }
        .dl-facts { display: grid; gap: 1rem; }
        .dl-facts__row { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem;
            padding-block-end: .85rem; border-block-end: var(--surface-border-w) solid var(--c-border); }
        .dl-facts__row:last-child { border-block-end: 0; padding-block-end: 0; }
        .dl-facts__k { color: var(--c-muted); font-size: .85rem; }
        .dl-facts__v { font-weight: 600; }

        .dl-band { padding-block: clamp(2.5rem, 7vw, 5rem); border-block-start: var(--surface-border-w) solid var(--c-border); }
        .dl-band__head { display: flex; align-items: baseline; gap: 1rem; flex-wrap: wrap; margin-block-end: 2.25rem; }
        .dl-band__title { font-family: var(--font-heading); font-size: var(--h2-size); font-weight: var(--heading-weight); margin: 0; }
        .dl-band__note { color: var(--c-muted); font-size: .85rem; }

        /* Numbered path: an ordered editorial list, deliberately not a card grid. */
        .dl-steps { display: grid; gap: 1rem; counter-reset: step; list-style: none; margin: 0; padding: 0; }
        @media (min-width: 48rem) { .dl-steps { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .dl-step { padding: 1.5rem 1.35rem; border-radius: var(--radius-card);
            border: var(--surface-border-w) solid var(--c-border); background: var(--c-surface); }
        .dl-step::before { counter-increment: step; content: counter(step, decimal);
            display: grid; place-items: center; width: 2.25rem; height: 2.25rem;
            margin-block-end: .9rem; border-radius: 999px; background: var(--c-primary);
            color: var(--c-on-primary); font-family: var(--font-heading); font-weight: 800; font-size: .95rem; }
        .dl-step__title { margin: 0 0 .4rem; font-weight: 700; }
        .dl-step__text { margin: 0; color: var(--c-muted); line-height: 1.8; font-size: .92rem; }

        .dl-services { display: grid; gap: 1rem; }
        @media (min-width: 48rem) { .dl-services { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .dl-service { padding: 1.5rem; border-radius: var(--radius-card);
            border: var(--surface-border-w) solid var(--c-border); background: var(--c-surface); }
        .dl-service__title { margin: 0 0 .6rem; font-weight: 600; }
        .dl-service__text { margin: 0; color: var(--c-muted); font-size: .92rem; line-height: 1.8; }

        .dl-plans { display: grid; gap: 1rem; }
        @media (min-width: 48rem) { .dl-plans { grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: start; } }
        .dl-plan { display: flex; flex-direction: column; gap: 1rem; padding: 1.75rem;
            border-radius: var(--radius-card); border: var(--surface-border-w) solid var(--c-border);
            background: var(--c-surface); }
        .dl-plan--featured { border-color: var(--c-primary); }
        .dl-plan__badge { align-self: flex-start; padding: .2rem .6rem; font-size: .72rem;
            border-radius: var(--radius-md); background: var(--c-primary); color: var(--c-on-primary); }
        .dl-plan__name { margin: 0; font-weight: 600; }
        .dl-plan__price { margin: 0; font-family: var(--font-heading); font-size: 1.5rem; font-weight: 700; }
        .dl-plan__period { color: var(--c-muted); font-size: .82rem; }
        .dl-plan__features { margin: 0; padding: 0; list-style: none; display: grid; gap: .6rem;
            font-size: .9rem; color: var(--c-muted); }
        .dl-plan__features li { padding-inline-start: 1.1rem; position: relative; }
        .dl-plan__features li::before { content: ""; position: absolute; inset-inline-start: 0; inset-block-start: .55em;
            width: .4rem; height: .4rem; border-radius: 50%; background: var(--c-primary); }

        .dl-foot { display: grid; gap: 2rem; padding-block: 2.5rem 2rem;
            border-block-start: var(--surface-border-w) solid var(--c-border);
            color: var(--c-muted); font-size: .85rem; }
        @media (min-width: 48rem) { .dl-foot { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr) auto; align-items: start; } }
        .dl-foot__brand { display: grid; gap: .5rem; }
        .dl-foot__name { font-family: var(--font-heading); font-size: 1.05rem; font-weight: 700; color: var(--c-text); }
        .dl-foot__blurb { margin: 0; max-width: 34ch; line-height: 1.8; }
        .dl-foot__nav { display: flex; flex-wrap: wrap; gap: .55rem 1.25rem; }
        .dl-foot__nav a, .dl-foot__meta a { color: var(--c-muted); text-decoration: none; }
        .dl-foot__nav a:hover, .dl-foot__meta a:hover { color: var(--c-text); }
        .dl-foot__meta { display: flex; flex-direction: column; gap: .5rem; }
        .dl-foot__copy { font-variant-numeric: tabular-nums; }

        /* Membership request dialog. The form inside is the shared join
           partial, so the dialog only supplies the shell: a scroll cap, a
           close affordance and a token-only backdrop. */
        .dl-join-dialog { position: relative; width: min(680px, calc(100% - 24px));
            max-height: min(92vh, 880px); padding: 0; overflow: hidden;
            border: var(--surface-border-w) solid var(--c-border);
            border-radius: var(--radius-card); background: var(--c-surface); color: var(--c-text); }
        .dl-join-dialog::backdrop { background: color-mix(in srgb, var(--c-background) 88%, transparent);
            backdrop-filter: blur(6px); }
        .dl-join-dialog[open] { display: flex; flex-direction: column; }
        .dl-join-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 1.5rem 1.25rem; }
        .dl-join-x { position: absolute; inset-block-start: .7rem; inset-inline-end: .7rem; z-index: 2;
            width: 2rem; height: 2rem; display: grid; place-items: center; cursor: pointer;
            border: var(--surface-border-w) solid var(--c-border); border-radius: 999px;
            background: var(--c-surface); color: var(--c-muted); }
        .dl-join-x:hover { color: var(--c-text); }
        /* The embedded section keeps the form contract but drops its own
           section chrome; the dialog already provides the card. */
        .dl-join-body .lp-section { padding: 0; opacity: 1; transform: none; }
        .dl-join-body .landing-container { max-width: none !important; }
        .dl-join-body .p-8 { padding: 0 !important; border: 0 !important;
            background: transparent !important; box-shadow: none !important; }
        .dl-join-body .reveal { opacity: 1; transform: none; }
        .dl-plan .dl-btn { font: inherit; cursor: pointer; }

        /* Reduced motion is a tenant preference, not a suggestion. */
        [data-animations="off"] .dl-reveal { opacity: 1; transform: none; }
        .dl-reveal { opacity: 0; transform: translateY(12px);
            transition: opacity var(--reveal-duration) var(--ease), transform var(--reveal-duration) var(--ease); }
        .dl-reveal.is-visible { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) { .dl-reveal { opacity: 1; transform: none; transition: none; } }
    </style>
</head>
<body class="landing dl">

@php
    $hero      = $L['hero'] ?? [];
    $advisor   = $L['advisor'] ?? [];
    $process   = $L['process'] ?? [];
    $services  = $L['services'] ?? [];
    $plans     = $L['plans'] ?? [];
    $nav       = $public['nav'] ?? [];
    $money     = fn ($n) => persian_digits(number_format((int) $n));
@endphp
<div class="dl-wrap">
    <header class="dl-masthead">
        <span class="dl-masthead__brand">{{ $tenant['name'] }}</span>
        <nav class="dl-masthead__nav" aria-label="{{ $tenant['name'] }}">
            @foreach (($nav['links'] ?? []) as $link)
                @if ($link['visible'] ?? true)
                    <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                @endif
            @endforeach
            <a class="dl-btn dl-btn--ghost" href="{{ route('login') }}">{{ $nav['cta']['label'] ?? 'ورود' }}</a>
        </nav>
    </header>

    <section class="dl-hero">
        <div>
            @if (!empty($hero['eyebrow']))
                <span class="dl-hero__eyebrow">{{ $hero['eyebrow'] }}</span>
            @endif
            <h1 class="dl-hero__title">
                {{ $hero['title_line1'] ?? '' }}
                <em>{{ $hero['title_line2'] ?? '' }}</em>
            </h1>
            <p class="dl-hero__sub">{{ $hero['subtitle'] ?? '' }}</p>
            <div class="dl-hero__actions">
                @foreach (($hero['buttons'] ?? []) as $button)
                    @if (($button['visible'] ?? true) && !empty($button['href']))
                        <a class="dl-btn dl-btn--{{ ($button['style'] ?? 'primary') === 'ghost' ? 'ghost' : 'solid' }}"
                           href="{{ $button['href'] }}">{{ $button['label'] }}</a>
                    @endif
                @endforeach
            </div>
        </div>

        <aside class="dl-card" aria-label="{{ $advisor['name'] ?? '' }}">
            <div class="dl-facts">
                <div class="dl-facts__row">
                    <span class="dl-facts__k">مشاور</span>
                    <span class="dl-facts__v">{{ $advisor['name'] ?? '' }}</span>
                </div>
                <div class="dl-facts__row">
                    <span class="dl-facts__k">حوزه</span>
                    <span class="dl-facts__v">{{ $advisor['tagline'] ?? '' }}</span>
                </div>
                @foreach (($advisor['stats'] ?? []) as $stat)
                    <div class="dl-facts__row">
                        <span class="dl-facts__k">{{ $stat['label'] }}</span>
                        <span class="dl-facts__v dl-num">{{ $stat['value'] }}</span>
                    </div>
                @endforeach
            </div>
        </aside>
    </section>
</div>

@if (!empty($advisor['bio']))
    <section class="dl-band" id="{{ $advisor['id'] ?? 'about' }}">
        <div class="dl-wrap">
            <div class="dl-band__head">
                <h2 class="dl-band__title">دربارهٔ مشاور</h2>
            </div>
            <p class="dl-hero__sub" style="max-width:62ch;white-space:pre-line">{{ $advisor['bio'] }}</p>
        </div>
    </section>
@endif

@if (!empty($process['items']))
    <section class="dl-band" id="{{ $process['id'] ?? 'process' }}">
        <div class="dl-wrap">
            <div class="dl-band__head">
                <h2 class="dl-band__title">{{ $process['heading'] ?? '' }}</h2>
            </div>
            <ol class="dl-steps">
                @foreach ($process['items'] as $item)
                    @continue(!($item['visible'] ?? true))
                    <li class="dl-step">
                        <div>
                            <h3 class="dl-step__title">{{ $item['title'] }}</h3>
                            <p class="dl-step__text">{{ $item['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif

@if (!empty($services['items']))
    <section class="dl-band" id="{{ $services['id'] ?? 'services' }}">
        <div class="dl-wrap">
            <div class="dl-band__head">
                <h2 class="dl-band__title">{{ $services['heading'] ?? '' }}</h2>
            </div>
            <div class="dl-services">
                @foreach ($services['items'] as $item)
                    @continue(!($item['visible'] ?? true))
                    <article class="dl-service">
                        <h3 class="dl-service__title">{{ $item['title'] }}</h3>
                        <p class="dl-service__text">{{ $item['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if (!empty($plans['items']))
    <section class="dl-band" id="{{ $plans['id'] ?? 'plans' }}">
        <div class="dl-wrap">
            <div class="dl-band__head">
                <h2 class="dl-band__title">{{ $plans['heading'] ?? '' }}</h2>
            </div>
            @if (!empty($plans['subheading']))
                <p class="dl-band__note" style="margin-block-end:2rem">{{ $plans['subheading'] }}</p>
            @endif
            <div class="dl-plans">
                @foreach ($plans['items'] as $plan)
                    @continue(!($plan['visible'] ?? true))
                    <article class="dl-plan {{ ($plan['featured'] ?? false) ? 'dl-plan--featured' : '' }}">
                        @if (!empty($plan['badge']))
                            <span class="dl-plan__badge">{{ $plan['badge'] }}</span>
                        @endif
                        <h3 class="dl-plan__name">{{ $plan['name'] }}</h3>
                        <p class="dl-plan__price dl-num">
                            {{ $money($plan['price'] ?? 0) }}
                            <span class="dl-plan__period">تومان / {{ $plan['period'] ?? '' }}</span>
                        </p>
                        <ul class="dl-plan__features">
                            @foreach (preg_split('/\r\n|\r|\n/', (string) ($plan['features'] ?? '')) as $feature)
                                @continue(trim($feature) === '')
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <button type="button"
                                class="dl-btn dl-btn--{{ ($plan['featured'] ?? false) ? 'solid' : 'ghost' }}"
                                data-dl-join data-plan="{{ $plan['slug'] ?? '' }}">
                            {{ $plan['cta_label'] ?? ($plans['cta_label'] ?? '') }}
                        </button>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{--
    The membership request form («درخواست عضویت»).

    This is deliberately the SHARED section partial, not a re-implementation.
    Its presentation is the platform's to keep right: it whitelists the field
    names, reads the plan price server-side, posts to a route that resolves the
    tenant from the host, and is the only public form that writes a row. A
    bespoke page styles itself freely, but it must not re-derive a form that
    proves things about money and identity — so it embeds the real one and
    wraps it in its own chrome.

    It is no longer a page section: a plan's button opens it in a dialog with
    that plan preselected, so the plan grid is the only place a visitor enters
    the join flow. The closing CTA band was removed with it.
--}}
@if (!empty($L['join']))
    <dialog id="dl-join-dialog" class="dl-join-dialog" aria-label="{{ $L['join']['heading'] ?? 'درخواست عضویت' }}">
        <button type="button" class="dl-join-x" data-dl-join-close aria-label="بستن">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="dl-join-body">
            @include('public.sections.join.default', ['cfg' => $L['join'], 'anim' => $L['animations'] ?? []])
        </div>
    </dialog>
@endif

<div class="dl-wrap">
    <footer class="dl-foot">
        <div class="dl-foot__brand">
            <span class="dl-foot__name">{{ $tenant['name'] }}</span>
            @if (!empty($advisor['tagline']))
                <p class="dl-foot__blurb">{{ $advisor['tagline'] }}</p>
            @endif
        </div>
        <nav class="dl-foot__nav" aria-label="دسترسی سریع">
            @foreach (($nav['links'] ?? []) as $link)
                @if ($link['visible'] ?? true)
                    <a href="{{ $link['href'] }}">{{ $link['label'] }}</a>
                @endif
            @endforeach
        </nav>
        <div class="dl-foot__meta">
            <a href="{{ route('login') }}">{{ $nav['cta']['label'] ?? 'ورود به پنل' }}</a>
            <span class="dl-foot__copy">© {{ persian_digits(now()->format('Y')) }} {{ $tenant['name'] }}</span>
        </div>
    </footer>
</div>

<script src="{{ asset('landings/drkafimentoring/landing.js') }}" defer></script>
</body>
</html>
