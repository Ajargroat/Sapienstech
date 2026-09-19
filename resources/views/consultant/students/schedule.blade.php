{{--
    Consultant weekly schedule editor for a single student.

    Behavioral/UX reference: consultant_schedule_editor.php +
    consultant_schedule_script.js. Ported to Blade + Laravel routes; the
    calendar/drag/modal logic itself lives in
    resources/js/features/consultant-schedule.js and is fed the URLs/CSRF
    token below via data-* attributes rather than hard-coded paths.

    NOTE: assumes `layouts.consultant` defines a `content` section and
    already loads the Vazirmatn font, Tailwind build, and RTL <html dir>
    the same way the other consultant pages do (dashboard, profile, etc).
--}}
@extends('layouts.consultant')

@section('title', 'برنامه هفتگی — ' . $student->name)

@section('content')
<div
    id="schedule-app"
    dir="rtl"
    class="flex flex-col h-full"
    data-student-id="{{ $student->id }}"
    data-student-name="{{ $student->name }}"
    data-csrf="{{ csrf_token() }}"
    data-url-dashboard="{{ route('consultant.dashboard') }}"
    data-url-items="{{ route('consultant.student.schedule.items.index', $student) }}"
    data-url-store="{{ route('consultant.student.schedule.items.store', $student) }}"
    data-url-update-template="{{ route('consultant.student.schedule.items.update', [$student, '__ITEM__']) }}"
    data-url-destroy-template="{{ route('consultant.student.schedule.items.destroy', [$student, '__ITEM__']) }}"
    data-url-comments-template="{{ route('consultant.student.schedule.items.comments', [$student, '__ITEM__']) }}"
    data-url-drafts="{{ route('consultant.student.schedule.drafts.index', $student) }}"
    data-url-draft-update-template="{{ route('consultant.student.schedule.drafts.update', [$student, '__DRAFT__']) }}"
    data-url-draft-apply-template="{{ route('consultant.student.schedule.drafts.apply', [$student, '__DRAFT__']) }}"
>
    <style>
        /* ------------------------------------------------------------------
           Tenant-driven chrome for the weekly planner and its draft tray.

           Every colour, radius, border width, elevation, timing and focus
           treatment below reads from the variables App\Support\ThemeTokens
           publishes through partials/theme-vars, so this screen follows
           whatever the active tenant configured (archetype -> tenant file ->
           database) instead of carrying a palette of its own. Fallbacks only
           cover the no-tenant console render.

           The draft tray, its rows and its states are one themeable system, so
           the schedule script can build matching markup without inlining any
           colour or size of its own.

           Icons are Font Awesome class names on purpose: partials/theme-icons
           loads the tenant's chosen set from public/icons/{set}.css, which
           masks those same fa-* names. One markup therefore renders in Lucide,
           Tabler, Bootstrap Icons or Line Awesome depending on the tenant --
           the unpkg <script> this view used to carry hardcoded Lucide's SVG
           naming and ignored that choice, so it is gone.
           ------------------------------------------------------------------ */
        #schedule-app {
            --draft-accent:           var(--c-primary, #06B6D4);
            --draft-accent-hover:     var(--c-primary-hover, var(--c-primary, #06B6D4));
            --draft-accent-soft:      var(--c-primary-soft, color-mix(in srgb, var(--c-primary, #06B6D4) 14%, transparent));
            --draft-on-accent:        var(--c-on-primary, #000000);
            --draft-line:             var(--c-border, rgba(255, 255, 255, .12));
            --draft-line-strong:      var(--c-border-strong, var(--c-border, rgba(255, 255, 255, .24)));
            --draft-surface:          var(--c-surface, #111111);
            --draft-surface-alt:      var(--c-surface-alt, #1A1A1A);
            --draft-radius:           var(--radius-card, var(--radius-lg, 20px));
            --draft-radius-sm:        var(--radius-sm, 10px);
            --draft-radius-button:    var(--radius-button, var(--radius-md, 14px));
            --draft-border-w:         var(--surface-border-w, 1px);
            --draft-shadow:           var(--card-shadow, var(--shadow, none));
            --draft-duration:         var(--animation-duration, 180ms);
            --draft-ease:             var(--ease, ease);
            --draft-lift:             var(--hover-lift, 3px);
            --draft-button-weight:    var(--btn-weight, 600);
            --draft-button-transform: var(--btn-transform, none);
        }
        #schedule-app .calendar-scroll-area { height: calc(100vh - 260px); overflow-y: auto; overflow-x: hidden; position: relative; }
        #schedule-app .grid-bg-pattern {
            background-image:
                linear-gradient(to bottom, var(--c-border) 1px, transparent 1px),
                linear-gradient(to bottom, var(--c-surface) 30px, var(--c-surface-alt) 30px, var(--c-surface-alt) 31px, transparent 31px);
            background-size: 100% 60px;
        }
        #schedule-app .day-column { position: relative; height: 1440px; border-left: var(--draft-border-w) solid var(--draft-line); min-width: 140px; }
        #schedule-app .day-column:last-child { border-left: none; }
        #schedule-app .drag-ghost {
            position: absolute; left: 4px; right: 4px;
            background-color: color-mix(in srgb, var(--draft-accent) 20%, transparent);
            border: 2px dashed var(--draft-accent); border-radius: var(--draft-radius-sm);
            z-index: 40; pointer-events: none; display: flex; align-items: flex-start; padding: 4px;
            font-size: 0.75rem; color: var(--draft-accent); font-weight: 600;
        }
        #schedule-app .event-card {
            position: absolute; left: 4px; right: 4px; border-radius: var(--draft-radius-sm); padding: 6px; font-size: 0.8rem;
            overflow: hidden; border-right-width: 4px; border-right-style: solid;
            box-shadow: 0 1px 3px color-mix(in srgb, var(--c-text) 12%, transparent);
            transition: transform var(--draft-duration) var(--draft-ease), box-shadow var(--draft-duration) var(--draft-ease);
            cursor: pointer; z-index: 10;
        }
        #schedule-app .event-card:hover { z-index: 20; border-color: var(--draft-line-strong); box-shadow: var(--draft-shadow); }
        /* The tenant's card-hover identity (theme.motion.hover -> data-hover on
           <body>) drives the event chip, exactly as landing.css drives .lp-card,
           so a brutalist tenant animates differently from an editorial one. */
        [data-hover="lift"] #schedule-app .event-card:hover { transform: translateY(calc(var(--draft-lift) * -1)); }
        [data-hover="scale"] #schedule-app .event-card:hover { transform: scale(1.02); }
        [data-hover="skew"] #schedule-app .event-card:hover { transform: skewY(-.6deg) skewX(.4deg); }
        [data-hover="shift"] #schedule-app .event-card:hover { transform: translateX(calc(var(--draft-lift) * 1.5)); }
        [data-hover="glow"] #schedule-app .event-card:hover { box-shadow: 0 0 0 1px var(--draft-accent-hover), var(--draft-shadow); }
        [data-hover="none"] #schedule-app .event-card:hover { transform: none; }
        /* Swatches carry only the category colour as data (--swatch, set by
           consultant-schedule.js from its COLOR_THEMES map). Their shape,
           border width and state ring come from the tenant. */
        #schedule-app .color-swatch {
            background: color-mix(in srgb, var(--swatch, var(--draft-accent)) 80%, var(--draft-surface));
            border: var(--draft-border-w) solid color-mix(in srgb, var(--swatch, var(--draft-accent)) 45%, transparent);
            border-radius: var(--draft-radius-sm);
            cursor: pointer;
            transition: transform var(--draft-duration) var(--draft-ease), box-shadow var(--draft-duration) var(--draft-ease);
        }
        #schedule-app .color-swatch:hover { transform: translateY(calc(var(--draft-lift) * -1)); }
        #schedule-app .color-swatch.selected {
            box-shadow: 0 0 0 var(--draft-border-w) var(--draft-surface), 0 0 0 calc(var(--draft-border-w) * 4) var(--swatch, var(--draft-accent));
        }
        #schedule-app .color-swatch:focus-visible { outline: 2px solid var(--draft-accent); outline-offset: 2px; }
        /* ==================================================================
           Draft tray: panel, editor bar, saved-draft rows
           --------------------------------------------------------------
           Everything here is class-driven so consultant-schedule.js can build
           rows and states that match this screen without embedding colours,
           radii, borders or timings of its own. Named .draft-* (not .btn/.card)
           to stay clear of the shell's own primitives.
           ================================================================== */
        #schedule-app .draft-surface {
            background: var(--draft-surface-alt);
            border-bottom: var(--draft-border-w) solid var(--draft-line);
        }
        #schedule-app .draft-chip {
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--draft-accent-soft); color: var(--draft-accent);
            border-radius: var(--draft-radius-sm); flex-shrink: 0;
        }

        /* --- buttons ----------------------------------------------------- */
        #schedule-app .draft-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: .375rem;
            font-family: var(--font-button, inherit);
            font-weight: var(--draft-button-weight);
            text-transform: var(--draft-button-transform);
            border-radius: var(--draft-radius-button);
            border: var(--draft-border-w) solid transparent;
            padding: .5rem .875rem;
            font-size: .8125rem; line-height: 1.4;
            cursor: pointer;
            transition: transform var(--draft-duration) var(--draft-ease),
                        background var(--draft-duration) var(--draft-ease),
                        color var(--draft-duration) var(--draft-ease),
                        border-color var(--draft-duration) var(--draft-ease),
                        box-shadow var(--draft-duration) var(--draft-ease);
        }
        #schedule-app .draft-btn:disabled { opacity: .5; cursor: not-allowed; }
        #schedule-app .draft-btn--primary { background: var(--draft-accent); color: var(--draft-on-accent); border-color: transparent; }
        #schedule-app .draft-btn--primary:hover:not(:disabled) { background: var(--draft-accent-hover); }
        #schedule-app .draft-btn--soft {
            background: var(--draft-accent-soft); color: var(--draft-accent);
            border-color: color-mix(in srgb, var(--draft-accent) 35%, transparent);
        }
        #schedule-app .draft-btn--soft:hover:not(:disabled) { border-color: var(--draft-accent); }
        #schedule-app .draft-btn--outline { background: transparent; color: var(--c-text); border-color: var(--draft-line-strong); }
        #schedule-app .draft-btn--outline:hover:not(:disabled) { background: var(--draft-surface); color: var(--draft-accent); border-color: var(--draft-accent); }
        #schedule-app .draft-btn--quiet { background: transparent; color: var(--c-muted); border-color: transparent; }
        #schedule-app .draft-btn--quiet:hover:not(:disabled) { background: var(--draft-surface); color: var(--c-text); }
        #schedule-app .draft-btn--danger { background: transparent; color: var(--c-danger); border-color: transparent; }
        #schedule-app .draft-btn--danger:hover:not(:disabled) { background: color-mix(in srgb, var(--c-danger) 12%, transparent); }
        #schedule-app .draft-btn--block { width: 100%; }

        /* The tenant's button identity (theme.buttons.variant -> data-btn) is
           mirrored onto this screen's primary action, so a brutalist tenant
           gets offset blocks and a glass tenant keeps its translucency. */
        [data-btn-shadow="on"] #schedule-app .draft-btn--primary { box-shadow: 0 10px 30px color-mix(in oklab, var(--draft-accent) 30%, transparent); }
        [data-btn="soft"] #schedule-app .draft-btn--primary { background: var(--draft-accent-soft); color: var(--draft-accent); }
        [data-btn="ghost"] #schedule-app .draft-btn--primary { background: transparent; color: var(--draft-accent); }
        [data-btn="outline"] #schedule-app .draft-btn--primary { background: transparent; color: var(--draft-accent); border-color: var(--draft-accent); }
        [data-btn="gradient"] #schedule-app .draft-btn--primary { background: var(--brand-gradient, var(--draft-accent)); }
        [data-btn="glass"] #schedule-app .draft-btn--primary {
            background: var(--c-glass, var(--draft-accent)); color: var(--c-text, var(--draft-on-accent));
            border-color: var(--c-glass-border, var(--draft-line));
        }
        [data-btn="glass"] #schedule-app .draft-btn--primary:hover:not(:disabled) { background: var(--c-glass-hover, var(--draft-accent-hover)); }
        [data-btn="underline"] #schedule-app .draft-btn--primary {
            background: none; color: var(--c-text); padding-inline: 0;
            border: 0; border-block-end: 2px solid var(--draft-accent); border-radius: 0;
        }
        [data-btn="brutal"] #schedule-app .draft-btn--primary {
            background: var(--draft-accent); color: var(--draft-on-accent);
            border-radius: 0; box-shadow: 4px 4px 0 var(--c-text);
        }
        [data-btn="brutal"] #schedule-app .draft-btn--primary:hover:not(:disabled) { transform: translate(2px, 2px); box-shadow: 2px 2px 0 var(--c-text); }
        [data-hover="lift"] #schedule-app .draft-btn:hover:not(:disabled) { transform: translateY(calc(var(--draft-lift) * -1)); }
        [data-hover="none"] #schedule-app .draft-btn:hover:not(:disabled) { transform: none; }
        /* --- dirty / busy affordances (shape and colour only) ------------ */
        #schedule-app #draft-editor[data-dirty="true"] { border-inline-start: 3px solid var(--c-warning, var(--draft-accent)); }
        #schedule-app .draft-status-dot {
            width: .5rem; height: .5rem; border-radius: 50%;
            background: currentColor; display: inline-block; flex-shrink: 0;
        }
        #schedule-app #draft-editor[data-dirty="true"] .draft-status-dot { animation: draft-pulse calc(var(--draft-duration) * 6) infinite; }
        #schedule-app .draft-spinner { display: none; }
        #schedule-app.is-busy .draft-spinner { display: inline-block; animation: draft-spin 1s linear infinite; }
        #schedule-app .draft-accent-text { color: var(--draft-accent); }
        #schedule-app .draft-success-text { color: var(--c-success); }
        #schedule-app .draft-warning-text { color: var(--c-warning, var(--c-muted)); }
        #schedule-app .draft-danger-text { color: var(--c-danger); }

        /* --- entrance motion -------------------------------------------- */
        #schedule-app #draft-panel:not(.hidden) { animation: draft-panel-in calc(var(--draft-duration) * 1.6) var(--draft-ease) both; }
        #schedule-app #draft-editor:not(.hidden) { animation: draft-panel-in calc(var(--draft-duration) * 1.6) var(--draft-ease) both; }
        #schedule-app #draft-list > .draft-row {
            animation: draft-row-in calc(var(--draft-duration) * 2) var(--draft-ease) both;
            animation-delay: calc(var(--row-index, 0) * 45ms);
        }
        @keyframes draft-panel-in { from { opacity: 0; transform: translateY(-6px); } }
        @keyframes draft-row-in { from { opacity: 0; transform: translateY(8px); } }
        @keyframes draft-spin { to { transform: rotate(360deg); } }
        @keyframes draft-pulse { 50% { opacity: .25; } }

        /* --- accessibility ----------------------------------------------- */
        /* Focus-ring style, mirroring the landing shell's policy so keyboard
           focus becomes a tenant decision on this screen too. */
        #schedule-app :focus-visible { outline: 2px solid var(--draft-accent); outline-offset: 2px; }
        [data-focus-ring="none"] #schedule-app :focus-visible { outline: none; }
        [data-focus-ring="glow"] #schedule-app :focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px color-mix(in oklab, var(--draft-accent) 35%, transparent);
        }
        [data-focus-ring="underline"] #schedule-app :focus-visible {
            outline: none; outline-offset: 0; border-block-end: 2px solid var(--draft-accent);
        }
        /* High contrast: strengthen text and rules without changing the palette. */
        [data-contrast="high"] #schedule-app {
            --c-muted: var(--c-text);
            --c-subtle: var(--c-text);
            --c-border: var(--c-border-strong);
        }

        /* Reduce-motion policy. `respect` keeps the media query below, matching
           landing.css; `force-off` disables motion regardless of the OS setting
           (and covers tenants whose theme publishes 0ms durations already). */
        @media (prefers-reduced-motion: reduce) {
            [data-reduce-motion="respect"] #schedule-app,
            [data-reduce-motion="force-off"] #schedule-app { --draft-duration: 0ms; }
            #schedule-app #draft-panel:not(.hidden),
            #schedule-app #draft-editor:not(.hidden),
            #schedule-app #draft-list > .draft-row,
            #schedule-app .draft-state--loading i,
            #schedule-app .draft-spinner,
            #schedule-app #draft-editor[data-dirty="true"] .draft-status-dot { animation: none; }
        }
        [data-reduce-motion="force-off"] #schedule-app { --draft-duration: 0ms; }
        [data-reduce-motion="force-off"] #schedule-app #draft-panel:not(.hidden),
        [data-reduce-motion="force-off"] #schedule-app #draft-editor:not(.hidden),
        [data-reduce-motion="force-off"] #schedule-app #draft-list > .draft-row,
        [data-reduce-motion="force-off"] #schedule-app .draft-state--loading i,
        [data-reduce-motion="force-off"] #schedule-app .draft-spinner,
        [data-reduce-motion="force-off"] #schedule-app #draft-editor[data-dirty="true"] .draft-status-dot {
            animation: none;
        }
        /* Tenants who cannot animate at all should not keep the modal/toast
           cross-fades running. */
        [data-reduce-motion="force-off"] #schedule-app #event-modal,
        [data-reduce-motion="force-off"] #schedule-app #event-modal > div,
        [data-reduce-motion="force-off"] #schedule-app #toast { transition: none; }

        /* Category colours are *data* (which tag a block belongs to), but the
           hues themselves come from the tenant's accent ramp when it defines
           one, falling back to the canonical palette the API stores. */
        #schedule-app .color-swatch[data-color="blue"]   { --swatch: var(--c-accent-blue, #3b82f6); }
        #schedule-app .color-swatch[data-color="green"]  { --swatch: var(--c-accent-emerald, #22c55e); }
        #schedule-app .color-swatch[data-color="yellow"] { --swatch: var(--c-accent-amber, #f59e0b); }
        #schedule-app .color-swatch[data-color="red"]    { --swatch: var(--c-accent-red, #ef4444); }
        #schedule-app .color-swatch[data-color="purple"] { --swatch: var(--c-accent-violet, #a855f7); }
        #schedule-app .color-swatch[data-color="pink"]   { --swatch: var(--c-accent-pink, #ec4899); }

        /* --- toolbar / field primitives ---------------------------------- */
        /* Shared chrome for the header cluster, the draft editor bar and the
           form fields, so none of them restate a colour, radius or border. */
        #schedule-app .draft-tool {
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--draft-surface-alt);
            border: var(--draft-border-w) solid var(--draft-line);
            border-radius: var(--draft-radius-sm);
            padding: .25rem;
        }
        #schedule-app .draft-field {
            background: var(--draft-surface);
            color: var(--c-text);
            border: var(--draft-border-w) solid var(--draft-line);
            border-radius: var(--draft-radius-button);
            padding: .5rem .75rem;
            font-size: .8125rem;
            transition: border-color var(--draft-duration) var(--draft-ease);
        }
        #schedule-app .draft-field::placeholder { color: var(--c-subtle, var(--c-muted)); }
        #schedule-app .draft-field:hover { border-color: var(--draft-line-strong); }
        #schedule-app .draft-field:focus { border-color: var(--draft-accent); outline: none; }
        #schedule-app .draft-icon-chip {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.5rem; height: 2.5rem; flex-shrink: 0;
            background: var(--draft-accent-soft); color: var(--draft-accent);
            border-radius: var(--draft-radius-sm);
        }
        #schedule-app .draft-btn--icon { padding: .625rem; }
        #schedule-app .draft-btn--compact { padding: .375rem; }
        #schedule-app .draft-btn--sm { padding: .375rem .625rem; font-size: .75rem; }

        /* --- containers --------------------------------------------------- */
        #schedule-app .draft-card {
            background: var(--draft-surface);
            border: var(--draft-border-w) solid var(--draft-line);
            border-radius: var(--draft-radius);
            box-shadow: var(--draft-shadow);
        }
        #schedule-app .draft-surface-top {
            background: var(--draft-surface-alt);
            border-top: var(--draft-border-w) solid var(--draft-line);
        }
        #schedule-app .draft-inset {
            background: var(--draft-surface-alt);
            border: var(--draft-border-w) solid var(--draft-line);
            border-radius: var(--draft-radius);
        }
        /* Modal chrome: radius and elevation come from the tenant rather than
           Tailwind literals, so a sharp-cornered, shadow-less archetype (e.g.
           editorial_serif) is not quietly re-rounded by this screen. */
        #schedule-app #event-modal > div {
            background: var(--draft-surface);
            border-radius: var(--draft-radius);
            box-shadow: var(--draft-shadow);
        }
        #schedule-app .draft-toast {
            background: var(--c-surface-elevated, var(--draft-surface));
            border: var(--draft-border-w) solid var(--draft-line);
            border-radius: var(--draft-radius);
            box-shadow: var(--draft-shadow);
        }

        #schedule-app .hidden-mobile { display: none; }
        #schedule-app .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        #schedule-app .hide-scrollbar::-webkit-scrollbar { display: none; }
        @media (min-width: 1024px) {
            #schedule-app .hidden-mobile { display: block; }
            #schedule-app .mobile-only { display: none !important; }
        }
    </style>

    {{-- Header cluster. Everything visual comes from the .draft-* primitives in
         the stylesheet above, which in turn resolve the tenant's theme tokens,
         so this markup names roles instead of colours. --}}
    <header class="bg-[var(--c-surface)] border-b border-[var(--c-border)] px-4 sm:px-6 py-3 sm:py-4 flex flex-col lg:flex-row justify-between items-center shrink-0 gap-3 sm:gap-4">
        <div class="flex justify-between w-full lg:w-auto items-center gap-4">
            <div class="flex items-center gap-3">
                <span class="draft-icon-chip">
                    <i class="fas fa-calendar-days" aria-hidden="true"></i>
                </span>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold text-[var(--c-text)] leading-tight">برنامه‌ریز هفتگی</h1>
                    <p class="text-xs sm:text-sm text-[var(--c-muted)]">مدیریت برنامه {{ $student->name }}</p>
                </div>
            </div>
            <div class="lg:hidden flex flex-col text-left text-xs sm:text-sm pl-2">
                <span class="font-bold text-[var(--c-text)]">{{ $student->name }}</span>
                <a href="{{ route('consultant.dashboard') }}" class="draft-accent-text hover:underline">بازگشت &larr;</a>
            </div>
        </div>

        <div class="flex flex-wrap lg:flex-nowrap items-center justify-between lg:justify-end gap-2 sm:gap-3 w-full lg:w-auto">
            <div class="hidden lg:flex flex-col text-left text-sm ml-2 pl-4 border-l border-[var(--c-border)]">
                <span class="font-bold text-[var(--c-text)]">{{ $student->name }}</span>
                <a href="{{ route('consultant.dashboard') }}" class="text-xs draft-accent-text hover:underline">بازگشت به داشبورد &larr;</a>
            </div>

            <div class="draft-tool flex-1 sm:flex-none">
                <button id="next-week-btn" type="button" class="draft-btn draft-btn--quiet draft-btn--compact" title="هفته قبل" aria-label="هفته قبل">
                    <i class="fas fa-chevron-right w-4 h-4 sm:w-5 sm:h-5" aria-hidden="true"></i>
                </button>
                <span id="week-date-display" class="px-2 sm:px-4 text-xs sm:text-sm font-medium whitespace-nowrap">در حال بارگذاری...</span>
                <button id="prev-week-btn" type="button" class="draft-btn draft-btn--quiet draft-btn--compact" title="هفته بعد" aria-label="هفته بعد">
                    <i class="fas fa-chevron-left w-4 h-4 sm:w-5 sm:h-5" aria-hidden="true"></i>
                </button>
            </div>

            <button id="save-draft-button" type="button" disabled class="draft-btn draft-btn--soft">
                <i class="fas fa-save w-4 h-4" aria-hidden="true"></i>
                <span>ذخیره پیش‌نویس</span>
                <i class="fas fa-spinner draft-spinner w-4 h-4" aria-hidden="true"></i>
            </button>
            <button id="add-event-button" type="button" aria-label="پیش‌نویس‌های برنامه هفتگی" title="پیش‌نویس‌های برنامه هفتگی" aria-expanded="false" aria-controls="draft-panel" class="draft-btn draft-btn--primary draft-btn--icon shrink-0">
                <i class="fas fa-folder-open w-5 h-5" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <section id="draft-panel" aria-labelledby="draft-panel-title" class="draft-surface hidden px-4 sm:px-6 py-4">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 id="draft-panel-title" class="flex items-center gap-2 font-bold text-[var(--c-text)]">
                <span class="draft-chip hidden sm:inline-flex w-8 h-8">
                    <i class="fas fa-folder-open w-4 h-4" aria-hidden="true"></i>
                </span>
                پیش‌نویس‌های من
            </h2>
            <button id="new-draft-button" type="button" disabled class="draft-btn draft-btn--primary">
                <i class="fas fa-plus w-4 h-4" aria-hidden="true"></i>
                پیش‌نویس جدید
            </button>
        </div>
        <p class="text-xs text-[var(--c-muted)] mb-3 flex items-center gap-1.5">
            <i class="fas fa-lock w-3.5 h-3.5 shrink-0" aria-hidden="true"></i>
            <span>پیش‌نویس‌ها خصوصی هستند. افزودن به هفته، بلوک‌ها را بدون حذف برنامه فعلی به هفته انتخاب‌شده اضافه می‌کند.</span>
        </p>
        <div id="draft-list" aria-live="polite" class="draft-list max-h-60 overflow-y-auto"></div>
    </section>

    <section id="draft-editor" data-dirty="false" class="draft-surface hidden px-4 sm:px-6 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
        <span class="draft-chip text-xs font-bold px-2.5 py-1.5">
            <i class="fas fa-file-lines w-4 h-4" aria-hidden="true"></i>
            حالت پیش‌نویس
        </span>
        <label for="draft-name" class="text-xs sm:text-sm font-medium text-[var(--c-muted)]">نام پیش‌نویس:</label>
        <input id="draft-name" type="text" maxlength="255" class="draft-field flex-1 min-w-40" placeholder="نام برنامه هفتگی">
        <span class="flex items-center gap-1.5">
            <span class="draft-status-dot hidden" aria-hidden="true"></span>
            <span id="draft-status" role="status" class="text-xs font-medium text-[var(--c-muted)]"></span>
        </span>
        <span id="draft-block-count" class="draft-row-meta hidden sm:inline-flex" aria-live="polite"></span>
        <div class="flex flex-wrap items-center gap-2 ms-auto">
            <button id="copy-draft-button" type="button" class="draft-btn draft-btn--outline">
                <i class="fas fa-clone w-4 h-4" aria-hidden="true"></i>
                ذخیره به‌عنوان پیش‌نویس جدید
            </button>
            <button id="exit-draft-button" type="button" class="draft-btn draft-btn--quiet">
                <i class="fas fa-arrow-right w-4 h-4" aria-hidden="true"></i>
                بازگشت به برنامه دانش‌آموز
            </button>
        </div>
        <p class="w-full text-xs text-[var(--c-muted)] flex items-center gap-1.5">
            <i class="fas fa-circle-info w-3.5 h-3.5 shrink-0" aria-hidden="true"></i>
            <span>حالت پیش‌نویس: تغییر بلوک‌ها فقط در این پیش‌نویس انجام می‌شود و تا افزودن به هفته برای دانش‌آموز نمایش داده نمی‌شود.</span>
        </p>
    </section>

    <main class="flex-1 flex flex-col overflow-hidden px-2 sm:px-4 lg:px-6 py-3 sm:py-4">
        <div id="mobile-day-tabs" class="mobile-only flex overflow-x-auto gap-2 pb-2 mb-2 snap-x hide-scrollbar"></div>

        <div class="flex-1 bg-[var(--c-surface)] border border-[var(--c-border)] rounded-xl sm:rounded-2xl shadow-sm overflow-hidden flex flex-col relative">
            <div id="calendar-header" class="flex border-b border-[var(--c-border)] bg-[var(--c-surface-alt)] shrink-0">
                <div class="w-12 sm:w-16 lg:w-20 shrink-0 border-l border-[var(--c-border)] flex items-center justify-center bg-[var(--c-surface)] z-10 relative">
                    <i class="fas fa-clock w-4 h-4 text-[var(--c-muted)]" aria-hidden="true"></i>
                </div>
                <div id="day-headers-container" class="flex flex-1"></div>
            </div>

            <div class="calendar-scroll-area bg-[var(--c-surface)]" id="calendar-scroll-area">
                <div class="flex relative">
                    <div class="w-12 sm:w-16 lg:w-20 shrink-0 border-l border-[var(--c-border)] relative bg-[var(--c-surface)] z-10">
                        <div id="time-axis" class="relative"></div>
                    </div>
                    <div id="grid-container" class="flex flex-1 grid-bg-pattern relative select-none cursor-crosshair"></div>
                </div>
            </div>
        </div>

        <div class="mt-2 text-center text-[10px] sm:text-xs text-[var(--c-muted)] flex items-center justify-center gap-1 sm:gap-2">
            <i class="fas fa-arrow-pointer w-3 h-3 sm:w-4 sm:h-4" aria-hidden="true"></i>
            برای ایجاد برنامه جدید، روی فضای خالی تقویم کلیک کرده و درگ کنید.
        </div>
    </main>

    <div id="event-modal" class="fixed inset-0 backdrop-blur-sm z-50 hidden flex items-center justify-center p-2 sm:p-4 opacity-0 transition-opacity duration-300" style="background: color-mix(in srgb, var(--c-text) 30%, transparent)">
        <div class="bg-[var(--c-surface)] w-full max-w-lg transform scale-95 transition-transform duration-300 overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh]">
            <div class="draft-surface flex justify-between items-center p-4 sm:p-5">
                <h3 class="text-base sm:text-lg font-bold text-[var(--c-text)]" id="modal-title">افزودن برنامه جدید</h3>
                <button type="button" onclick="ScheduleApp.closeModal()" class="draft-btn draft-btn--quiet draft-btn--compact">
                    <i class="fas fa-xmark w-5 h-5" aria-hidden="true"></i>
                </button>
            </div>

            <div class="p-4 sm:p-5 overflow-y-auto flex-1">
                <form id="event-form" class="space-y-4">
                    <input type="hidden" id="event_id" value="">

                    <div>
                        <label class="block text-sm font-medium text-[var(--c-muted)] mb-1">عنوان برنامه <span class="text-red-500">*</span></label>
                        <input type="text" id="title" required placeholder="مثال: مطالعه ریاضی دهم" class="draft-field w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--c-muted)] mb-2">روز هفته</label>
                        <div class="flex flex-wrap gap-2" id="modal-day-pills"></div>
                        <input type="hidden" id="day_index" value="">
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--c-muted)] mb-1">ساعت شروع</label>
                            <input type="time" id="start_time" required class="draft-field w-full text-left" dir="ltr">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--c-muted)] mb-1">ساعت پایان</label>
                            <input type="time" id="end_time" required class="draft-field w-full text-left" dir="ltr">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--c-muted)] mb-2">رنگ دسته‌بندی</label>
                        <div class="flex flex-wrap gap-2 sm:gap-3" id="color-picker" role="radiogroup">
                            <button type="button" data-color="blue" class="color-swatch w-7 h-7 sm:w-8 sm:h-8" role="radio" aria-checked="true" title="آبی"></button>
                            <button type="button" data-color="green" class="color-swatch w-7 h-7 sm:w-8 sm:h-8" role="radio" aria-checked="false" title="سبز"></button>
                            <button type="button" data-color="yellow" class="color-swatch w-7 h-7 sm:w-8 sm:h-8" role="radio" aria-checked="false" title="زرد"></button>
                            <button type="button" data-color="red" class="color-swatch w-7 h-7 sm:w-8 sm:h-8" role="radio" aria-checked="false" title="قرمز"></button>
                            <button type="button" data-color="purple" class="color-swatch w-7 h-7 sm:w-8 sm:h-8" role="radio" aria-checked="false" title="بنفش"></button>
                            <button type="button" data-color="pink" class="color-swatch w-7 h-7 sm:w-8 sm:h-8" role="radio" aria-checked="false" title="صورتی"></button>
                        </div>
                        <input type="hidden" id="color_theme" value="blue">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 pt-2 border-t border-[var(--c-border)]">
                        <div class="sm:col-span-1">
                            <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">نام کتاب (اختیاری)</label>
                            <input type="text" id="book_name" class="draft-field w-full">
                        </div>
                        <div class="grid grid-cols-2 sm:col-span-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">تعداد صفحه</label>
                                <input type="number" id="page_count" min="0" class="draft-field w-full text-left" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">تعداد تست</label>
                                <input type="number" id="test_count" min="0" class="draft-field w-full text-left" dir="ltr">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">لینک آزمون/محتوا (اختیاری)</label>
                        <input type="url" id="link_url" placeholder="https://..." class="draft-field w-full text-left" dir="ltr">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[var(--c-muted)] mb-1">توضیحات مشاور</label>
                        <textarea id="description" rows="2" placeholder="نکات تکمیلی برای دانش‌آموز..." class="draft-field w-full resize-none">></textarea>
                    </div>

                    <div id="status-container" class="hidden mt-4 pt-4 border-t border-[var(--c-border)] flex justify-between items-center">
                        <span class="text-sm font-medium text-[var(--c-muted)]">وضعیت انجام:</span>
                        <span id="completion-status" class="text-sm font-bold"></span>
                    </div>

                    <div id="comments-section" class="hidden mt-3">
                        <label class="block text-xs font-medium text-[var(--c-muted)] mb-2">نظرات دانش‌آموز</label>
                        <div id="comments-list" class="draft-inset space-y-2 max-h-40 overflow-y-auto p-3"></div>
                    </div>
                </form>
            </div>

            <div class="draft-surface-top flex flex-wrap sm:flex-nowrap justify-between items-center gap-3 p-4 sm:p-5 shrink-0">
                <div class="w-full sm:w-auto flex justify-start order-2 sm:order-1">
                    <button type="button" id="btn-delete" onclick="ScheduleApp.deleteEvent()" class="draft-btn draft-btn--danger hidden w-full sm:w-auto">
                        <i class="fas fa-trash w-4 h-4" aria-hidden="true"></i>
                        <span>حذف برنامه</span>
                    </button>
                </div>
                <div class="flex gap-2 w-full sm:w-auto order-1 sm:order-2">
                    <button type="button" onclick="ScheduleApp.closeModal()" class="draft-btn draft-btn--outline flex-1 sm:flex-none">انصراف</button>
                    <button type="button" onclick="ScheduleApp.saveEvent()" class="draft-btn draft-btn--primary flex-1 sm:flex-none">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="draft-toast fixed bottom-5 left-1/2 -translate-x-1/2 text-[var(--c-text)] px-4 py-3 flex items-center gap-3 transition-all duration-300 transform translate-y-20 opacity-0 z-50">
        <i class="fas fa-circle-check w-5 h-5 draft-success-text" aria-hidden="true"></i>
        <span id="toast-msg" class="text-sm font-medium">عملیات با موفقیت انجام شد</span>
    </div>
</div>

@vite(['resources/js/features/consultant-schedule.js'])
@endsection
