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
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $c['title'] }} — {{ $tenant['name'] }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="{{ $theme['assets']['font_url'] }}">

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

            <label for="email-c" class="block mb-1 text-sm font-medium">{{ $c['email_label'] }}</label>
            <input id="email-c" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" dir="ltr"
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

            <label for="email-s" class="block mb-1 text-sm font-medium">{{ $s['email_label'] }}</label>
            <input id="email-s" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" dir="ltr"
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
