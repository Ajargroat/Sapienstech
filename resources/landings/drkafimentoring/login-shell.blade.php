{{--
    Bespoke login shell — drkafimentoring.

    Presentation only. The credential card is the SHARED
    auth.partials.login-card partial, included unchanged, so this page carries
    the whole security contract (both guards, @csrf, throttle:login, the
    failed-login tab re-open and the whereIdentifier lookup) without
    re-implementing any of it.

    What this file owns is the frame: a two-column panel on the tenant's
    dark-luminous ground, the card de-chromed so it reads as paper rather than
    a glass box inside a box, and the advisor's own voice (name, tagline, bio,
    stats, portrait) on the brand side. Every colour is a token or a
    color-mix of tokens; no inline script (the CSP allows none) — the tab
    switcher below is the same nonce'd logic the shared card expects.
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

    // The advisor's portrait, resolved the way the landing resolves it, and
    // only rendered when the file is really there.
    $advisorImage = isset($adv['image'])
        ? 'tenants/'.($tenant['slug'] ?? '').'/'.ltrim((string) $adv['image'], '/')
        : null;
    $hasPortrait = $advisorImage !== null && file_exists(public_path($advisorImage));

    $monogram = mb_substr((string) ($adv['name'] ?? $tenant['name']), 0, 1);
    $isStaff = $active === 'consultant';
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}"
      data-auth-surface="{{ $tenant['slug'] ?? ($theme['slug'] ?? 'drkafimentoring') }}" @include('partials.theme-attrs')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $c['title'] }} — {{ $tenant['name'] }}</title>

    @include('partials.theme-vars')

    <style>
        .ka-auth {
            /* border-box, so `100svh` is the viewport and the padding above
               costs no extra scroll: content-box made the ground
               100svh + 2 * padding tall and pushed a 96px scrollbar under a
               page that already fit. */
            box-sizing: border-box;
            margin: 0;
            min-block-size: 100svh;
            display: grid;
            place-items: center;
            padding: clamp(1rem, 3vw, 2.5rem);
            background:
                radial-gradient(70rem 46rem at 12% -12%,
                    color-mix(in srgb, var(--c-primary) 16%, transparent), transparent 62%),
                var(--c-background);
            color: var(--c-text);
            font-family: var(--font-body, var(--font-heading));
        }

        .ka-auth *,
        .ka-auth *::before,
        .ka-auth *::after {
            box-sizing: border-box;
        }

        .ka-auth__panel {
            inline-size: 100%;
            max-inline-size: 68rem;
            min-block-size: min(34rem, calc(100svh - clamp(2rem, 6vw, 5rem)));
            display: grid;
            overflow: hidden;
            border: 1px solid var(--c-border);
            border-radius: var(--radius-card);
            background: var(--c-surface);
            box-shadow: var(--card-shadow);
        }

        /* The card keeps its own 44px fields; its focus ring is drawn inside
           its box so the panel's overflow: hidden cannot clip it. */
        .ka-auth__card .login-field__input:focus {
            box-shadow: inset 0 0 0 2px var(--c-primary);
        }

        @media (min-width: 60rem) {
            .ka-auth__panel {
                grid-template-columns: minmax(0, 1.06fr) minmax(0, .94fr);
                align-items: stretch;
            }
        }

        /* ---------- column A: the card ---------------------------------- */
        .ka-auth__form {
            display: flex;
            align-items: center;
            padding: clamp(1.75rem, 4vw, 3.25rem);
        }

        .ka-auth__stack {
            inline-size: 100%;
            max-inline-size: 26rem;
            margin-inline: auto;
            display: grid;
            gap: 1.5rem;
        }

        /* The lockup is one signature, not a header row: mark and wordmark
           sit together at the block start, never spread across 26rem. */
        .ka-auth__lockup {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-width: 0;
            justify-content: flex-start;
        }

        .ka-auth__lockup > span:last-child {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: .1rem;
        }

        .ka-auth__mark {
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            inline-size: 2.75rem;
            block-size: 2.75rem;
            border-radius: 999px;
            background: color-mix(in srgb, var(--c-primary) 18%, transparent);
            color: var(--c-primary);
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 1.1rem;
        }

        .ka-auth__lockup b {
            display: block;
            font-family: var(--font-heading);
            font-size: .98rem;
            line-height: 1.4;
        }

        .ka-auth__lockup > span:last-child > span {
            display: block;
            color: var(--c-muted);
            font-size: .8rem;
            line-height: 1.5;
        }

        /* The panel is the card. Dissolving the shared card's own chrome is
           the whole point of a shell: otherwise the page shows a box inside
           a box (two borders, two fills, two shadows). */
        .ka-auth__card .login-card {
            max-inline-size: none !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
        }

        /* The lockup already names the brand; the card's h1 would repeat it. */
        .ka-auth__card .login-card > h1 {
            display: none;
        }

        /* ---------- column B: the advisor ------------------------------- */
        .ka-auth__aside {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            padding: clamp(2rem, 4vw, 3.25rem);
            border-block-start: 1px solid color-mix(in srgb, var(--c-border) 80%, transparent);
            background:
                radial-gradient(90% 80% at 80% 0%,
                    color-mix(in srgb, var(--c-primary) 22%, transparent), transparent 64%),
                linear-gradient(180deg,
                    color-mix(in srgb, var(--c-primary) 10%, var(--c-background)),
                    var(--c-background));
        }

        @media (min-width: 60rem) {
            .ka-auth__aside {
                border-block-start: 0;
                border-inline-start: 1px solid color-mix(in srgb, var(--c-border) 80%, transparent);
            }
        }

        .ka-auth__kicker {
            margin: 0;
            color: var(--c-primary);
            font-size: .78rem;
            letter-spacing: .04em;
        }

        .ka-auth__title {
            margin: .6rem 0 0;
            font-family: var(--font-heading);
            font-size: clamp(1.35rem, 2.6vw, 1.9rem);
            line-height: 1.35;
        }

        .ka-auth__lede {
            margin: .85rem 0 0;
            color: var(--c-muted);
            font-size: .9rem;
            line-height: 1.9;
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ka-auth__stats {
            display: grid;
            gap: .85rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .ka-auth__stat {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding-block-end: .8rem;
            border-block-end: 1px solid color-mix(in srgb, var(--c-text) 12%, transparent);
        }

        .ka-auth__stat b {
            font-family: var(--font-heading);
            font-size: 1rem;
        }

        .ka-auth__stat span {
            color: var(--c-muted);
            font-size: .8rem;
        }

        .ka-auth__quote {
            margin: 0;
            margin-block-start: auto;
            padding: 1rem 1.1rem;
            border: 1px solid color-mix(in srgb, var(--c-text) 12%, transparent);
            border-radius: var(--radius-card);
            background: color-mix(in srgb, var(--c-background) 45%, transparent);
        }

        .ka-auth__quote p {
            margin: 0 0 .9rem;
            color: var(--c-muted);
            font-size: .86rem;
            line-height: 1.85;
        }

        .ka-auth__who {
            display: flex;
            align-items: center;
            gap: .7rem;
            min-width: 0;
        }

        .ka-auth__avatar {
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            inline-size: 2.6rem;
            block-size: 2.6rem;
            overflow: hidden;
            border-radius: 999px;
            background: color-mix(in srgb, var(--c-primary) 20%, transparent);
            color: var(--c-primary);
            font-family: var(--font-heading);
            font-weight: 800;
        }

        .ka-auth__avatar img {
            inline-size: 100%;
            block-size: 100%;
            object-fit: cover;
        }

        .ka-auth__who b {
            display: block;
            font-size: .86rem;
        }

        .ka-auth__who span span {
            display: block;
            color: var(--c-muted);
            font-size: .76rem;
        }
    </style>
</head>
<body class="ka-auth">
    <main class="ka-auth__panel">
        {{-- Column A — the task. Everything in here belongs to the card. --}}
        <div class="ka-auth__form">
            <div class="ka-auth__stack">
                <span class="ka-auth__lockup">
                    <span class="ka-auth__mark" aria-hidden="true">{{ $monogram }}</span>
                    <span>
                        <b>{{ $tenant['name'] }}</b>
                        <span>{{ $adv['tagline'] ?? '' }}</span>
                    </span>
                </span>

                <div class="ka-auth__card">
                    @include('auth.partials.login-card', [
                        'c' => $c,
                        's' => $s,
                        'tabs' => $tabs,
                        'active' => $active,
                    ])
                </div>
            </div>
        </div>

        {{-- Column B — the advisor's own voice, read from config. --}}
        <aside class="ka-auth__aside">
            <div>
                <p class="ka-auth__kicker">{{ $adv['eyebrow']['label'] ?? $tenant['short_name'] }}</p>
                <h1 class="ka-auth__title">{{ $adv['tagline'] ?? $c['title'] }}</h1>
                @if (!empty($adv['bio']))
                    <p class="ka-auth__lede">{{ $adv['bio'] }}</p>
                @endif
            </div>

            @if ($stats->isNotEmpty())
                <ul class="ka-auth__stats">
                    @foreach ($stats as $stat)
                        <li class="ka-auth__stat">
                            <b>{{ $stat['value'] ?? '' }}</b>
                            <span>{{ $stat['label'] ?? '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <blockquote class="ka-auth__quote">
                <p>{{ $isStaff ? $c['subtitle'] : $s['subtitle'] }}</p>
                <div class="ka-auth__who">
                    <span class="ka-auth__avatar" aria-hidden="true">
                        @if ($hasPortrait)
                            <img src="{{ tenant_asset($advisorImage) }}"
                                 alt="" loading="lazy" width="42" height="42">
                        @else
                            {{ $monogram }}
                        @endif
                    </span>
                    <span>
                        <b>{{ $adv['name'] ?? $tenant['name'] }}</b>
                        <span>{{ $adv['eyebrow']['label'] ?? '' }}</span>
                    </span>
                </div>
            </blockquote>
        </aside>
    </main>

    {{-- The card's tab switcher (same nonce'd logic the shared page uses):
         both panes are in the document, and a failed login re-opens the one
         it posted from. --}}
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
