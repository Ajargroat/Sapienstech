<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant['name'] }} | {{ $tenant['page_title'] }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="{{ $theme['assets']['font_url'] }}">
    <link rel="stylesheet" href="{{ $theme['assets']['icon_library_url'] }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
</head>
<body class="consultant-shell" data-theme-preset="{{ $theme['preset'] }}">
    <script>
        (function () {
            // Two states: the tenant's configured theme as-is ("dark", the
            // default coming from the inline style above) and a fixed
            // light override ("light"). Preference is per-browser via
            // localStorage. This runs synchronously before first paint to
            // avoid a flash of the wrong theme.
            var LIGHT_PALETTE = {
                '--c-background': '#F6F7FB',
                '--c-surface': '#FFFFFF',
                '--c-surface-alt': '#F0F2F7',
                '--c-surface-elevated': '#FFFFFF',
                '--c-text': '#111827',
                '--c-muted': '#6B7280',
                '--c-border': 'rgba(0,0,0,.08)',
                '--c-border-strong': 'rgba(0,0,0,.16)',
            };

            var body = document.body;

            // Capture the tenant's original (server-rendered) values so we
            // can restore them exactly when switching back to "dark". The
            // tokens now live in a :root stylesheet (partials/theme-vars),
            // so read them from the computed style instead of inline.
            var darkPalette = {};
            Object.keys(LIGHT_PALETTE).forEach(function (key) {
                darkPalette[key] = getComputedStyle(body).getPropertyValue(key).trim();
            });

            function apply(scheme) {
                var palette = scheme === 'light' ? LIGHT_PALETTE : darkPalette;
                Object.keys(palette).forEach(function (key) {
                    body.style.setProperty(key, palette[key]);
                });
                body.setAttribute('data-color-scheme', scheme);
            }

            var saved = localStorage.getItem('consultant-color-scheme');
            if (saved === 'light') {
                apply('light');
            } else {
                body.setAttribute('data-color-scheme', 'dark');
            }

            document.addEventListener('DOMContentLoaded', function () {
                var btn = document.getElementById('theme-toggle-btn');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    var current = body.getAttribute('data-color-scheme') === 'light' ? 'light' : 'dark';
                    var next = current === 'light' ? 'dark' : 'light';
                    apply(next);
                    localStorage.setItem('consultant-color-scheme', next);
                });
            });
        })();
    </script>

    <div class="page-glow page-glow-primary"></div>
    <div class="page-glow page-glow-secondary"></div>

    <div class="min-h-screen relative z-10">
        @include('components.consultant.topnav')

        <div class="consultant-content">
            <main class="content-container" id="app-content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
