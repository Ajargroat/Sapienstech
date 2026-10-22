{{--
    Bespoke landing — آکادمی جنت (tenant-one). v2: the rich rebuild.

    Structure is owned here; content is NOT — every string, icon, accent and
    image below is read from the resolved tenant config ($public/$theme), so
    the page stays correct when the academy edits its own copy.

    Design language = the tenant's own stored spec (config/tenants/tenant-one
    .php), not a new invention: aurora ground, glass surfaces, glow hovers,
    gradient-shift headline, bento services, boxed stats, masonry quotes,
    squircle icon chips, spin rings, dot grids, top-accent card edges,
    thick-underline heading rules, scroll progress + tilt reveal.

    Token contract: every colour is var(--c-*) or color-mix() over it — zero
    hard-coded colour. Icons ship through partials.theme-icons + _icon.
    Behaviour lives in landings/{slug}/landing.js, loaded with a src= (the CSP
    allows no inline script). Every animation is suppressible twice: by the
    tenant's data-animations switch and by prefers-reduced-motion.
--}}
@php
    $L = $public['landing'] ?? [];
    $adv = $L['advisor'] ?? [];
    $nav = $public['nav'] ?? [];
    $foot = $public['footer'] ?? [];

    // A button may carry a route NAME ({route: 'login'}) or a URL ({href: …}).
    $hrefOf = static function (array $b): string {
        $name = $b['route'] ?? null;
        if (is_string($name) && $name !== '') {
            try {
                return route($name);
            } catch (\Throwable) {
                return '#';
            }
        }
        return (string) ($b['href'] ?? '#');
    };

    $styleOf = static fn (array $b): string => match ($b['style'] ?? 'solid') {
        'ghost', 'outline' => 'jn-btn--ghost',
        'gradient' => 'jn-btn--grad',
        'primary' => 'jn-btn--primary',
        default => 'jn-btn--solid',
    };

    // Accent names in config ('accent-teal', 'primary') resolve to CSS tokens
    // (--c-accent-teal, --c-primary) per ThemeTokens' content-addressable
    // accent palette.
    $accentOf = static fn (?string $name): string => 'var(--c-'.($name ?: 'primary').')';

    $monogram = mb_substr((string) ($adv['name'] ?? $tenant['name']), 0, 1);

    $advisorImage = isset($adv['image'])
        ? 'tenants/'.(tenant()?->slug ?? '').'/'.ltrim((string) $adv['image'], '/')
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}"
      data-numerals="{{ ($theme['i18n']['numerals'] ?? 'auto') === 'auto'
            ? (($tenant['locale'] ?? 'fa') === 'fa' ? 'fa' : 'en')
            : $theme['i18n']['numerals'] }}"
      data-animations="{{ ($theme['effects']['enable_animations'] ?? true) ? 'on' : 'off' }}"
      data-counter-duration="{{ $theme['counter_duration'] ?? ($theme['landing']['counter_duration'] ?? 2000) }}"
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
        <meta name="twitter:image" content="{{ tenant_asset($L['meta']['og_image']) }}">
    @endif

    @if (!empty($theme['brand']['src']))
        <link rel="icon" href="{{ tenant_asset($theme['brand']['src']) }}">
    @elseif (!empty($tenant['favicon']))
        <link rel="icon" href="{{ $tenant['favicon'] }}">
    @endif

    {{-- Fonts ship through theme-vars (that partial includes theme-fonts); a
         second explicit pipeline here made the page double-shift its own faces. --}}
    @include('partials.theme-vars')
    @include('partials.theme-icons')

    <style>
        /* ---------- shell + aurora ground ---------------------------------
           The tenant stores background.mode = aurora: three large washes
           (emerald, gold, teal) behind the whole page, so the brand reads
           even between sections. */
        .jn { background: var(--c-background); color: var(--c-text);
              font-family: var(--font-body); font-size: var(--body-size);
              line-height: var(--line-height); min-block-size: 100dvh; }
        .jn * { box-sizing: border-box; }
        .jn-wrap { position: relative; max-width: var(--content-max); margin-inline: auto;
                   padding-inline: clamp(1.15rem, 4vw, 3.25rem); }
        .jn-shell { position: relative; z-index: 1; }
        @media (min-width: 64rem) { .jn-shell { padding-inline-start: 3.75rem; } }

        .jn-aurora { position: fixed; inset: 0; z-index: 0; overflow: clip;
                     pointer-events: none; user-select: none; }
        .jn-aurora__w { position: absolute; border-radius: 50%; filter: blur(90px); }
        .jn-aurora__w--a { inline-size: 55vw; block-size: 55vw; inset-block-start: -18vw;
                           inset-inline-start: -12vw;
                           background: radial-gradient(circle,
                               color-mix(in srgb, var(--c-primary) 22%, transparent),
                               transparent 70%); }
        .jn-aurora__w--b { inline-size: 46vw; block-size: 46vw; inset-block-end: -14vw;
                           inset-inline-end: -10vw;
                           background: radial-gradient(circle,
                               color-mix(in srgb, var(--c-secondary) 13%, transparent),
                               transparent 70%); }
        .jn-aurora__w--c { inline-size: 38vw; block-size: 38vw; inset-block-end: 20%;
                           inset-inline-start: 26%;
                           background: radial-gradient(circle,
                               color-mix(in srgb, var(--c-accent-teal) 11%, transparent),
                               transparent 70%); }

        /* The vertical brand spine, kept from v1 — now glass-tipped. */
        .jn-rail { display: none; }
        @media (min-width: 64rem) {
            .jn-rail {
                display: flex; position: fixed; inset-block: 0; inset-inline-start: 0;
                width: 3.75rem; z-index: 60; flex-direction: column; align-items: center;
                justify-content: space-between; padding-block: 1.75rem;
                border-inline-end: 1px solid color-mix(in srgb, var(--c-primary) 22%, transparent);
                background: color-mix(in srgb, var(--c-background) 72%, transparent);
                backdrop-filter: blur(16px);
                writing-mode: vertical-rl; text-orientation: mixed;
                font-size: .68rem; letter-spacing: .32em; color: var(--c-muted);
                text-transform: uppercase;
            }
            .jn-rail__dot { inline-size: .55rem; block-size: .55rem; border-radius: 50%;
                            background: var(--c-secondary);
                            box-shadow: 0 0 12px color-mix(in srgb, var(--c-secondary) 60%, transparent); }
        }

        /* ---------- progress bar (driven by landings/{slug}/landing.js) --- */
        .jn-progress { position: fixed; inset-block-start: 0; inset-inline: 0; block-size: 3px;
                       z-index: 90; pointer-events: none;
                       background: linear-gradient(90deg, var(--c-primary), var(--c-secondary));
                       transform: scaleX(var(--jn-progress, 0));
                       transform-origin: 0 50%; }
        html[dir="rtl"] .jn-progress { transform-origin: 100% 50%; }

        /* ---------- glass header ----------------------------------------- */
        .jn-top { position: sticky; inset-block-start: 0; z-index: 70;
                  background: color-mix(in srgb, var(--c-background) 62%, transparent);
                  backdrop-filter: blur(18px) saturate(1.35);
                  border-block-end: 1px solid color-mix(in srgb, var(--c-primary) 16%, transparent); }
        .jn-top__in { display: flex; align-items: center; justify-content: space-between;
                      gap: 1rem; padding-block: .85rem; }
        .jn-brand { display: inline-flex; align-items: center; gap: .65rem;
                    font-family: var(--font-heading); font-weight: 900;
                    letter-spacing: -.01em; color: var(--c-text); text-decoration: none; }
        .jn-brand__mark { inline-size: 1.9rem; block-size: 1.9rem; display: grid; place-items: center;
                          border-radius: var(--radius-md);
                          background: linear-gradient(135deg, var(--c-primary), var(--c-accent-teal));
                          color: var(--c-on-primary); font-size: .9rem;
                          box-shadow: 0 0 18px color-mix(in srgb, var(--c-primary) 40%, transparent); }
        .jn-nav { display: none; align-items: center; gap: clamp(1rem, 2.4vw, 2rem); }
        @media (min-width: 56rem) { .jn-nav { display: flex; } }
        .jn-nav a { position: relative; color: var(--c-muted); text-decoration: none;
                    font-size: .9rem; padding-block: .35rem;
                    transition: color var(--reveal-duration) var(--ease); }
        .jn-nav a::after { content: ""; position: absolute; inset-inline: 0; inset-block-end: -.35rem;
                           block-size: 2px; border-radius: 2px;
                           background: var(--c-primary); transform: scaleX(0);
                           transition: transform var(--reveal-duration) var(--ease);
                           transform-origin: var(--jn-underline-origin, 0 50%); }
        html[dir="rtl"] .jn-nav a::after { transform-origin: 100% 50%; }
        .jn-nav a:hover::after, .jn-nav a:focus-visible::after { transform: scaleX(1); }
        .jn-nav a:hover, .jn-nav a:focus-visible { color: var(--c-text); }

        /* ---------- buttons: incl. the gradient CTA chip the nav stores --- */
        .jn-btn { display: inline-flex; align-items: center; justify-content: center; gap: .55rem;
                  padding: .8rem 1.5rem; font: inherit; font-weight: 600; text-decoration: none;
                  border-radius: var(--radius-button);
                  border: 1px solid transparent;
                  transition: background-color var(--reveal-duration) var(--ease),
                              border-color var(--reveal-duration) var(--ease),
                              color var(--reveal-duration) var(--ease),
                              box-shadow var(--reveal-duration) var(--ease),
                              translate var(--reveal-duration) var(--ease); }
        .jn-btn:hover { translate: 0 -2px; }
        .jn-btn--solid { background: var(--c-primary); color: var(--c-on-primary);
                         box-shadow: 0 8px 26px -10px color-mix(in srgb, var(--c-primary) 55%, transparent); }
        .jn-btn--solid:hover, .jn-btn--solid:focus-visible {
            background: var(--c-primary-hover);
            box-shadow: 0 12px 32px -10px color-mix(in srgb, var(--c-primary) 70%, transparent); }
        .jn-btn--grad { background: linear-gradient(135deg, var(--c-primary), var(--c-accent-teal));
                        color: var(--c-on-primary);
                        box-shadow: 0 8px 26px -10px color-mix(in srgb, var(--c-primary) 60%, transparent); }
        .jn-btn--grad:hover, .jn-btn--grad:focus-visible {
            box-shadow: 0 12px 34px -8px color-mix(in srgb, var(--c-primary) 75%, transparent); }
        .jn-btn--primary { background: var(--c-secondary); color: var(--c-background);
                           box-shadow: 0 8px 26px -10px color-mix(in srgb, var(--c-secondary) 55%, transparent); }
        .jn-btn--primary:hover, .jn-btn--primary:focus-visible {
            background: color-mix(in srgb, var(--c-secondary) 82%, var(--c-text)); }
        .jn-btn--ghost { color: var(--c-text); border-color: color-mix(in srgb, var(--c-border) 80%, transparent);
                         background: color-mix(in srgb, var(--c-surface) 30%, transparent); }
        .jn-btn--ghost:hover, .jn-btn--ghost:focus-visible { border-color: var(--c-primary); color: var(--c-primary); }

        /* ---------- hero --------------------------------------------------
           Split hero at the stored 60-40, glass panel carries the advisor
           facts; gradient-shift lives on the brand line (text_effect). */
        .jn-hero { position: relative; overflow: clip;
                   padding-block: clamp(3rem, 8vw, 6.5rem) clamp(2.5rem, 5vw, 4rem); }
        .jn-hero__grid { display: grid; gap: clamp(2rem, 5vw, 4rem); align-items: center; }
        @media (min-width: 60rem) {
            .jn-hero__grid { grid-template-columns: 1.45fr 1fr; align-items: center; }
        }
        .jn-hero__dots { position: absolute; inset-block-start: 8%; inset-inline-end: 0;
                         inline-size: clamp(7rem, 16vw, 13rem); aspect-ratio: 1;
                         background-image: radial-gradient(color-mix(in srgb, var(--c-text) 17%, transparent) 1px, transparent 1.35px);
                         background-size: 15px 15px;
                         mask-image: linear-gradient(215deg, black 5%, transparent 72%);
                         -webkit-mask-image: linear-gradient(215deg, black 5%, transparent 72%);
                         pointer-events: none; }
        .jn-kicker { display: inline-flex; align-items: center; gap: .6rem; margin: 0 0 1.4rem;
                     font-size: .74rem; letter-spacing: .26em; text-transform: uppercase;
                     color: var(--c-secondary); }
        .jn-kicker::before { content: ""; inline-size: 2.25rem; block-size: 2px;
                             background: linear-gradient(90deg, var(--c-secondary), transparent); }
        html[dir="rtl"] .jn-kicker::before { background: linear-gradient(270deg, var(--c-secondary), transparent); }
        .jn-title { position: relative; margin: 0; max-width: 16ch;
                    font-family: var(--font-heading); font-weight: 900;
                    font-size: clamp(2.3rem, 6vw, 4.9rem); line-height: 1.06; letter-spacing: -.03em; }
        .jn-grad { display: block;
                   background: linear-gradient(110deg, var(--c-primary) 15%, var(--c-secondary) 50%, var(--c-accent-teal) 85%);
                   background-size: 220% 100%; -webkit-background-clip: text; background-clip: text;
                   color: transparent; }
        html[data-animations="on"] .jn-grad { animation: jn-shift 9s ease-in-out infinite; }
        @keyframes jn-shift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .jn-title em { display: block; font-style: normal; }
        .jn-title em::after { content: ""; display: block; inline-size: clamp(3.5rem, 11vw, 8rem);
                              block-size: 4px; margin-block-start: .55rem; border-radius: 4px;
                              background: linear-gradient(90deg, var(--c-secondary), var(--c-accent-amber), transparent); }
        html[dir="rtl"] .jn-title em::after { background: linear-gradient(270deg, var(--c-secondary), var(--c-accent-amber), transparent); }
        .jn-sub { position: relative; max-width: 52ch; margin: 1.6rem 0 0;
                  color: var(--c-muted); line-height: 1.95; }
        .jn-actions { position: relative; display: flex; flex-wrap: wrap; gap: .7rem; margin-block-start: 2rem; }

        /* Glass capsule: concentric brand rings around the advisor badge. */
        .jn-orbit { position: relative; aspect-ratio: 1; max-inline-size: 24rem;
                    margin-inline: auto; display: grid; place-items: center; }
        .jn-orbit__ring { position: absolute; border-radius: 50%; pointer-events: none; }
        .jn-orbit__ring--a { inset: 0; border: 1px solid color-mix(in srgb, var(--c-primary) 30%, transparent);
                             border-block-start-color: transparent; transform: rotate(0.001deg); }
        .jn-orbit__ring--b { inset: 9%; border: 1px dashed color-mix(in srgb, var(--c-secondary) 40%, transparent);
                             border-radius: 50%; }
        .jn-orbit__ring--c { inset: 18%;
                             background: radial-gradient(circle at 34% 28%,
                                 color-mix(in srgb, var(--c-secondary) 24%, transparent),
                                 color-mix(in srgb, var(--c-primary) 16%, transparent) 46%,
                                 transparent 74%); }
        html[data-animations="on"] .jn-orbit__ring--b { animation: jn-spin 34s linear infinite; }
        html[data-animations="on"] .jn-orbit__ring--c { animation: jn-spin 46s linear infinite reverse; }
        @keyframes jn-spin { to { transform: rotate(360deg); } }
        .jn-orbit__core { position: relative; display: grid; gap: .4rem; padding: 1.5rem 1.75rem;
                          border-radius: var(--radius-lg);
                          background: color-mix(in srgb, var(--c-surface) 68%, transparent);
                          backdrop-filter: blur(18px) saturate(1.3);
                          border: 1px solid color-mix(in srgb, var(--c-border) 70%, transparent);
                          box-shadow: 0 24px 70px -30px color-mix(in srgb, var(--c-primary) 45%, transparent);
                          text-align: center; }
        .jn-orbit__num { margin: 0; font-family: var(--font-heading); font-weight: 900;
                         font-size: clamp(1.9rem, 4vw, 2.6rem); line-height: 1; color: var(--c-primary);
                         font-variant-numeric: tabular-nums; }
        .jn-orbit__lbl { margin: 0; font-size: .78rem; color: var(--c-muted); }
        .jn-foot__social .lp-icon { place-items: center; inline-size: 2rem; block-size: 2rem;
                                    aspect-ratio: 1; border-radius: var(--radius-md); }
        .jn-foot__social a { transition: color var(--reveal-duration) var(--ease),
                                          border-color var(--reveal-duration) var(--ease); }
        .jn-foot__social a:hover .lp-icon, .jn-foot__social a:focus-visible .lp-icon { color: var(--c-primary); }

        /* ---------- signature marquee band ---------------------------------
           A full-bleed band of the academy's own services, repeated and
           slowly drifting. It sits between the hero and the advisor so the
           brand reads before any section asks for attention. Colours come
           only from tokens, so the band repaints with the palette; the
           drift is gated on the tenant's animation switch AND on
           prefers-reduced-motion, like every other motion here. */
        .jn-marquee { position: relative; margin-block: clamp(2rem, 4vw, 3rem);
                      padding-block: .85rem;
                      border-block: 1px solid color-mix(in srgb, var(--c-primary) 26%, transparent);
                      background:
                          linear-gradient(90deg,
                              color-mix(in srgb, var(--c-primary) 10%, transparent),
                              transparent 22%, transparent 78%,
                              color-mix(in srgb, var(--c-secondary) 10%, transparent)),
                          color-mix(in srgb, var(--c-surface) 40%, transparent);
                      overflow: hidden; }
        /* The edges fade into the page instead of ending in a hard rule. */
        .jn-marquee::before, .jn-marquee::after {
            content: ""; position: absolute; inset-block: 0; inline-size: clamp(2rem, 8vw, 7rem);
            z-index: 1; pointer-events: none; }
        .jn-marquee::before { inset-inline-start: 0;
                              background: linear-gradient(90deg, var(--c-background), transparent); }
        .jn-marquee::after { inset-inline-end: 0;
                             background: linear-gradient(270deg, var(--c-background), transparent); }
        .jn-marquee__track { display: flex; gap: 2.75rem; inline-size: max-content;
                             will-change: transform; }
        html[data-animations="on"] .jn-marquee__track { animation: jn-drift 38s linear infinite; }
        html[data-animations="on"] .jn-marquee:hover .jn-marquee__track { animation-play-state: paused; }
        .jn-marquee__item { display: inline-flex; align-items: center; gap: .6rem;
                            font-size: .86rem; letter-spacing: .08em; white-space: nowrap;
                            color: color-mix(in srgb, var(--c-text) 82%, transparent); }
        .jn-marquee__item .lp-icon { color: var(--c-primary); font-size: .95rem; }
        /* The separator is a token-tinted diamond, not a bullet. */
        .jn-marquee__sep { inline-size: .4rem; block-size: .4rem; rotate: 45deg; flex: none;
                           background: linear-gradient(135deg, var(--c-primary), var(--c-secondary));
                           border-radius: 1px; }
        @keyframes jn-drift {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }
        /* RTL pages drift the other way so the band still reads as movement
           away from the reading edge. */
        html[dir="rtl"][data-animations="on"] .jn-marquee__track { animation-name: jn-drift-rtl; }
        @keyframes jn-drift-rtl {
            from { transform: translateX(0); }
            to   { transform: translateX(50%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .jn-marquee__track { animation: none !important; }
        }

        /* ---------- shared card chrome (top-accent edge, glass) ---------- */
        .jn-card { position: relative;
                   background: color-mix(in srgb, var(--c-surface) 72%, transparent);
                   backdrop-filter: blur(16px) saturate(1.25);
                   border: 1px solid color-mix(in srgb, var(--c-border) 75%, transparent);
                   border-radius: var(--radius-card);
                   transition: translate var(--reveal-duration) var(--ease),
                               border-color var(--reveal-duration) var(--ease),
                               box-shadow var(--reveal-duration) var(--ease); }
        .jn-card::before { content: ""; position: absolute; inset-block-start: 0;
                           inset-inline-start: 14%; inline-size: 72%; block-size: 2px; border-radius: 2px;
                           background: linear-gradient(90deg, transparent, var(--c-accent-emerald), var(--c-accent-amber), transparent); }
        html[dir="rtl"] .jn-card::before { background: linear-gradient(270deg, transparent, var(--c-accent-emerald), var(--c-accent-amber), transparent); }
        .jn-card--tilt:hover { translate: 0 -6px;
                               border-color: color-mix(in srgb, var(--c-primary) 45%, transparent);
                               box-shadow: 0 30px 80px -40px color-mix(in srgb, var(--c-primary) 55%, transparent); }

        .jn-sec { padding-block: clamp(2.75rem, 6vw, 4.5rem); }
        .jn-sec__h { position: relative; margin: 0 0 2.25rem; max-width: 30ch;
                     font-family: var(--font-heading); font-weight: 900;
                     font-size: clamp(1.6rem, 3.4vw, 2.4rem); letter-spacing: -.02em;
                     padding-block-end: .55rem; }
        .jn-sec__h::after { content: ""; position: absolute; inset-block-end: 0;
                            inset-inline-start: 0; inline-size: clamp(4rem, 12vw, 9rem);
                            block-size: 4px; border-radius: 4px;
                            background: linear-gradient(90deg, var(--c-primary), var(--c-secondary), transparent); }
        html[dir="rtl"] .jn-sec__h::after { background: linear-gradient(270deg, var(--c-primary), var(--c-secondary), transparent); }

        .jn-eco { display: grid; gap: .5rem; padding: clamp(1.25rem, 2.6vw, 1.75rem);
                  max-inline-size: 100%; }
        .jn-eco .jn-svc__x { font-size: 1rem; }

        /* ---------- bento services (config variant) ----------------------- */
        .jn-bento { display: grid; gap: var(--card-gap);
                    grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        @media (min-width: 60rem) {
            .jn-bento { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .jn-lead { grid-row: span 2; }
        }
        .jn-svc { padding: clamp(1.25rem, 2.6vw, 1.75rem); }
        .jn-svc .lp-icon { display: grid; place-items: center;
                           inline-size: 2.9rem; block-size: 2.9rem; aspect-ratio: 1;
                           border-radius: max(30%, var(--radius-md));
                           background: color-mix(in srgb, var(--c-background) 55%, transparent);
                           border: 1px solid color-mix(in srgb, var(--c-border) 65%, transparent);
                           font-size: 1.05rem;
                           transition: box-shadow var(--reveal-duration) var(--ease),
                                       border-color var(--reveal-duration) var(--ease); }
        .jn-svc:hover .lp-icon { border-color: color-mix(in srgb, var(--c-primary) 40%, transparent);
                                 box-shadow: 0 0 24px -6px color-mix(in srgb, var(--c-primary) 40%, transparent); }
        .jn-lead .lp-icon { inline-size: 3.4rem; block-size: 1.2rem; font-size: 1.2rem; }
        .jn-svc__t { margin: 0 0 .45rem; font-family: var(--font-heading); font-weight: 800; font-size: 1.08rem; }
        .jn-lead .jn-svc__t { font-size: 1.3rem; }
        .jn-svc__x { margin: 0; color: var(--c-muted); line-height: 1.9; font-size: .92rem; }

        /* ---------- boxed stats (config variant) -------------------------- */
        .jn-stats { display: grid; gap: var(--card-gap);
                    grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 48rem) { .jn-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .jn-stat { padding: clamp(1.1rem, 2.4vw, 1.6rem); text-align: center; }
        .jn-stat__v { margin: 0; font-family: var(--font-heading); font-weight: 900;
                      font-size: clamp(1.7rem, 3.6vw, 2.6rem); line-height: 1.05;
                      color: var(--c-primary); font-variant-numeric: tabular-nums; }
        .jn-stat[data-gradient="1"] .jn-stat__v {
                      background: linear-gradient(110deg, var(--c-primary), var(--c-secondary));
                      -webkit-background-clip: text; background-clip: text; color: transparent; }
        .jn-stat__l { margin: .55rem 0 0; font-size: .8rem; color: var(--c-muted);
                      padding-block-end: .5rem;
                      border-block-end: 3px solid color-mix(in srgb, var(--c-border) 60%, transparent); }
        .jn-stat[data-gradient="1"] .jn-stat__l { border-image: linear-gradient(90deg, var(--c-primary), var(--c-secondary)) 1; border-image-slice: 0 0 1 0; }

        /* ---------- advisor plate: rings + grayscale portrait, glass ----- */
        .jn-plate { display: grid; gap: clamp(1.75rem, 4vw, 3.25rem); align-items: center; }
        @media (min-width: 60rem) { .jn-plate { grid-template-columns: .8fr 1.2fr; } }
        .jn-portrait { position: relative; aspect-ratio: 4 / 5; max-inline-size: 22rem;
                       justify-self: center; }
        .jn-portrait__frame { position: absolute; inset: 0; overflow: hidden;
                              border-radius: var(--radius-lg);
                              border: 1px solid color-mix(in srgb, var(--c-primary) 30%, transparent);
                              background: linear-gradient(158deg, color-mix(in srgb, var(--c-primary) 20%, var(--c-surface)), var(--c-surface) 62%); }
        .jn-portrait__img { inline-size: 100%; block-size: 100%; object-fit: cover; display: block;
                            filter: grayscale(1) contrast(1.03);
                            transition: filter calc(var(--reveal-duration) * 2) var(--ease); }
        .jn-portrait__frame:hover .jn-portrait__img { filter: grayscale(0); }
        .jn-portrait__mono { position: absolute; inset: 0; display: grid; place-items: center;
                             font-family: var(--font-heading); font-weight: 900;
                             font-size: clamp(3.5rem, 9vw, 6rem); color: var(--c-primary); }
        .jn-portrait__badge { position: absolute; inset-block-end: .9rem; inset-inline-start: .9rem;
                              padding: .35rem .8rem; border-radius: var(--radius-button);
                              font-size: .72rem; font-weight: 700;
                              background: color-mix(in srgb, var(--c-surface) 82%, transparent);
                              backdrop-filter: blur(10px);
                              color: var(--c-secondary);
                              border: 1px solid color-mix(in srgb, var(--c-secondary) 40%, transparent); }
        .jn-portrait__r1, .jn-portrait__r2 { position: absolute; border-radius: 50%; pointer-events: none; }
        .jn-portrait__r1 { inset: -7%; border: 1px dashed color-mix(in srgb, var(--c-primary) 35%, transparent); }
        .jn-portrait__r2 { inset: -13%; border: 1px solid color-mix(in srgb, var(--c-secondary) 25%, transparent);
                           border-block-end-color: transparent; border-inline-start-color: transparent; }
        html[data-animations="on"] .jn-portrait__r1 { animation: jn-spin 40s linear infinite; }
        html[data-animations="on"] .jn-portrait__r2 { animation: jn-spin 60s linear infinite reverse; }
        .jn-adv__name { margin: 0; font-family: var(--font-heading); font-weight: 900;
                        font-size: clamp(1.5rem, 3.4vw, 2.2rem); letter-spacing: -.02em; }
        .jn-adv__tag { margin: .4rem 0 0; color: var(--c-secondary); font-weight: 600; }
        .jn-adv__bio { margin: 1.1rem 0 0; color: var(--c-muted); line-height: 2; }

        /* ---------- masonry quotes (config variant) ----------------------- */
        .jn-quotes { columns: 1; column-gap: var(--card-gap); }
        @media (min-width: 60rem) { .jn-quotes { columns: 3; } }
        .jn-quote { break-inside: avoid; margin: 0 0 var(--card-gap);
                    padding: 1.6rem 1.5rem 1.4rem; }
        .jn-quote__mark { font-family: var(--font-heading); font-size: 2.6rem; line-height: .7;
                          color: color-mix(in srgb, var(--c-secondary) 65%, transparent); }
        .jn-quote__x { margin: .5rem 0 1.2rem; color: var(--c-text); line-height: 1.95; font-size: .93rem; }
        .jn-quote__who { display: flex; align-items: center; gap: .7rem; }
        .jn-quote__who > span:last-child > span { display: block; }
        .jn-quote__ini { display: grid; place-items: center; inline-size: 2.2rem; block-size: 2.2rem;
                         border-radius: var(--radius-md); font-size: .74rem; font-weight: 700;
                         color: var(--c-on-primary); }

        /* ---------- faq, glass rows -------------------------------------- */
        .jn-faq details { padding: 1.1rem 1.25rem; margin-block-end: .75rem; }
        .jn-faq summary { display: flex; align-items: flex-start; justify-content: space-between;
                          gap: 1rem; cursor: pointer; list-style: none;
                          font-family: var(--font-heading); font-weight: 700; font-size: 1rem; }
        .jn-faq summary::-webkit-details-marker { display: none; }
        .jn-faq summary::after { content: "+"; color: var(--c-primary); font-size: 1.25rem;
                                 line-height: 1; flex: none;
                                 transition: rotate var(--reveal-duration) var(--ease); }
        .jn-faq details[open] summary::after { content: "\2013"; }
        .jn-faq p { margin: .8rem 0 0; color: var(--c-muted); line-height: 1.95; font-size: .92rem; }

        /* ---------- closing band ----------------------------------------- */
        .jn-band { position: relative; overflow: clip; text-align: center;
                   border-block-start: 1px solid color-mix(in srgb, var(--c-border) 70%, transparent);
                   padding-block: clamp(3rem, 7vw, 5.5rem); }
        .jn-band::before { content: ""; position: absolute; inset-block-start: 30%; inset-inline: -20%;
                           block-size: 12rem; filter: blur(80px);
                           background: radial-gradient(ellipse, color-mix(in srgb, var(--c-primary) 16%, transparent), transparent 70%); }
        .jn-band__wm { position: absolute; inset-inline: 0; inset-block-end: -.28em;
                       font-family: var(--font-heading); font-weight: 900;
                       font-size: clamp(4.5rem, 17vw, 12rem); line-height: .8; letter-spacing: -.04em;
                       color: color-mix(in srgb, var(--c-primary) 12%, transparent);
                       pointer-events: none; user-select: none; }
        .jn-band__t { position: relative; margin: 0 auto; max-width: 34ch;
                      font-family: var(--font-heading); font-weight: 900;
                      font-size: clamp(1.35rem, 3.2vw, 2.1rem); line-height: 1.55; }
        .jn-band__a { position: relative; display: flex; flex-wrap: wrap; justify-content: center;
                      gap: .7rem; margin-block-start: 1.8rem; }

        /* ---------- footer ------------------------------------------------ */
        .jn-foot { border-block-start: 1px solid color-mix(in srgb, var(--c-border) 70%, transparent);
                   padding-block: clamp(2rem, 4vw, 3rem) 1.5rem; }
        .jn-foot__grid { display: grid; gap: 2rem; }
        @media (min-width: 60rem) { .jn-foot__grid { grid-template-columns: 1.4fr 1fr 1fr; } }
        .jn-foot__blurb { margin: .9rem 0 0; max-width: 42ch; color: var(--c-muted);
                          font-size: .88rem; line-height: 1.95; }
        .jn-foot__h { margin: 0 0 .9rem; font-family: var(--font-heading); font-size: .8rem;
                      letter-spacing: .18em; text-transform: uppercase; color: var(--c-secondary); }
        .jn-foot__links { display: grid; gap: .55rem; }
        .jn-foot__links a { color: var(--c-muted); text-decoration: none; font-size: .89rem;
                            transition: color var(--reveal-duration) var(--ease); }
        .jn-foot__links a:hover, .jn-foot__links a:focus-visible { color: var(--c-primary); }
        .jn-foot__social { display: flex; gap: .5rem; margin-block-start: 1.15rem; }
        .jn-foot__social a { display: grid; place-items: center; inline-size: 2rem; block-size: 2rem;
                             border-radius: var(--radius-md); text-decoration: none;
                             font-size: .72rem; color: var(--c-muted);
                             border: 1px solid color-mix(in srgb, var(--c-border) 70%, transparent); }
        .jn-foot__social a:hover, .jn-foot__social a:focus-visible {
            color: var(--c-primary); border-color: var(--c-primary); }
        .jn-foot__base { display: flex; flex-wrap: wrap; justify-content: space-between;
                         gap: .5rem; margin-block-start: 2rem; padding-block-start: 1.2rem;
                         border-block-start: 1px solid color-mix(in srgb, var(--c-border) 70%, transparent);
                         font-size: .76rem; color: var(--c-muted); }

        /* ---------- reveal on scroll (armed by landing.js; without JS the
             page is fully visible) ---------------------------------------- */
        html[data-animations="on"].jn-obs .jn-rise { opacity: 0; translate: 0 1.4rem; }
        html[data-animations="on"].jn-obs .jn-rise.jn-in {
            opacity: 1; translate: 0 0;
            transition: opacity calc(var(--reveal-duration) * 2) var(--ease),
                        translate calc(var(--reveal-duration) * 2) var(--ease);
        }
        html[data-animations="on"].jn-obs .jn-rise.jn-in:nth-child(2) { transition-delay: 80ms; }
        html[data-animations="on"].jn-obs .jn-rise.jn-in:nth-child(3) { transition-delay: 160ms; }
        html[data-animations="on"].jn-obs .jn-rise.jn-in:nth-child(4) { transition-delay: 240ms; }
        @media (prefers-reduced-motion: reduce) {
            .jn *, .jn *::before, .jn *::after { animation: none !important; transition: none !important; }
            html[data-animations="on"] .jn-grad, html[data-animations="on"] .jn-orbit__ring--b,
            html[data-animations="on"] .jn-orbit__ring--c, html[data-animations="on"] .jn-portrait__r1,
            html[data-animations="on"] .jn-portrait__r2 { animation: none !important; }
        }

        /* ---------- blog (landing data contract, class B) -----------------
             Fed by the composer ($public.landing.blog.items, the BlogPost::
             toLandingItem shape). The composer drops `blog` from the section
             list while nothing is published, so this block never renders an
             empty shell — the gate below is the only render path. */
        .jn-blog { display: grid; gap: var(--card-gap);
                   grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        .jn-post { display: flex; flex-direction: column; overflow: clip; }
        .jn-post__plate { display: grid; place-items: center; block-size: 8.5rem;
                          inline-size: 100%; }
        .jn-post__plate img { inline-size: 100%; block-size: 100%; object-fit: cover; }
        .jn-post__b { display: grid; gap: .45rem;
                      padding: clamp(1.25rem, 2.6vw, 1.75rem); }
        .jn-post__t { margin: 0; font-family: var(--font-heading); font-weight: 800;
                      font-size: 1.08rem; }
        .jn-post__x { margin: 0; color: var(--c-muted); font-size: .92rem;
                      display: -webkit-box; -webkit-box-orient: vertical;
                      -webkit-line-clamp: 2; overflow: hidden; }
        .jn-blog__more { display: flex; justify-content: center;
                         margin-block-start: 2rem; }
    </style>
</head>
<body class="jn">

<div class="jn-aurora" aria-hidden="true">
    <div class="jn-aurora__w jn-aurora__w--a"></div>
    <div class="jn-aurora__w jn-aurora__w--b"></div>
    <div class="jn-aurora__w jn-aurora__w--c"></div>
</div>

<div class="jn-progress" role="presentation"></div>

<div class="jn-rail" aria-hidden="true">
    <span class="jn-rail__dot"></span>
    <span>{{ $tenant['short_name'] ?? $tenant['name'] }}</span>
    <span>{{ date('Y') }}</span>
</div>

<div class="jn-shell">

    <header class="jn-top">
        <div class="jn-wrap jn-top__in">
            <a class="jn-brand" href="{{ route('home') }}">
                <span class="jn-brand__mark" aria-hidden="true">{{ $monogram }}</span>
                <span>{{ $tenant['name'] }}</span>
            </a>

            @if (($nav['enabled'] ?? true))
                <nav class="jn-nav" aria-label="{{ $tenant['name'] }}">
                    @foreach (($nav['links'] ?? []) as $link)
                        @if (($link['visible'] ?? true))
                            <a href="{{ $link['href'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                        @endif
                    @endforeach
                </nav>
            @endif

            @php
                $topCta = collect($L['hero']['buttons'] ?? [])->firstWhere('visible', true);
            @endphp
            @if ($topCta)
                <a class="jn-btn jn-btn--grad" href="{{ $hrefOf($topCta) }}">
                    {{ $topCta['label'] ?? '' }}
                </a>
            @endif
        </div>
    </header>

    <main>

        <section class="jn-hero">
            <div class="jn-wrap">
                <div class="jn-hero__dots" aria-hidden="true"></div>

                <div class="jn-hero__grid">
                    <div>
                        <p class="jn-kicker">{{ $adv['eyebrow']['label'] ?? ($tenant['role_label'] ?? '') }}</p>

                        <h1 class="jn-title">
                            {{ $L['hero']['title_line1'] ?? '' }}
                            <em><span class="jn-grad">{{ $L['hero']['title_line2'] ?? $tenant['name'] }}</span></em>
                        </h1>

                        <p class="jn-sub">{{ $L['hero']['subtitle'] ?? '' }}</p>

                        <div class="jn-actions">
                            @foreach (($L['hero']['buttons'] ?? []) as $b)
                                @if (($b['visible'] ?? true))
                                    <a class="jn-btn {{ $styleOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @if (!empty($adv['stats']))
                    <div class="jn-orbit">
                        <div class="jn-orbit__ring jn-orbit__ring--a"></div>
                            <div class="jn-orbit__ring jn-orbit__ring--b"></div>
                            <div class="jn-orbit__ring jn-orbit__ring--c"></div>
                            <div class="jn-orbit__core">
                            @foreach (($adv['stats'] ?? []) as $f)
                                <div>
                                    <p class="jn-orbit__num">{{ $f['value'] ?? '' }}</p>
                                    <p class="jn-orbit__lbl">{{ $f['label'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{--
            Signature marquee: the academy's OWN service titles, stated twice
            so the loop is seamless. Content is read from the tenant config
            (services.items[].title) — never invented here — and the band is
            skipped entirely when the tenant has no services, so a sparse
            config never renders an empty strip.
        --}}
        @php
            $marquee = collect($L['services']['items'] ?? [])
                ->pluck('title')
                ->filter(static fn ($t) => is_string($t) && trim($t) !== '')
                ->values();
        @endphp

        @if ($marquee->isNotEmpty())
            <div class="jn-marquee" aria-hidden="true">
                <div class="jn-marquee__track">
                    @foreach ($marquee->concat($marquee) as $title)
                        <span class="jn-marquee__item">
                            {{-- The tenant's own first service icon, reused as
                                 the band's ornament — no new icon is invented. --}}
                            <i class="lp-icon {{ $L['services']['items'][0]['icon'] ?? '' }}" aria-hidden="true"></i>
                            {{ $title }}
                        </span>
                        <span class="jn-marquee__sep"></span>
                    @endforeach
                </div>
            </div>
        @endif

        @if (($adv['name'] ?? null))
            <section class="jn-sec" id="{{ $adv['id'] ?? 'about' }}">
                <div class="jn-wrap">
                    <div class="jn-plate">
                        <div class="jn-portrait">
                            <div class="jn-portrait__r1" aria-hidden="true"></div>
                            <div class="jn-portrait__r2" aria-hidden="true"></div>
                            <div class="jn-portrait__frame jn-card jn-card--tilt">
                                @if ($advisorImage && file_exists(public_path($advisorImage)))
                                    <img class="jn-portrait__img" src="{{ tenant_asset($adv['image']) }}"
                                         alt="{{ $adv['image_alt'] ?? $adv['name'] }}" loading="lazy">
                                @else
                                    <span class="jn-portrait__mono" aria-hidden="true">{{ $monogram }}</span>
                                @endif

                                @if (($adv['badge']['visible'] ?? false) && ($adv['badge']['label'] ?? null))
                                    <span class="jn-portrait__badge">{{ $adv['badge']['label'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h2 class="jn-adv__name">{{ $adv['name'] }}</h2>
                            <p class="jn-adv__tag">{{ $adv['tagline'] ?? '' }}</p>
                            <p class="jn-adv__bio">{{ $adv['bio'] ?? '' }}</p>

                            @if (!empty($adv['buttons']))
                                <div class="jn-actions">
                                    @foreach ($adv['buttons'] as $b)
                                        @if (($b['visible'] ?? true))
                                            <a class="jn-btn {{ $styleOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['ecosystem']['text']))
            <section class="jn-sec">
                <div class="jn-wrap">
                    <div class="jn-card jn-eco jn-rise" data-reveal>
                        <p class="jn-svc__x">{{ $L['ecosystem']['text'] }}</p>
                    </div>
                </div>
            </section>
        @endif

        <section class="jn-sec" id="services">
            <div class="jn-wrap">
                <h2 class="jn-sec__h">{{ $L['services']['heading'] ?? 'خدمات' }}</h2>

                <div class="jn-bento">
                    @foreach (($L['services']['items'] ?? []) as $i => $svc)
                        <article class="jn-card jn-card--tilt jn-svc @if ($i === 0) jn-lead @endif jn-rise" data-reveal>
                            @include('public.sections._icon', [
                                'icon' => $svc['icon'] ?? '',
                                'accent' => $accentOf($svc['accent'] ?? null),
                            ])
                            <h3 class="jn-svc__t">{{ $svc['title'] ?? '' }}</h3>
                            <p class="jn-svc__x">{{ $svc['text'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        @if (!empty($L['stats']['items']))
            <section class="jn-sec">
                <div class="jn-wrap">
                    <div class="jn-stats">
                        @foreach (($L['stats']['items'] ?? []) as $s)
                            @if (($s['visible'] ?? true))
                                <div class="jn-card jn-card--tilt jn-stat jn-rise"
                                     data-reveal
                                     data-counter="{{ $s['value'] ?? 0 }}"
                                     data-suffix="{{ $s['suffix'] ?? '' }}"
                                     data-gradient="{{ ($s['gradient'] ?? false) ? '1' : '0' }}">
                                    <p class="jn-stat__v">{{ $s['value'] ?? '' }}{{ $s['suffix'] ?? '' }}</p>
                                    <p class="jn-stat__l">{{ $s['label'] ?? '' }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['testimonials']['items']))
            <section class="jn-sec" id="testimonials">
                <div class="jn-wrap">
                    <h2 class="jn-sec__h">{{ $L['testimonials']['heading'] ?? 'داستان موفقیت' }}</h2>

                    <div class="jn-quotes">
                        @foreach (($L['testimonials']['items'] ?? []) as $q)
                            @if (($q['visible'] ?? true))
                                <figure class="jn-card jn-quote jn-rise" data-reveal>
                                    <div class="jn-quote__mark" aria-hidden="true">”</div>
                                    <blockquote class="jn-quote__x">{{ $q['text'] ?? '' }}</blockquote>
                                    <figcaption class="jn-quote__who">
                                        <span class="jn-quote__ini" aria-hidden="true"
                                              style="background: linear-gradient(135deg, {{ $accentOf($q['from'] ?? null) }}, {{ $accentOf($q['to'] ?? null) }})">
                                            {{ $q['initials'] ?? '' }}
                                        </span>
                                        <span>
                                            <span class="jn-quote__n">{{ $q['name'] ?? '' }}</span>
                                            <span class="jn-quote__r">{{ $q['result'] ?? '' }}</span>
                                        </span>
                                    </figcaption>
                                </figure>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (in_array('blog', (array) ($L['sections'] ?? []), true) && !empty($L['blog']['items']))
            <section class="jn-sec" id="{{ $L['blog']['id'] ?? 'blog' }}">
                <div class="jn-wrap">
                    <h2 class="jn-sec__h">{{ $L['blog']['heading'] ?? 'آخرین مقالات' }}</h2>

                    <div class="jn-blog">
                        @foreach (($L['blog']['items'] ?? []) as $item)
                            @continue(! ($item['visible'] ?? true))
                            <a class="jn-card jn-card--tilt jn-rise jn-post"
                               href="{{ $item['url'] ?? '#' }}" data-reveal>
                                <span class="jn-post__plate" aria-hidden="true"
                                      style="background: linear-gradient(135deg, {{ $accentOf($item['from'] ?? null) }}, {{ $accentOf($item['to'] ?? null) }})">
                                    @if (!empty($item['image']))
                                        <img src="{{ tenant_asset($item['image']) }}" alt="{{ $item['title'] ?? '' }}">
                                    @endif
                                </span>
                                <span class="jn-post__b">
                                    <h3 class="jn-post__t">{{ $item['title'] ?? '' }}</h3>
                                    <p class="jn-post__x">{{ $item['excerpt'] ?? '' }}</p>
                                </span>
                            </a>
                        @endforeach
                    </div>

                    @if (($L['blog']['see_all']['visible'] ?? true))
                        <div class="jn-blog__more">
                            <a class="jn-btn jn-btn--ghost"
                               href="{{ $L['blog']['see_all']['href'] ?? '#' }}">{{ $L['blog']['see_all']['label'] ?? '' }}</a>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if (!empty($L['faq']['items']))
            <section class="jn-sec" id="faq">
                <div class="jn-wrap">
                    <h2 class="jn-sec__h">{{ $L['faq']['heading'] ?? 'پرسش‌های متداول' }}</h2>

                    <div class="jn-faq">
                        @foreach (($L['faq']['items'] ?? []) as $f)
                            @if (($f['visible'] ?? true))
                                <details class="jn-card">
                                    <summary>{{ $f['question'] ?? '' }}</summary>
                                    <p>{{ $f['answer'] ?? '' }}</p>
                                </details>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (!empty($L['cta']['text']))
            <section class="jn-band">
                <div class="jn-band__wm" aria-hidden="true">{{ $tenant['short_name'] ?? $tenant['name'] }}</div>
                <div class="jn-wrap">
                    <p class="jn-band__t">{{ $L['cta']['text'] }}</p>
                    <div class="jn-band__a">
                        @foreach (($L['cta']['buttons'] ?? []) as $b)
                            @if (($b['visible'] ?? true))
                                <a class="jn-btn {{ $styleOf($b) }}" href="{{ $hrefOf($b) }}">{{ $b['label'] ?? '' }}</a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

    </main>

    <footer class="jn-foot">
        <div class="jn-wrap">
            <div class="jn-foot__grid">
                <div>
                    <a class="jn-brand" href="{{ route('home') }}">
                        <span class="jn-brand__mark" aria-hidden="true">{{ $monogram }}</span>
                        <span>{{ $tenant['name'] }}</span>
                    </a>
                    <p class="jn-foot__blurb">{{ $foot['blurb'] ?? '' }}</p>

                    @if (!empty($foot['social']))
                        <div class="jn-foot__social">
                            @foreach ($foot['social'] as $s)
                                @if (($s['visible'] ?? true))
                                    <a href="{{ $s['url'] ?? '#' }}" aria-label="{{ $s['label'] ?? '' }}"
                                       rel="noopener noreferrer">
                                        @include('public.sections._icon', ['icon' => $s['icon'] ?? '', 'accent' => 'var(--c-muted)'])
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                @foreach (($foot['columns'] ?? []) as $col)
                    <nav aria-label="{{ $col['title'] ?? '' }}">
                        <p class="jn-foot__h">{{ $col['title'] ?? '' }}</p>
                        <div class="jn-foot__links">
                            @foreach (($col['links'] ?? []) as $l)
                                <a href="{{ $l['href'] ?? '#' }}">{{ $l['label'] ?? '' }}</a>
                            @endforeach
                        </div>
                    </nav>
                @endforeach
            </div>

            <div class="jn-foot__base">
                <span>{{ $tenant['name'] }}</span>
                <span>{{ date('Y') }}</span>
            </div>
        </div>
    </footer>

</div>

<script src="{{ asset('landings/tenant-one/landing.js') }}" defer></script>
</body>
</html>
