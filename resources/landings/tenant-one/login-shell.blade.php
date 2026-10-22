{{--
    Bespoke login shell — آکادمی جنت (tenant-one).

    Presentation only. The credential card itself is the SHARED
    auth.partials.login-card partial, included unchanged, so this page
    inherits the whole security contract rather than re-implementing it:
    both guards' forms, @csrf, the throttle:login route it posts to, the
    failed-login tab re-open, and the whereIdentifier lookup all live in
    that partial and in the controller — none of it is touched here.

    What this file owns is only the FRAME around the card. Every colour is a
    token, so the page repaints with the tenant's palette exactly like the
    landing and the dashboard.

    DESIGN (the "SoftQA" reference the owner approved, adapted to RTL):

      - one white-ish panel floating on a tinted ground, split into a
        column stack on narrow screens and a text column + brand column
        above 60rem;
      - the card column is the "paper": it dissolves the shared card's own
        chrome (surface fill / border / shadow / radius) so the card sits
        flush on the panel instead of being a glass box inside a box —
        the double-box defect the previous shell had;
      - the brand column is one solid field of the tenant's primary, with
        the advisor's own portrait, quote and stats. No gradient text, no
        aurora blobs, no frosted glass: the reference is quiet and solid;
      - the field is a TOKEN MIX, never an ink literal: it walks from the
        tenant's primary toward its background, so it stays legible for a
        pale brand as well as a dark one (see --jn-auth-field).

    Rules this file obeys (docs/landing-data.md, AGENTS.md §7.9):
      - tokens are the brand contract: zero hard-coded colour below;
      - no inline script (the CSP allows none) — the tab switcher's
        behaviour comes from the shared card's own nonce'd script, which
        the layout below keeps;
      - no tenant content is invented: the aside reads the tenant's own
        advisor name/bio/tagline/stats/portrait from config.
--}}
@php
    $c = $public['login'];
    $s = $public['student_login'];
    $tabs = $c['tabs'] ?? ['consultant' => 'مشاور', 'student' => 'دانش‌آموز'];

    // Same resolution the shared view performs, so a failed login re-opens
    // the tab the visitor actually submitted.
    $active = old('role', request('tab')) === 'student' ? 'student' : 'consultant';

    $adv = $public['landing']['advisor'] ?? [];
    $stats = collect($adv['stats'] ?? [])->take(3)->values();

    // The advisor's portrait, resolved the way the landing resolves it
    // (landing.blade.php:53) and only rendered when the file is really
    // there — otherwise the block falls back to the tenant's monogram.
    $advisorImage = isset($adv['image'])
        ? 'tenants/'.($tenant['slug'] ?? '').'/'.ltrim((string) $adv['image'], '/')
        : null;
    $hasPortrait = $advisorImage !== null && file_exists(public_path($advisorImage));

    $monogram = mb_substr((string) ($adv['name'] ?? $tenant['name']), 0, 1);

    // The real name of whoever signs in — consultancy staff or the academy's
    // own students. Read from config, never invented.
    $who = trim(implode(' · ', array_filter([
        (string) ($tabs['consultant'] ?? ''),
        (string) ($tabs['student'] ?? ''),
    ])));

    $isStaff = $active === 'consultant';
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}"
      data-auth-surface="{{ $tenant['slug'] ?? ($theme['slug'] ?? 'tenant-one') }}" @include('partials.theme-attrs')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $c['title'] }} — {{ $tenant['name'] }}</title>

    @include('partials.theme-vars')

    <style>
        /* ---------- the solid field --------------------------------------
           The brand column's ground. It is a MIX of the tenant's own
           tokens, not a literal, so it works for a dark emerald brand
           (tenant-one) and a pale one without a second rule:

             primary 82% + background 18%   — the brand, deepened a shade
             primary 52% + background 48%   — the same field, shaded, for
                                              the portrait halo and the
                                              stacked (mobile) variant
           Text on it is the tenant's own on_primary, which ThemeTokens
           already solves against primary. */
        .jn-auth {
            --jn-auth-field: color-mix(in srgb, var(--c-primary) 82%, var(--c-background));
            --jn-auth-field-deep: color-mix(in srgb, var(--c-primary) 55%, var(--c-background));
            --jn-auth-ink: var(--c-on-primary);

            min-block-size: 100svh;
            display: grid;
            place-items: center;
            padding: clamp(1rem, 3vw, 2.5rem);
            background: color-mix(in srgb, var(--c-primary) 8%, var(--c-background));
            color: var(--c-text);
            font-family: var(--font-body, var(--font-heading));
        }

        /* ---------- the floating panel ---------------------------------- */
        .jn-auth__panel {
            inline-size: 100%;
            max-inline-size: 68rem;
            background: var(--c-background);
            border: 1px solid color-mix(in srgb, var(--c-border) 80%, transparent);
            border-radius: min(var(--radius-lg), 1.1rem);
            overflow: hidden;
            box-shadow: 0 1.5rem 3.5rem color-mix(in srgb, var(--c-background) 55%, transparent);
            display: grid;
            grid-template-rows: auto auto;
        }

        /* ---------- column A: the form ----------------------------------
           The "paper". Its own ground is the tenant's surface (a card on a
           darker page), which is what makes the inputs read as wells. */
        .jn-auth__form {
            background: var(--c-surface);
            color: var(--c-text);
            padding: clamp(1.75rem, 4vw, 3.25rem) clamp(1.5rem, 4vw, 3.5rem);
            display: flex;
            align-items: center;
        }
        .jn-auth__stack {
            inline-size: 100%;
            max-inline-size: 26rem;
            margin-inline: auto;
        }

        /* ---------- the brand lockup (replaces the card's own <h1>) ----- */
        .jn-auth__brand {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 1.05rem;
            line-height: 1.2;
            margin-block-end: clamp(1.75rem, 4vw, 2.75rem);
        }
        .jn-auth__mark {
            display: grid;
            place-items: center;
            flex: none;
            inline-size: 2.35rem;
            block-size: 2.35rem;
            border-radius: var(--radius-md);
            background: var(--c-primary);
            color: var(--c-on-primary);
            font-size: 1.05rem;
            font-family: var(--font-heading);
            font-weight: 800;
        }

        /* ---------- the shared card, de-chromed -------------------------
           auth.partials.login-card ships as a glass box: surface fill,
           1px border, shadow, radius, its own 2rem padding and its own
           centred <h1> of the tenant name. Inside a panel that is already
           a surface, that is box-in-box — and the name would appear twice
           (lockup + heading). This column dissolves those four properties
           and hides the duplicated heading. Nothing inside the card is
           edited: it is included unchanged below. */
        .jn-auth__card .login-card {
            padding: 0 !important;
            background: none !important;
            border: 1px solid transparent !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            max-width: none !important;
        }
        .jn-auth__card .login-card > h1 { display: none; }

        /* The card's §"consultant/student" heading + subtitle sit inside
           each form; the shell shows its own once above the tabs. */
        .jn-auth__card .login-card form > h2,
        .jn-auth__card .login-card form > p {
            /* kept in the DOM for screen readers and for tests that read
               the copy — only visually collapsed */
            position: absolute;
            inline-size: 1px;
            block-size: 1px;
            overflow: hidden;
            clip-path: inset(50%);
            white-space: nowrap;
        }

        /* The way back to the site and the sign-up link come from the card
           itself; the shell only spaces them. */
        .jn-auth__foot { text-align: center; margin-block-start: 1.5rem; }

        /* ---------- column B: the brand field --------------------------- */
        .jn-auth__aside {
            position: relative;
            display: none;
            flex-direction: column;
            justify-content: space-between;
            gap: clamp(1.5rem, 3vw, 2.5rem);
            padding: clamp(2rem, 4vw, 3.25rem);
            background: var(--jn-auth-field);
            color: var(--jn-auth-ink);
            overflow: hidden;
        }
        .jn-auth__aside::after {
            /* one soft light source, top corner — a wash, not a blob */
            content: "";
            position: absolute;
            inset-block-start: -30%;
            inset-inline-end: -25%;
            inline-size: 70%;
            aspect-ratio: 1;
            border-radius: 50%;
            background: radial-gradient(circle,
                color-mix(in srgb, var(--c-primary) 55%, var(--jn-auth-ink) 12%) 0%,
                transparent 68%);
            opacity: .55;
            pointer-events: none;
        }
        .jn-auth__aside > * { position: relative; z-index: 1; }

        .jn-auth__kicker {
            margin: 0 0 .9rem;
            font-size: .7rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            opacity: .75;
        }
        .jn-auth__title {
            margin: 0;
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: clamp(1.6rem, 2.6vw, 2.15rem);
            line-height: 1.35;
        }
        .jn-auth__lede {
            margin: 1.1rem 0 0;
            font-size: .94rem;
            line-height: 1.9;
            opacity: .85;
        }

        /* ---------- the advisor's own quote ----------------------------- */
        .jn-auth__quote { margin: 0; }
        .jn-auth__quote::before {
            content: "\201C";
            display: block;
            font-family: var(--font-heading);
            font-size: 2.6rem;
            line-height: .6;
            margin-block-end: .55rem;
            opacity: .5;
        }
        .jn-auth__quote p {
            margin: 0;
            font-size: .96rem;
            line-height: 1.95;
            max-inline-size: 34ch;
        }
        .jn-auth__who {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-block-start: 1.1rem;
        }
        .jn-auth__avatar {
            display: grid;
            place-items: center;
            flex: none;
            inline-size: 2.9rem;
            block-size: 2.9rem;
            border-radius: 50%;
            overflow: hidden;
            background: color-mix(in srgb, var(--jn-auth-ink) 14%, transparent);
            border: 1px solid color-mix(in srgb, var(--jn-auth-ink) 28%, transparent);
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 1.05rem;
        }
        .jn-auth__avatar img {
            inline-size: 100%;
            block-size: 100%;
            object-fit: cover;
            display: block;
            filter: grayscale(1) contrast(1.05);
        }
        .jn-auth__who b {
            display: block;
            font-size: .9rem;
            font-weight: 700;
        }
        .jn-auth__who span {
            display: block;
            font-size: .78rem;
            opacity: .8;
        }

        /* ---------- the tenant's own numbers ---------------------------- */
        .jn-auth__stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0 clamp(1.25rem, 3vw, 2.25rem);
            margin: 0;
            padding-block-start: 1.25rem;
            border-block-start: 1px solid color-mix(in srgb, var(--jn-auth-ink) 22%, transparent);
            list-style: none;
        }
        .jn-auth__stat { padding: 0; }
        .jn-auth__stat b {
            display: block;
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 1.35rem;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }
        .jn-auth__stat span {
            display: block;
            font-size: .74rem;
            opacity: .78;
        }

        /* ---------- above 60rem: two real columns ----------------------- */
        @media (min-width: 60rem) {
            .jn-auth__panel {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: none;
            }
            .jn-auth__aside { display: flex; }
        }

        /* ---------- stacked: the field becomes a short header -----------
           Below the breakpoint the brand is not context worth a full
           screen — but dropping it entirely (what the previous shell did)
           loses the tenant's identity on the page everyone sees on a
           phone. So it collapses to one tinted band: quote + who. */
        @media (max-width: 59.99rem) {
            .jn-auth__aside {
                display: flex;
                order: 2;
                background: var(--jn-auth-field-deep);
                padding: 1.5rem clamp(1.5rem, 4vw, 2rem);
                gap: 1rem;
            }
            .jn-auth__aside::after { display: none; }
            .jn-auth__head, .jn-auth__stats { display: none; }
            .jn-auth__who { margin-block-start: 0; }
            .jn-auth__quote p { font-size: .88rem; }
        }

        /* ---------- the card's fields, harmonised -----------------------
           The shared card paints inputs from --c-surface-alt. On the
           panel's surface ground that is a readable well; the shell only
           gives the focus ring the brand colour and keeps the 44px
           touch target the platform promises (AGENTS.md §7.8 LOCK). */
        .jn-auth__card .login-card input[type="text"],
        .jn-auth__card .login-card input[type="email"],
        .jn-auth__card .login-card input[type="password"] {
            min-block-size: 2.75rem;
            padding-inline: .85rem;
            transition: border-color var(--animation-duration, 180ms) ease;
        }
        .jn-auth__card .login-card input[type="text"]:focus,
        .jn-auth__card .login-card input[type="email"]:focus,
        .jn-auth__card .login-card input[type="password"]:focus {
            border-color: var(--c-primary);
        }
        .jn-auth__card .login-card button[type="submit"] {
            min-block-size: 2.85rem;
            font-size: .95rem;
        }
    </style>
</head>
<body class="jn-auth">
    <div class="jn-auth__panel">

        {{-- Column A — the task. Everything in here belongs to the card. --}}
        <div class="jn-auth__form">
            <div class="jn-auth__stack">

                <span class="jn-auth__brand">
                    <span class="jn-auth__mark" aria-hidden="true">{{ $monogram }}</span>
                    {{ $tenant['name'] }}
                </span>

                <div class="jn-auth__card">
                    {{--
                        The credential card, unchanged. It carries both guards'
                        forms, @csrf, the remember box, the error block and the
                        signup/back links — and it computes its own
                        $c/$s/$tabs/$active fallbacks, so nothing about auth
                        logic is duplicated above.
                    --}}
                    @include('auth.partials.login-card', [
                        'c' => $c,
                        's' => $s,
                        'tabs' => $tabs,
                        'active' => $active,
                    ])
                </div>
            </div>
        </div>

        {{-- Column B — the brand. The tenant's own voice, nothing invented. --}}
        <aside class="jn-auth__aside">
            <div class="jn-auth__head">
                <p class="jn-auth__kicker">{{ $adv['eyebrow']['label'] ?? $tenant['short_name'] }}</p>
                <h1 class="jn-auth__title">{{ $adv['tagline'] ?? $c['title'] }}</h1>
                @if (!empty($adv['bio']))
                    <p class="jn-auth__lede">{{ $adv['bio'] }}</p>
                @endif
            </div>

            <blockquote class="jn-auth__quote">
                <p>{{ $isStaff ? $c['subtitle'] : $s['subtitle'] }}</p>
                <div class="jn-auth__who">
                    <span class="jn-auth__avatar" aria-hidden="true">
                        @if ($hasPortrait)
                            <img src="{{ tenant_asset($adv['image']) }}"
                                 alt="" loading="lazy" width="46" height="46">
                        @else
                            {{ $monogram }}
                        @endif
                    </span>
                    <span>
                        <b>{{ $isStaff ? ($adv['name'] ?? $tenant['name']) : $who }}</b>
                        <span>{{ $isStaff ? ($adv['eyebrow']['label'] ?? '') : ($adv['name'] ?? '') }}</span>
                    </span>
                </div>
            </blockquote>

            @if ($stats->isNotEmpty())
                <ul class="jn-auth__stats">
                    @foreach ($stats as $stat)
                        <li class="jn-auth__stat">
                            <b>{{ $stat['value'] ?? '' }}</b>
                            <span>{{ $stat['label'] ?? '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>

    {{--
        The tab switcher. The shared view keeps this inline with the CSP
        nonce; the same approach is used here so the shell does not depend on
        a built asset, and select() runs once on load to honour a re-opened
        or deep-linked tab. The card's chrome is dissolved above, but the
        controls' own paint is left to the card's inline styles so the two
        never fight.
    --}}
    <script nonce="{{ csp_nonce() }}">
        (function () {
            const tabs  = document.querySelectorAll('.login-tab');
            const panes = document.querySelectorAll('[data-pane]');
            function select(role) {
                panes.forEach(p => p.classList.toggle('hidden', p.dataset.pane !== role));
                tabs.forEach(t => {
                    const on = t.dataset.role === role;
                    t.style.background = on ? 'var(--c-glass-hover)' : 'transparent';
                    t.style.color      = on ? 'var(--c-text)' : 'var(--c-muted)';
                });
            }
            tabs.forEach(t => t.addEventListener('click', () => select(t.dataset.role)));
            select('{{ $active }}');
        })();
    </script>
</body>
</html>
