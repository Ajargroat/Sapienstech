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
        <span>حالت پیش‌نمایش فعال است — تغییرات ذخیره‌نشده را در سراسر سایت می‌بینید.</span>
        <form method="POST" action="{{ route('studio.preview.exit') }}" data-router="off">
            @csrf
            <button type="submit" class="secondary-button">خروج از پیش‌نمایش</button>
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
    // inputs submit, but they get no rail tab: the inspector writes their rows,
    // so exposing them as a settings panel would be a second, worse way in.
    $railGroups = array_filter($studioGroups, fn ($g) => ! ($g['canvas_only'] ?? false));
@endphp

<div class="studio-split studio-workspace" id="studio-split">
    <aside class="studio-workspace-rail" aria-label="ابزارهای استودیو">
        <div class="studio-workspace-tools" role="group" aria-label="حالت ابزار">
            <button type="button" class="studio-workspace-tool" data-workspace-tool="select"
                    title="انتخاب عنصر" aria-label="انتخاب عنصر" aria-pressed="true">
                <i class="fas fa-arrow-pointer" aria-hidden="true"></i>
            </button>
            <button type="button" class="studio-workspace-tool" data-workspace-tool="interact"
                    title="تعامل با صفحه" aria-label="تعامل با صفحه" aria-pressed="false">
                <i class="fas fa-hand" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="studio-tabs" aria-label="بخش‌های تنظیمات ظاهر">
            @foreach($railGroups as $groupKey => $group)
                <button type="button" class="studio-tab" data-studio-tab="{{ $groupKey }}"
                        aria-controls="studio-group-{{ $groupKey }}" aria-current="{{ $initialGroup === $groupKey ? 'true' : 'false' }}"
                        title="{{ $group['label'] }}" aria-label="{{ $group['label'] }}">
                    <i class="fas {{ $group['icon'] ?? 'fa-sliders-h' }}" aria-hidden="true"></i>
                    @if($groupChanged[$groupKey] ?? false)
                        <span class="studio-badge" title="{{ $groupChanged[$groupKey] }} مورد تغییر کرده است" aria-hidden="true">●</span>
                    @endif
                </button>
            @endforeach
        </nav>

        @if(array_key_exists('blocks', $studioGroups))
            <div class="studio-workspace-tools" role="group" aria-label="افزودن بلوک">
                @foreach([
                    'heading' => ['fa-heading', 'افزودن عنوان'],
                    'text' => ['fa-align-right', 'افزودن متن'],
                    'button' => ['fa-square-plus', 'افزودن دکمه'],
                    'card' => ['fa-id-card', 'افزودن کارت'],
                    'image' => ['fa-image', 'افزودن تصویر'],
                    'spacer' => ['fa-arrows-up-down', 'افزودن فاصله'],
                ] as $insertType => [$insertIcon, $insertLabel])
                    <button type="button" class="studio-workspace-tool" data-workspace-insert="{{ $insertType }}"
                            title="{{ $insertLabel }}" aria-label="{{ $insertLabel }}">
                        <i class="fas {{ $insertIcon }}" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>
            <div class="studio-workspace-tools" role="group" aria-label="عملیات بلوک‌ها">
                @foreach([
                    'undo' => ['fa-rotate-left', 'واگرد تغییر بلوک‌ها'],
                    'redo' => ['fa-rotate-right', 'انجام دوبارهٔ تغییر بلوک‌ها'],
                    'duplicate' => ['fa-clone', 'تکثیر بلوک انتخاب‌شده'],
                    'reset-style' => ['fa-eraser', 'بازنشانی سبک بلوک انتخاب‌شده'],
                ] as $workspaceAction => [$actionIcon, $actionLabel])
                    <button type="button" class="studio-workspace-tool" data-workspace-action="{{ $workspaceAction }}"
                            title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}">
                        <i class="fas {{ $actionIcon }}" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>
        @endif
    </aside>

    <div class="studio-main">
        <form method="POST" action="{{ route('studio.save') }}"
              enctype="multipart/form-data" class="studio-form" data-router="off" id="studio-form"
              data-live-url="{{ route('studio.live') }}"
              data-home-url="{{ route('home') }}">
            @csrf

            {{-- Contextual properties: the real schema controls for the element
                 selected in the canvas are relocated here (still inside the
                 form, so they keep submitting). One field node is ever in one
                 place, so no duplicate input names can reach the server. --}}
            <section class="studio-context" data-context-panel hidden aria-label="ویژگی‌های عنصر انتخاب‌شده">
                <header class="studio-context-head">
                    <span class="studio-context-eyebrow">ویژگی‌ها</span>
                    <strong class="studio-context-title" data-context-title></strong>
                    <button type="button" class="studio-context-close" data-context-clear aria-label="بستن ویژگی‌ها">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>
                <p class="studio-context-note" data-context-note></p>
                <div class="studio-context-fields" data-context-fields></div>
            </section>

            {{-- Object inspector: title always visible, diagnostics (metrics,
                 shortcuts, layers) collapsible so the editable fields above
                 stay the first thing on screen. --}}
            <section class="studio-object-inspector" data-object-inspector aria-label="ویژگی‌های عنصر انتخاب‌شده">
                <header class="studio-object-head">
                    <span class="studio-object-eyebrow">بازرس عنصر</span>
                    <h2 data-object-title aria-live="polite">عنصری انتخاب نشده است</h2>
                    <button type="button" class="studio-object-collapse" data-object-collapse
                            aria-expanded="false" aria-label="نمایش یا بستن جزئیات عنصر">
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="studio-object-collapse" data-inspector-toggle
                            aria-expanded="true" title="بستن پنل بازرس" aria-label="بستن یا باز کردن پنل بازرس">
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>
                <p data-object-scope>برای مشاهدهٔ ویژگی‌ها، عنصری را در پیش‌نمایش انتخاب کنید.</p>
                <dl class="studio-object-metrics" data-object-metrics aria-label="ابعاد و موقعیت عنصر"></dl>
                <div class="studio-object-controls" data-object-controls role="group" aria-label="میان‌برهای عنصر"></div>
                <details class="studio-page-layers">
                    <summary><i class="fas fa-layer-group" aria-hidden="true"></i> بخش‌های صفحه</summary>
                    <ul data-page-layers aria-label="لایه‌های صفحه"></ul>
                </details>
            </section>

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
                                <button type="button" class="studio-hint" data-studio-hint aria-label="راهنمای این بخش">
                                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                                    <span class="studio-hint-bubble" role="tooltip">{{ $group['hint'] }}</span>
                                </button>
                            @endif
                            @if($changedCount)
                                <span class="studio-group-count" title="{{ $changedCount }} مورد تغییر کرده است">
                                    <span class="studio-badge" aria-hidden="true">●</span>
                                    {{ persian_digits($changedCount) }}
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
                      onsubmit="return confirm('همهٔ تغییرات ظاهری سراسری حذف شوند؟');">
                    @csrf
                    <button type="submit" class="link-danger">
                        <i class="fas fa-recycle" aria-hidden="true"></i> بازنشانی همهٔ تغییرات سراسری ({{ persian_digits(count($overrides)) }} مورد)
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
            <span>پیش‌نمایش زنده</span>
        </div>
        {{-- The iframe is sized to the emulated device's CSS width and
             scaled to fit this stage (theme-studio.js). --}}
        <div class="studio-preview-stage" data-studio-preview-stage>
            <iframe class="studio-preview-frame" data-studio-preview-frame title="پیش‌نمایش سایت" src="about:blank"></iframe>
        </div>
    </aside>
</div>

{{-- Bottom status bar: preview chrome (devices, zoom, frame actions) on one
     side, save + dirty + error indicator on the other — the classic thin
     application status bar that replaced the old top toolbar. --}}
<footer class="studio-statusbar studio-workspace" aria-label="نوار وضعیت استودیو">
    <div class="studio-preview-devices" role="group" aria-label="اندازهٔ نمایش">
        <button type="button" class="studio-preview-device" data-studio-preview-device="desktop" title="نمایش دسکتاپ" aria-label="نمایش دسکتاپ" aria-pressed="true">
            <i class="fas fa-display" aria-hidden="true"></i>
        </button>
        <button type="button" class="studio-preview-device" data-studio-preview-device="tablet" title="نمایش تبلت" aria-label="نمایش تبلت" aria-pressed="false">
            <i class="fas fa-tablet-screen-button" aria-hidden="true"></i>
        </button>
        <button type="button" class="studio-preview-device" data-studio-preview-device="mobile" title="نمایش موبایل" aria-label="نمایش موبایل" aria-pressed="false">
            <i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
        </button>
    </div>

    <div class="studio-workspace-zoom" role="group" aria-label="بزرگ‌نمایی پیش‌نمایش">
        <button type="button" class="studio-statusbar-btn" data-studio-zoom-fit title="بزرگ‌نمایی متناسب" aria-label="بزرگ‌نمایی متناسب" aria-pressed="true">
            <i class="fas fa-expand" aria-hidden="true"></i>
        </button>
        <input type="range" id="studio-zoom" class="studio-zoom-bar" data-studio-zoom min="0.25" max="1.25" step="0.05" value="1" aria-label="بزرگ‌نمایی پیش‌نمایش">
        <output class="studio-zoom-value" data-studio-zoom-value for="studio-zoom">متناسب</output>
    </div>

    <button type="button" class="studio-preview-action studio-statusbar-btn" data-studio-preview-reload title="بارگذاری دوباره" aria-label="بارگذاری دوبارهٔ پیش‌نمایش">
        <i class="fas fa-rotate" aria-hidden="true"></i>
    </button>
    <a class="studio-preview-action studio-statusbar-btn" href="{{ route('home') }}" target="_blank" rel="noopener" title="باز کردن در تب جدید" aria-label="باز کردن پیش‌نمایش در تب جدید">
        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
    </a>
    <button type="button" class="studio-preview-action studio-statusbar-btn" data-studio-preview-max title="تمام‌صفحه" aria-label="تغییر حالت تمام‌صفحهٔ پیش‌نمایش">
        <i class="fas fa-expand" aria-hidden="true"></i>
    </button>

    <span class="studio-statusbar-spacer" aria-hidden="true"></span>

    {{-- Error/validation indicator: seeded from the server-rendered error
         list and fed by live-preview 422s; click opens the message popover. --}}
    <button type="button" class="studio-statusbar-btn studio-errors-toggle" data-studio-errors-toggle hidden
            title="خطاهای اعتبارسنجی" aria-label="خطاهای اعتبارسنجی" aria-expanded="false">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        <span class="studio-errors-count" data-studio-errors-count>0</span>
    </button>

    {{-- Unsaved-changes indicator: the live preview writes to the session
         only, so an edit is "pending" until one of the save buttons is used. --}}
    <span class="studio-dirty" data-studio-dirty hidden role="status" aria-live="polite">
        <span class="studio-dirty-dot" aria-hidden="true"></span>
        <span data-studio-dirty-text>ذخیره‌نشده</span>
    </span>

    {{-- Save: the button opens a scope dropup whose buttons submit the
         studio form through the `form` attribute. --}}
    <div class="studio-save-wrap">
        <button type="button" class="studio-statusbar-save" data-studio-save-jump hidden>
            <i class="fas fa-save" aria-hidden="true"></i>
            ذخیرهٔ تغییرات
        </button>
        <div class="studio-save-menu" data-studio-save-menu>
            <button type="submit" form="studio-form" name="scope" value="preview">
                <i class="fas fa-eye" aria-hidden="true"></i> پیش‌نمایش
            </button>
            <button type="submit" form="studio-form" name="scope" value="me">
                <i class="fas fa-user" aria-hidden="true"></i> فقط برای من
            </button>
            @if($canPublishEveryone)
                <button type="submit" form="studio-form" name="scope" value="everyone" class="is-primary">
                    <i class="fas fa-globe" aria-hidden="true"></i> برای همه
                </button>
            @endif
        </div>
    </div>
</footer>

@endsection
