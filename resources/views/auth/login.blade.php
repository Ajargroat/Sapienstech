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

    @include('auth.partials.login-card')

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
