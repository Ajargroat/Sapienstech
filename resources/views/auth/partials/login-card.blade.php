@php
    // The card is self-sufficient on purpose: it is included by the shared
    // login page AND by a bespoke login shell, neither of which is the other.
    // Every fallback below equals what the shared view computes, so the
    // rendered bytes of the shared card are exactly what they were when this
    // block was extracted from auth/login.blade.php — the byte probe in
    // resources landings shell tests asserts the shared render never moved.
    $c    = $c ?? $public['login'];
    $s    = $s ?? $public['student_login'];
    $tabs = $tabs ?? ['consultant' => 'مشاور', 'student' => 'دانش‌آموز'];
    $active = $active ?? (old('role', request('tab')) === 'student' ? 'student' : 'consultant');
    $tabStyle = fn ($on) => $on
        ? 'background:var(--c-glass-hover);color:var(--c-text)'
        : 'color:var(--c-muted)';

    // The card is styled by its OWN sheet, below, not by the consumer's.
    // It is legitimately included by three frames — the shared page, which
    // loads app.css, and two bespoke shells, which load only the tenant
    // token sheet. When the card relied on Tailwind utilities, the shells
    // rendered it unstyled: native <button> chrome (2px outset, 13.3px),
    // no field labels, both role panes visible at once. A shell owns its
    // frame; a component owns its body — so the body's rules travel with it,
    // scoped to `.login-card` and reading only tenant tokens.

    // The way out for a visitor who has no account yet. It is offered only
    // when this tenant's home page really carries the membership form: the
    // `join` section has to be in the landing's section list *and* the
    // feature has to be on, because the public POST route 404s without it.
    $signup = (array) ($c['signup_link'] ?? []);
    $landingSections = (array) site('public.landing.sections', []);
    $signupHref = null;

    if (trim((string) ($signup['label'] ?? '')) !== ''
        && ($signup['visible'] ?? false)
        && in_array('join', $landingSections, true)
        && (bool) site('features.student_join_requests', false)) {
        // `fragment` names a section *key*; the anchor is that section's
        // configured id, which a tenant is free to have renamed.
        $fragment = trim((string) ($signup['fragment'] ?? ''));
        $anchor = $fragment !== '' && in_array($fragment, $landingSections, true)
            ? (string) site("public.landing.{$fragment}.id", $fragment)
            : null;

        $signupHref = route('home').($anchor !== null && $anchor !== '' ? '#'.$anchor : '');
    }
@endphp
<style>
    /* ---------- the credential card's own body -------------------------
       Everything here is `.login-card`-scoped and token-driven, so the same
       markup renders identically inside any frame. The shells keep their
       de-chrome overrides (!important in login-shell.blade.php) and their
       own touch-target sizing on top; nothing below assumes a frame. */
    .login-card {
        --lc-field-h: 2.75rem;   /* 44px — the platform's touch target */
        --lc-gap: 1rem;
        position: relative;
        z-index: 10;
        box-sizing: border-box;
        inline-size: 100%;
        padding: 2rem;
        border-radius: var(--radius-card);
    }

    .login-card *,
    .login-card *::before,
    .login-card *::after {
        box-sizing: border-box;
    }

    /* The switcher hides a pane with `.hidden`. That utility lives in
       app.css, which a bespoke shell does not load — so the component
       guarantees it for its OWN panes. Both role forms would otherwise
       render stacked, and the page would show two logins at once. */
    .login-card form[data-pane].hidden {
        display: none !important;
    }

    .login-card h1 {
        margin: 0 0 .35rem;
        font-family: var(--font-heading, var(--font-body, sans-serif));
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.4;
        text-align: center;
    }

    /* ---------- role tabs ---------------------------------------------- */
    .login-card .login-tabs {
        display: flex;
        gap: .25rem;
        padding: .25rem;
        margin-block-end: 1.5rem;
        border-radius: var(--radius-input);
    }

    .login-tab {
        flex: 1 1 0;
        min-width: 0;
        display: block;
        padding-block: .55rem;
        border: 1px solid transparent;
        border-radius: calc(var(--radius-input) - .2rem);
        background: transparent;
        color: var(--c-muted);
        font: inherit;
        font-size: .88rem;
        font-weight: 600;
        line-height: 1.5;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        -webkit-appearance: none;
        appearance: none;
        transition: background-color .18s ease, color .18s ease, border-color .18s ease;
    }

    .login-tab:hover {
        color: var(--c-text);
    }

    .login-tab:focus-visible {
        outline: 2px solid var(--c-primary);
        outline-offset: 2px;
    }

    /* The active tab is set by the page's own nonce'd switcher (inline
       background/color), so the rule below only has to keep an inactive tab
       from looking pressed. */

    /* ---------- errors ------------------------------------------------- */
    .login-error {
        margin-block-end: 1rem;
        padding: .7rem .85rem;
        border-radius: var(--radius-input);
        font-size: .875rem;
        line-height: 1.7;
    }

    /* ---------- a pane's heading --------------------------------------- */
    .login-pane__title {
        margin: 0 0 .3rem;
        font-family: var(--font-heading, var(--font-body, sans-serif));
        font-size: 1.12rem;
        font-weight: 800;
        line-height: 1.5;
        text-align: center;
    }

    .login-pane__subtitle {
        margin: 0 0 1.5rem;
        color: var(--c-muted);
        font-size: .875rem;
        line-height: 1.8;
        text-align: center;
    }

    /* ---------- fields ------------------------------------------------- */
    .login-field__label {
        display: block;
        margin-block-end: .4rem;
        font-size: .875rem;
        font-weight: 600;
        line-height: 1.6;
        color: var(--c-text);
    }

    .login-field__input {
        display: block;
        inline-size: 100%;
        block-size: auto;
        min-block-size: var(--lc-field-h);
        padding: .6rem .85rem;
        margin-block-end: var(--lc-gap);
        font: inherit;
        font-size: .95rem;
        line-height: 1.6;
        border-radius: var(--radius-input);
        transition: border-color .18s ease, box-shadow .18s ease;
    }

    .login-field__input:focus {
        outline: none;
        border-color: var(--c-primary) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--c-primary) 32%, transparent);
    }

    .login-field__input:focus-visible {
        outline: none;
    }

    /* ---------- remember ----------------------------------------------- */
    .login-remember {
        display: flex;
        align-items: center;
        gap: .55rem;
        margin-block-end: 1.5rem;
        color: var(--c-muted);
        font-size: .875rem;
        line-height: 1.6;
        cursor: pointer;
        user-select: none;
    }

    .login-remember input[type="checkbox"] {
        -webkit-appearance: none;
        appearance: none;
        flex: 0 0 auto;
        inline-size: 1.1rem;
        block-size: 1.1rem;
        margin: 0;
        display: grid;
        place-items: center;
        border: 1px solid color-mix(in srgb, var(--c-text) 30%, transparent);
        border-radius: .3rem;
        background: var(--c-surface-alt);
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease;
    }

    .login-remember input[type="checkbox"]::after {
        content: "";
        inline-size: .55rem;
        block-size: .32rem;
        border-inline-start: 2px solid var(--c-on-primary);
        border-block-end: 2px solid var(--c-on-primary);
        transform: rotate(-45deg) translate(.05rem, -.1rem);
        opacity: 0;
        transition: opacity .12s ease;
    }

    .login-remember input[type="checkbox"]:checked {
        background: var(--c-primary);
        border-color: var(--c-primary);
    }

    .login-remember input[type="checkbox"]:checked::after {
        opacity: 1;
    }

    .login-remember input[type="checkbox"]:focus-visible {
        outline: 2px solid var(--c-primary);
        outline-offset: 2px;
    }

    /* ---------- submit ------------------------------------------------- */
    .login-submit {
        display: block;
        inline-size: 100%;
        min-block-size: var(--lc-field-h);
        padding-block: .65rem;
        border: 0;
        border-radius: var(--radius-button);
        font: inherit;
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.5;
        cursor: pointer;
        -webkit-appearance: none;
        appearance: none;
        transition: filter .18s ease, transform .18s ease;
    }

    .login-submit:hover {
        filter: brightness(1.07);
    }

    .login-submit:active {
        transform: translateY(1px);
    }

    .login-submit:focus-visible {
        outline: 2px solid var(--c-text);
        outline-offset: 2px;
    }

    /* ---------- the way out of the card -------------------------------- */
    .login-foot__link {
        display: block;
        text-align: center;
        font-size: .875rem;
        line-height: 1.8;
        text-decoration: none;
    }

    .login-foot__link--muted {
        margin-block-start: 1.5rem;
        color: var(--c-muted);
    }

    .login-foot__link--accent {
        margin-block-start: .75rem;
        color: var(--c-primary);
        font-weight: 600;
    }

    .login-foot__link:hover {
        text-decoration: underline;
        text-underline-offset: 3px;
    }
</style>
<div class="login-card w-full p-8 relative z-10 rounded-[var(--radius-card)]"
         style="max-width:{{ $c['card']['max_width'] }};
                background:color-mix(in srgb, var(--c-surface) calc({{ $theme['effects']['glass_opacity'] }} * 100%), transparent);
                border:1px solid var(--c-border);
                box-shadow:var(--card-shadow);
                {{ $c['card']['glass'] ? 'backdrop-filter:blur(var(--backdrop-blur));' : '' }}">

        <h1 class="text-2xl font-bold text-center mb-1">{{ $tenant['name'] }}</h1>

        {{-- Role tabs --}}
        <div class="login-tabs flex p-1 rounded-[var(--radius-input)] mb-6"
             style="background:var(--c-surface-alt);border:1px solid var(--c-border)">
            <button type="button" data-role="consultant"
                    class="login-tab login-tab__btn"
                    style="{{ $tabStyle($active === 'consultant') }}">{{ $tabs['consultant'] }}</button>
            <button type="button" data-role="student"
                    class="login-tab login-tab__btn"
                    style="{{ $tabStyle($active === 'student') }}">{{ $tabs['student'] }}</button>
        </div>

        @if ($errors->any())
            <div class="login-error text-sm rounded-[var(--radius-input)] p-3 mb-4"
                 style="background:color-mix(in srgb, var(--c-danger) 10%, transparent);border:1px solid var(--c-danger);color:var(--c-danger)">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Consultant --}}
        <form method="POST" action="{{ route('login') }}" data-pane="consultant"
              class="{{ $active === 'consultant' ? '' : 'hidden' }}">
            @csrf
            <input type="hidden" name="role" value="consultant">

            <h2 class="login-pane__title">{{ $c['title'] }}</h2>
            <p class="login-pane__subtitle" style="color:var(--c-muted)">{{ $c['subtitle'] }}</p>

            <label for="identifier-c" class="login-field__label">{{ $c['identifier_label'] }}</label>
            <input id="identifier-c" type="text" name="identifier" value="{{ old('identifier') }}" required autofocus autocomplete="username" dir="ltr"
                   class="login-field__input"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label for="password-c" class="login-field__label">{{ $c['password_label'] }}</label>
            <input id="password-c" type="password" name="password" required autocomplete="current-password" dir="ltr"
                   class="login-field__input"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label class="login-remember" style="color:var(--c-muted)">
                <input type="checkbox" name="remember" value="1" class="rounded"> {{ $c['remember_label'] }}
            </label>

            <button type="submit" class="login-submit"
                    style="background:var(--c-primary);color:var(--c-on-primary)">{{ $c['submit_label'] }}</button>
        </form>

        {{-- Student --}}
        <form method="POST" action="{{ route('student.login') }}" data-pane="student"
              class="{{ $active === 'student' ? '' : 'hidden' }}">
            @csrf
            <input type="hidden" name="role" value="student">

            <h2 class="login-pane__title">{{ $s['title'] }}</h2>
            <p class="login-pane__subtitle" style="color:var(--c-muted)">{{ $s['subtitle'] }}</p>

            <label for="identifier-s" class="login-field__label">{{ $s['identifier_label'] }}</label>
            <input id="identifier-s" type="text" name="identifier" value="{{ old('identifier') }}" required autocomplete="username" dir="ltr"
                   class="login-field__input"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label for="password-s" class="login-field__label">{{ $s['password_label'] }}</label>
            <input id="password-s" type="password" name="password" required autocomplete="current-password" dir="ltr"
                   class="login-field__input"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label class="login-remember" style="color:var(--c-muted)">
                <input type="checkbox" name="remember" value="1" class="rounded"> {{ $s['remember_label'] }}
            </label>

            <button type="submit" class="login-submit"
                    style="background:var(--c-primary);color:var(--c-on-primary)">{{ $s['submit_label'] }}</button>
        </form>

        @if ($c['back_link']['visible'] ?? true)
            <a href="{{ route($c['back_link']['route']) }}" class="login-foot__link login-foot__link--muted"
               style="color:var(--c-muted)">→ {{ $c['back_link']['label'] }}</a>
        @endif

        {{-- A visitor without an account is the normal case on this page, so
             it must not be a dead end. Rendered only when the membership
             form actually exists on the home page (see $signupHref above). --}}
        @if ($signupHref !== null)
            <a href="{{ $signupHref }}" class="login-foot__link login-foot__link--accent"
               style="color:var(--c-primary)">{{ $signup['label'] }}</a>
        @endif
    </div>
