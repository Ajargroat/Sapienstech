{{--
    resources/views/partials/color-scheme.blade.php

    Shared colour-scheme toggle for the consultant, student, teacher and public
    shells. $schemeKey is the localStorage key, unique per portal so the two
    areas can be themed independently in the same browser.

    The palette lives in `theme.schemes` and is emitted as a real CSS block by
    partials/theme-vars, so this script only flips an attribute. The scheme list
    and the starting scheme both come from the resolved config
    (ThemeTokens::schemes), so a tenant that declares only a dark scheme gets a
    toggle that does nothing rather than one that lands on a missing palette —
    and a paper-bodied tenant that declares `dark` gets a working switch without
    a line of code here.
--}}
@php
    $t = site('theme');

    // Every destination the visitor may occupy, in config order. A tenant with
    // a single scheme name renders no toggle at all (see $toggle below).
    $names = array_values((array) ($t['scheme_names'] ?? ['dark']));

    // Where a first-time visitor starts; the tenant owns this, not the script.
    $default = (string) ($t['default_scheme'] ?? ($names[0] ?? 'dark'));

    // The labels/icons are resolved from the scheme *name*, because a tenant
    // may name its own schemes (e.g. 'sepia'). Unknown names fall back to the
    // first letter of the name, so the control is never blank.
    $labels = [
        'light' => ['label' => 'حالت روشن', 'icon' => 'fa-sun'],
        'dark'  => ['label' => 'حالت تاریک', 'icon' => 'fa-moon'],
    ];

    $cycle = [];
    foreach ($names as $name) {
        $cycle[] = [
            'name'  => $name,
            'label' => $labels[$name]['label'] ?? $name,
            'icon'  => $labels[$name]['icon'] ?? 'fa-palette',
        ];
    }

    // A toggle needs somewhere to go. One scheme = nothing to switch.
    $toggle = count($cycle) > 1;
@endphp

<script>
    (function () {
        var STORAGE_KEY = @json($schemeKey);
        var DEFAULT = @json($default);
        var NAMES = @json(array_column($cycle, 'name'));
        var body = document.body;

        function known(scheme) {
            return NAMES.indexOf(scheme) !== -1;
        }

        function apply(scheme) {
            body.setAttribute('data-color-scheme', scheme);
        }

        // A stored scheme that the tenant has since removed must not win: fall
        // back to the tenant's declared default instead of an unknown name.
        var saved = localStorage.getItem(STORAGE_KEY);
        apply(known(saved) ? saved : DEFAULT);

        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('theme-toggle-btn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var current = NAMES.indexOf(body.getAttribute('data-color-scheme'));
                var next = NAMES[(current + 1) % NAMES.length];

                // The per-scheme icon pair is only correct for a two-scheme
                // theme; with three or more, the button keeps its own icon.
                apply(next);
                localStorage.setItem(STORAGE_KEY, next);
                btn.setAttribute('aria-label', 'تغییر حالت نمایش');
            });
        });
    })();
</script>
