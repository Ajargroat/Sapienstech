{{--
    resources/views/partials/color-scheme.blade.php

    Shared light/dark toggle for the consultant and student shells.
    $schemeKey: the localStorage key, unique per portal so the two areas can be
    themed independently in the same browser.

    This used to carry its own hardcoded light palette in JavaScript, which made
    it a third source of truth for colour (after theme.colors and the old
    theme.presets) and meant every tenant got the *same* light mode. The
    palette now lives in `theme.schemes` and is emitted as a real CSS block by
    partials/theme-vars, so this script only flips an attribute.
--}}
<script>
    (function () {
        var STORAGE_KEY = @json($schemeKey);
        var body = document.body;

        function apply(scheme) {
            body.setAttribute('data-color-scheme', scheme);
        }

        apply(localStorage.getItem(STORAGE_KEY) === 'light' ? 'light' : 'dark');

        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('theme-toggle-btn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var next = body.getAttribute('data-color-scheme') === 'light' ? 'dark' : 'light';
                apply(next);
                localStorage.setItem(STORAGE_KEY, next);
            });
        });
    })();
</script>
