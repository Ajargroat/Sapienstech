{{-- resources/views/auth/login.blade.php --}}
{{-- Single login page, consultant/student role tabs. Both forms post to their
     own guard endpoint; switching is client-side, so the user never leaves the
     page. Theme tokens are shared with the landing page via partials.theme-vars. --}}
@php
    $c    = $public['login'];
    $s    = $public['student_login'];
    $tabs = $c['tabs'] ?? ['consultant' => 'مشاور', 'student' => 'دانش‌آموز'];

    // Failed login re-opens the submitted tab; ?tab=student deep-links it;
    // otherwise default to consultant.
    $active = old('role', request('tab')) === 'student' ? 'student' : 'consultant';

    $tabStyle = fn ($on) => $on
        ? 'background:var(--c-glass-hover);color:var(--c-text)'
        : 'color:var(--c-muted)';

    // The way out for a visitor who has no account yet. It is offered only
    // when this tenant's home page really carries the membership form: the
    // `join` section has to be in the landing's section list *and* the
    // feature has to be on, because the public POST route 404s without it.
    // A tenant that has not opted into the flow renders no link at all, so
    // its login page is byte-for-byte what it was before.
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
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $c['title'] }} — {{ $tenant['name'] }}</title>


    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
</head>
<body class="landing flex items-center justify-center min-h-screen p-4">
    <div class="page-glow page-glow-primary"></div>
    <div class="page-glow page-glow-secondary"></div>

    <div class="w-full p-8 relative z-10 rounded-[var(--radius-card)]"
         style="max-width:{{ $c['card']['max_width'] }};
                background:color-mix(in srgb, var(--c-surface) calc({{ $theme['effects']['glass_opacity'] }} * 100%), transparent);
                border:1px solid var(--c-border);
                box-shadow:var(--card-shadow);
                {{ $c['card']['glass'] ? 'backdrop-filter:blur(var(--backdrop-blur));' : '' }}">

        <h1 class="text-2xl font-bold text-center mb-1">{{ $tenant['name'] }}</h1>

        {{-- Role tabs --}}
        <div class="flex p-1 rounded-[var(--radius-input)] mb-6"
             style="background:var(--c-surface-alt);border:1px solid var(--c-border)">
            <button type="button" data-role="consultant"
                    class="login-tab flex-1 py-2 text-sm font-medium rounded-[var(--radius-input)] transition-colors"
                    style="{{ $tabStyle($active === 'consultant') }}">{{ $tabs['consultant'] }}</button>
            <button type="button" data-role="student"
                    class="login-tab flex-1 py-2 text-sm font-medium rounded-[var(--radius-input)] transition-colors"
                    style="{{ $tabStyle($active === 'student') }}">{{ $tabs['student'] }}</button>
        </div>

        @if ($errors->any())
            <div class="text-sm rounded-[var(--radius-input)] p-3 mb-4"
                 style="background:color-mix(in srgb, var(--c-danger) 10%, transparent);border:1px solid var(--c-danger);color:var(--c-danger)">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Consultant --}}
        <form method="POST" action="{{ route('login') }}" data-pane="consultant"
              class="{{ $active === 'consultant' ? '' : 'hidden' }}">
            @csrf
            <input type="hidden" name="role" value="consultant">

            <h2 class="text-lg font-bold text-center mb-1">{{ $c['title'] }}</h2>
            <p class="text-center mb-6 text-sm" style="color:var(--c-muted)">{{ $c['subtitle'] }}</p>

            <label for="identifier-c" class="block mb-1 text-sm font-medium">{{ $c['identifier_label'] }}</label>
            <input id="identifier-c" type="text" name="identifier" value="{{ old('identifier') }}" required autofocus autocomplete="username" dir="ltr"
                   class="w-full px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-(--c-primary)"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label for="password-c" class="block mb-1 text-sm font-medium">{{ $c['password_label'] }}</label>
            <input id="password-c" type="password" name="password" required autocomplete="current-password" dir="ltr"
                   class="w-full px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-(--c-primary)"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label class="flex items-center gap-2 mb-6 text-sm" style="color:var(--c-muted)">
                <input type="checkbox" name="remember" value="1" class="rounded"> {{ $c['remember_label'] }}
            </label>

            <button type="submit" class="w-full font-semibold rounded-[var(--radius-button)] py-2.5 transition-colors hover:opacity-90"
                    style="background:var(--c-primary);color:var(--c-on-primary)">{{ $c['submit_label'] }}</button>
        </form>

        {{-- Student --}}
        <form method="POST" action="{{ route('student.login') }}" data-pane="student"
              class="{{ $active === 'student' ? '' : 'hidden' }}">
            @csrf
            <input type="hidden" name="role" value="student">

            <h2 class="text-lg font-bold text-center mb-1">{{ $s['title'] }}</h2>
            <p class="text-center mb-6 text-sm" style="color:var(--c-muted)">{{ $s['subtitle'] }}</p>

            <label for="identifier-s" class="block mb-1 text-sm font-medium">{{ $s['identifier_label'] }}</label>
            <input id="identifier-s" type="text" name="identifier" value="{{ old('identifier') }}" required autocomplete="username" dir="ltr"
                   class="w-full px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-(--c-primary)"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label for="password-s" class="block mb-1 text-sm font-medium">{{ $s['password_label'] }}</label>
            <input id="password-s" type="password" name="password" required autocomplete="current-password" dir="ltr"
                   class="w-full px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-(--c-primary)"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label class="flex items-center gap-2 mb-6 text-sm" style="color:var(--c-muted)">
                <input type="checkbox" name="remember" value="1" class="rounded"> {{ $s['remember_label'] }}
            </label>

            <button type="submit" class="w-full font-semibold rounded-[var(--radius-button)] py-2.5 transition-colors hover:opacity-90"
                    style="background:var(--c-primary);color:var(--c-on-primary)">{{ $s['submit_label'] }}</button>
        </form>

        @if ($c['back_link']['visible'] ?? true)
            <a href="{{ route($c['back_link']['route']) }}" class="block text-center text-sm mt-6 hover:underline"
               style="color:var(--c-muted)">→ {{ $c['back_link']['label'] }}</a>
        @endif

        {{-- A visitor without an account is the normal case on this page, so
             it must not be a dead end. Rendered only when the membership
             form actually exists on the home page (see $signupHref above). --}}
        @if ($signupHref !== null)
            <a href="{{ $signupHref }}" class="block text-center text-sm mt-3 hover:underline"
               style="color:var(--c-primary)">{{ $signup['label'] }}</a>
        @endif
    </div>

    <script>
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
