{{--
    The Theme Studio — standalone page (renders through `layouts.studio`).

    This is the old consultant appearance tab lifted out of the profile hub:
    same workspace markup (rail + panels + live preview iframe), but the
    form and helpers now post to the standalone `studio.*` routes and the
    page owns its flashes (the settings layout used to render them).

    The shell layout loads the studio entry (`studio-standalone.js`) and the
    platform stylesheet — nothing tenant-themed reaches this page.
--}}

@extends('layouts.studio')

@section('content')

@if(session('success'))
    {{-- Success notices are transient toasts, not in-flow rows: app.js fades
         and removes them after data-flash-timeout ms. --}}
    <div class="settings-flash settings-flash--success studio-toast" role="status" data-flash-timeout="5000">
        <i class="fas fa-check-circle" aria-hidden="true"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    {{-- Validation errors no longer render as a big banner: the status-bar
         indicator (bottom of the page) surfaces them as an icon + count and
         its popover lists every message, each clickable to the field. This
         hidden list is the server-rendered seed; live-preview 422s join in. --}}
    <ul class="studio-error-seed" data-studio-error-seed hidden>
        @foreach($errors->all() as $message)
            <li data-message="{{ $message }}"></li>
        @endforeach
    </ul>
@endif

@if($previewing)
    <div class="settings-flash studio-preview-banner" role="status">
        <i class="fas fa-eye" aria-hidden="true"></i>
        <span>Preview mode is on — unsaved changes show across the whole site.</span>
        <form method="POST" action="{{ route('studio.preview.exit') }}" data-router="off">
            @csrf
            <button type="submit" class="secondary-button">Exit preview</button>
        </form>
    </div>
@endif

@php
    // The rail and the panels iterate the same filtered groups, so a tab can
    // never point at a group the tenant is not allowed to see.
    $studioGroups = \App\Support\StudioSchema::groups();
    if (! $isTenantAdmin) {
        $studioGroups = array_filter($studioGroups, fn ($g) => ! (bool) ($g['admin'] ?? false));
    }

    $groupChanged = [];
    foreach ($studioGroups as $gKey => $g) {
        $groupChanged[$gKey] = collect($g['fields'])
            ->filter(fn ($f) => array_key_exists($f['path'], $overrides))
            ->count();
    }

    // A failed save must reopen the rail on the group holding the error,
    // overriding whatever tab the browser had remembered. List rows fail as
    // "path.<key>.<field>", so the group check widens to the wildcard.
    $errorGroup = null;
    foreach ($studioGroups as $gKey => $g) {
        foreach ($g['fields'] as $f) {
            $hasError = $errors->has($f['path'])
                || (($f['control'] ?? '') === 'list' && $errors->has($f['path'].'.*'));

            if ($hasError) { $errorGroup = $gKey; break 2; }
        }
    }
    $initialGroup = $errorGroup ?? (array_key_first($studioGroups) ?? null);

    // Canvas-only groups (the per-element override store) are rendered so their
    // inputs submit, but they are not a settings surface: the inspector writes
    // their rows, so exposing them as a panel would be a second, worse way in.

    // Page switcher: only routes this session can actually OPEN inside the
    // frame. The login page sits behind `guest` (an authed session is
    // redirected away), the student portal behind its own auth guard, and the
    // teacher panel behind isTeacher() — listing them would promise a canvas
    // that can never render. The id namespaces for those pages still exist
    // for whenever their layouts do render (Phase 1b / DashboardIdsTest).
    $studioPages = [
        ['key' => 'landing', 'label' => 'Home', 'url' => route('home')],
    ];
    if (site('features.dashboard')) {
        $studioPages[] = ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => route('consultant.dashboard')];
    }
    if (site('features.teacher_panel') && auth()->user()?->isTeacher()) {
        $studioPages[] = ['key' => 'teacher', 'label' => 'Teacher panel', 'url' => route('teacher.dashboard')];
    }
@endphp

<div class="studio-split studio-workspace" id="studio-split">
    {{-- Panel sashes: drag a border to resize a panel, drag past the
         collapse threshold (or double-click) to fold it away. Absolutely
         positioned over the column boundaries, so they take no grid cell. --}}
    <div class="studio-sash" data-studio-sash="rail" role="separator"
         aria-orientation="vertical" aria-label="Resize left panel" tabindex="0"></div>
    <div class="studio-sash" data-studio-sash="inspector" role="separator"
         aria-orientation="vertical" aria-label="Resize right panel" tabindex="0"></div>

    <aside class="studio-workspace-rail" aria-label="Studio tools">
        {{-- Draft: the single named checkpoint, at the top of the left panel
             (Prompts §6). It snapshots the FORM (which lives in the main
             column), so the URLs ride here as data — studio-draft.js owns
             the body. Restore re-submits the payload as scope=preview: the
             working layer only, never the published config. --}}
        <details class="studio-workspace-draft" data-draft-panel aria-label="Draft"
                 data-draft-show="{{ route('studio.draft.show') }}"
                 data-draft-store="{{ route('studio.draft.store') }}"
                 data-draft-update="{{ route('studio.draft.update') }}"
                 data-draft-destroy="{{ route('studio.draft.destroy') }}">
            <summary class="studio-workspace-tree-title">
                <i class="fas fa-file-lines" aria-hidden="true"></i>
                <span>Draft</span>
            </summary>
            <div class="studio-workspace-draft-body" data-draft-body></div>
        </details>

        {{-- The layer tree names the whole document, so it owns the rail; the
             schema groups live in the right column's Design area as one
             collapsible accordion (below the object inspector). --}}
        <section class="studio-workspace-tree" aria-label="Page layers">
            <h2 class="studio-workspace-tree-title">
                <i class="fas fa-layer-group" aria-hidden="true"></i>
                <span>Layers</span>
            </h2>
            <ul data-page-layers aria-label="Page layers"></ul>
        </section>
    </aside>

    <div class="studio-main">
        <form method="POST" action="{{ route('studio.save') }}"
              enctype="multipart/form-data" class="studio-form" data-router="off" id="studio-form"
              data-live-url="{{ route('studio.live') }}"
              data-home-url="{{ route('home') }}"
              data-studio-tokens='{!! json_encode(\App\Support\ThemeTokens::VARS, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}'>
            @csrf

            {{-- Contextual properties: the real schema controls for the element
                 selected in the canvas are relocated here (still inside the
                 form, so they keep submitting). One field node is ever in one
                 place, so no duplicate input names can reach the server. --}}
            <section class="studio-context" data-context-panel hidden aria-label="Selected element properties">
                <header class="studio-context-head">
                    <span class="studio-context-eyebrow">Properties</span>
                    <strong class="studio-context-title" data-context-title></strong>
                    <button type="button" class="studio-context-close" data-context-clear aria-label="Close properties">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>
                <p class="studio-context-note" data-context-note></p>
                <nav class="studio-context-crumb" data-context-crumb hidden aria-label="Selected element path"></nav>
                <div class="studio-context-fields" data-context-fields></div>
            </section>

            {{-- Object inspector: title always visible, diagnostics (metrics,
                 shortcuts, layers) collapsible so the editable fields above
                 stay the first thing on screen. --}}
            <section class="studio-object-inspector" data-object-inspector aria-label="Selected element properties">
                <header class="studio-object-head">
                    <span class="studio-object-eyebrow">Element inspector</span>
                    <h2 data-object-title aria-live="polite">No element selected</h2>
                    <button type="button" class="studio-object-collapse" data-object-collapse
                            aria-expanded="false" aria-label="Toggle element details">
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="studio-object-collapse" data-inspector-toggle
                            aria-expanded="true" title="Close inspector panel" aria-label="Close or open inspector panel">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>
                <p data-object-scope>Select an element in the preview to see its properties.</p>
                <dl class="studio-object-metrics" data-object-metrics aria-label="Element size and position"></dl>
                <div class="studio-object-controls" data-object-controls role="group" aria-label="Element shortcuts"></div>
            </section>

            {{-- Design: the global schema groups. These tabs used to live on the
                 left rail, which the layer tree now owns; the right column is
                 where a selected node's properties are edited, so the global
                 version of the same controls belongs beside them. --}}
            <h2 class="studio-design-title">
                <i class="fas fa-palette" aria-hidden="true"></i>
                <span>Design</span>
            </h2>

            <div class="settings-cards">
                @foreach($studioGroups as $groupKey => $group)
                    @php $changedCount = $groupChanged[$groupKey] ?? 0; @endphp

                    {{-- Tab panel: every group but the active one is hidden
                         outright, so the bar shows one section at a time. --}}
                    <details class="settings-card studio-group" id="studio-group-{{ $groupKey }}"
                             data-studio-group="{{ $groupKey }}"
                             @if($initialGroup === $groupKey) open @else hidden @endif
                             @if($errorGroup === $groupKey) data-studio-error @endif>
                        <summary class="studio-group-head">
                            <i class="fas {{ $group['icon'] ?? 'fa-sliders-h' }} studio-group-icon" aria-hidden="true"></i>
                            <h3 class="settings-card-title">{{ $group['label'] }}</h3>
                            @if(!empty($group['hint']))
                                <button type="button" class="studio-hint" data-studio-hint aria-label="Section help">
                                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                                    <span class="studio-hint-bubble" role="tooltip">{{ $group['hint'] }}</span>
                                </button>
                            @endif
                            @if($changedCount)
                                <span class="studio-group-count" title="{{ $changedCount }} changed">
                                    <span class="studio-badge" aria-hidden="true">●</span>
                                    {{ $changedCount }}
                                </span>
                            @endif
                            <i class="fas fa-chevron-down studio-group-caret" aria-hidden="true"></i>
                        </summary>
                        <div class="studio-group-body">
                            <div class="studio-grid">
                                @foreach($group['fields'] as $field)
                                    @include('consultant.settings.partials._field', [
                                        'field' => $field,
                                        'resolved' => $resolved,
                                        'overrides' => $overrides,
                                        'canPublishEveryone' => $canPublishEveryone,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </form>

        {{-- Reset (per key) posts here; JS fills path + scope then submits. --}}
        <form method="POST" action="{{ route('studio.reset') }}" id="studio-reset-form" class="hidden-form" data-router="off">
            @csrf
            <input type="hidden" name="path" value="">
            <input type="hidden" name="scope" value="everyone">
        </form>

        @if($overrides !== [] && $canPublishEveryone)
            <div class="studio-reset-all">
                <form method="POST" action="{{ route('studio.reset.all') }}" data-router="off"
                      onsubmit="return confirm('Delete all site-wide appearance changes?');">
                    @csrf
                    <button type="submit" class="link-danger">
                        <i class="fas fa-recycle" aria-hidden="true"></i> Reset all site-wide changes ({{ count($overrides) }})
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- Live preview: an iframe of the public site, same session, so the
         studio's preview layer renders in it. Token changes are patched in
         as CSS variables without a reload; structural ones reload it. The
         pane is always visible — the preview is the studio's main canvas. --}}
    <aside class="studio-preview-pane" id="studio-preview-pane">
        <div class="studio-preview-head">
            <span class="studio-live-dot" data-studio-live-dot data-state="idle" aria-hidden="true"></span>
            <span>Live preview</span>
        </div>
        {{-- The iframe is sized to the emulated device's CSS width and
             scaled to fit this stage (theme-studio.js). --}}
        <div class="studio-preview-stage" data-studio-preview-stage>
            <iframe class="studio-preview-frame" data-studio-preview-frame title="Site preview" src="about:blank"></iframe>
        </div>
        {{-- Floating tool bar, overlaid bottom-center of the canvas like
             Figma's — a pill, not a pane, so the canvas keeps its space. --}}
        <div class="studio-tool-dock" role="group" aria-label="Canvas tools">
            <button type="button" class="studio-workspace-tool" data-workspace-tool="select"
                    title="Select" aria-label="Select" aria-pressed="true">
                <i class="fas fa-arrow-pointer" aria-hidden="true"></i>
            </button>
            <button type="button" class="studio-workspace-tool" data-workspace-tool="interact"
                    title="Interact with page" aria-label="Interact with page" aria-pressed="false">
                <i class="fas fa-hand" aria-hidden="true"></i>
            </button>
        </div>
    </aside>
</div>

{{-- Bottom status bar: preview chrome (devices, zoom, frame actions) on one
     side, save + dirty + error indicator on the other — the classic thin
     application status bar that replaced the old top toolbar. --}}
<footer class="studio-statusbar studio-workspace" aria-label="Studio status bar">
    <div class="studio-preview-devices" role="group" aria-label="Viewport size">
        <button type="button" class="studio-preview-device" data-studio-preview-device="desktop" title="Desktop view" aria-label="Desktop view" aria-pressed="true">
            <i class="fas fa-display" aria-hidden="true"></i>
        </button>
        <button type="button" class="studio-preview-device" data-studio-preview-device="tablet" title="Tablet view" aria-label="Tablet view" aria-pressed="false">
            <i class="fas fa-tablet-screen-button" aria-hidden="true"></i>
        </button>
        <button type="button" class="studio-preview-device" data-studio-preview-device="mobile" title="Mobile view" aria-label="Mobile view" aria-pressed="false">
            <i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
        </button>
    </div>

    {{-- Page switcher: which route the canvas frame shows. theme-studio.js
         swaps the frame to the chosen URL; studio-workspace then rebuilds the
         tree against that frame's own data-studio-path ids. --}}
    <div class="studio-preview-pages" role="group" aria-label="Preview page">
        @foreach ($studioPages as $page)
            <button type="button" class="studio-statusbar-btn studio-preview-page"
                    data-studio-page="{{ $page['url'] }}"
                    title="{{ $page['label'] }}" aria-label="{{ $page['label'] }}"
                    aria-pressed="{{ $page['key'] === 'landing' ? 'true' : 'false' }}">
                {{ $page['label'] }}
            </button>
        @endforeach
    </div>

    <div class="studio-workspace-zoom" role="group" aria-label="Preview zoom">
        <button type="button" class="studio-statusbar-btn" data-studio-zoom-fit title="Fit to screen" aria-label="Fit to screen" aria-pressed="true">
            <i class="fas fa-expand" aria-hidden="true"></i>
        </button>
        <input type="range" id="studio-zoom" class="studio-zoom-bar" data-studio-zoom min="0.25" max="1.25" step="0.05" value="1" aria-label="Preview zoom">
        <output class="studio-zoom-value" data-studio-zoom-value for="studio-zoom">Fit</output>
    </div>

    <button type="button" class="studio-preview-action studio-statusbar-btn" data-studio-preview-reload title="Reload" aria-label="Reload preview">
        <i class="fas fa-rotate" aria-hidden="true"></i>
    </button>
    <a class="studio-preview-action studio-statusbar-btn" href="{{ route('home') }}" data-studio-preview-open target="_blank" rel="noopener" title="Open in new tab" aria-label="Open preview in a new tab">
        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
    </a>
    <button type="button" class="studio-preview-action studio-statusbar-btn" data-studio-preview-max title="Maximize" aria-label="Toggle preview fullscreen">
        <i class="fas fa-expand" aria-hidden="true"></i>
    </button>

    <span class="studio-statusbar-spacer" aria-hidden="true"></span>

    {{-- Error/validation indicator: seeded from the server-rendered error
         list and fed by live-preview 422s; click opens the message popover. --}}
    <button type="button" class="studio-statusbar-btn studio-errors-toggle" data-studio-errors-toggle hidden
            title="Validation errors" aria-label="Validation errors" aria-expanded="false">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        <span class="studio-errors-count" data-studio-errors-count>0</span>
    </button>

    {{-- Unsaved-changes indicator: the live preview writes to the session
         only, so an edit is "pending" until one of the save buttons is used. --}}
    <span class="studio-dirty" data-studio-dirty hidden role="status" aria-live="polite">
        <span class="studio-dirty-dot" aria-hidden="true"></span>
        <span data-studio-dirty-text>Unsaved</span>
    </span>

    {{-- Save: one action only — publish for everyone (or the personal
         fallback when the user holds no site-wide grant). The button opens
         a confirmation dialog instead of a scope menu; the dialog's submit
         button carries the scope through the `form` attribute. --}}
    <div class="studio-save-wrap">
        <button type="button" class="studio-statusbar-save" data-studio-save-jump hidden>
            <i class="fas fa-save" aria-hidden="true"></i>
            Save changes
        </button>
        <dialog class="studio-confirm" data-save-confirm aria-labelledby="studio-save-confirm-title">
            <h3 id="studio-save-confirm-title" data-save-confirm-title>
                {{ $canPublishEveryone ? 'Publish these changes?' : 'Save these changes?' }}
            </h3>
            <p data-save-confirm-text>
                @if($canPublishEveryone)
                    Saving applies your edits to the live site for every visitor immediately. Unsaved changes in other areas of this page will be published together.
                @else
                    Saving applies your edits to your personal view only. Other visitors keep the published theme.
                @endif
            </p>
            <div class="studio-confirm-actions">
                <button type="button" class="studio-confirm-cancel" data-save-confirm-cancel>Cancel</button>
                <button type="submit" form="studio-form" name="scope" value="{{ $canPublishEveryone ? 'everyone' : 'me' }}"
                        class="studio-confirm-go">
                    {{ $canPublishEveryone ? 'Publish for everyone' : 'Save for me' }}
                </button>
            </div>
        </dialog>
    </div>
</footer>

@endsection
