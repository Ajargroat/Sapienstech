{{-- resources/views/partials/color-scheme.blade.php --}}
{{-- Shared light/dark override for the consultant and student shells.
     $schemeKey: the localStorage key, unique per portal so the two areas
     can be themed independently in the same browser. --}}
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

        var STORAGE_KEY = @json($schemeKey);

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

        var saved = localStorage.getItem(STORAGE_KEY);
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
                localStorage.setItem(STORAGE_KEY, next);
            });
        });
    })();
</script>
