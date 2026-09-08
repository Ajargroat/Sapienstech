@extends('consultant.settings.layout')

@section('settings-content')
@if($previewing)
    <div class="settings-flash studio-preview-banner" role="status">
        <i class="fas fa-eye" aria-hidden="true"></i>
        <span>حالت پیش‌نمایش فعال است — تغییرات ذخیره‌نشده را در سراسر سایت می‌بینید.</span>
        <form method="POST" action="{{ route('consultant.settings.appearance.preview.exit') }}" data-router="off">
            @csrf
            <button type="submit" class="secondary-button">خروج از پیش‌نمایش</button>
        </form>
    </div>
@endif

<div class="studio-toolbar">
    <span class="studio-toolbar-title">
        <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
        استودیوی ظاهر
    </span>

    <label class="studio-live-toggle">
        <input type="checkbox" data-studio-live>
        <span>پیش‌نمایش زنده</span>
    </label>
</div>

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
@endphp

<div class="studio-split" id="studio-split">
    <div class="studio-main">
        {{-- Inspector rail: one tab per group; clicking a tab shows only that
             group's panel (theme-studio.js switches panels). --}}
        <nav class="studio-tabs" aria-label="بخش‌های تنظیمات ظاهر">
            @foreach($studioGroups as $groupKey => $group)
                <button type="button" class="studio-tab" data-studio-tab="{{ $groupKey }}"
                        aria-controls="studio-group-{{ $groupKey }}" aria-current="{{ $initialGroup === $groupKey ? 'true' : 'false' }}">
                    <i class="fas {{ $group['icon'] ?? 'fa-sliders-h' }}" aria-hidden="true"></i>
                    <span>{{ $group['label'] }}</span>
                    @if($groupChanged[$groupKey] ?? false)
                        <span class="studio-badge" title="{{ $groupChanged[$groupKey] }} مورد تغییر کرده است" aria-hidden="true">●</span>
                    @endif
                </button>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('consultant.settings.appearance.save') }}"
              enctype="multipart/form-data" class="studio-form" data-router="off" id="studio-form"
              data-live-url="{{ route('consultant.settings.appearance.live') }}"
              data-home-url="{{ route('home') }}">
            @csrf

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

                {{-- Scope decision: the tenant admin is in charge of "for everyone" --}}
                <section class="settings-card studio-save-card">
                    <h3 class="settings-card-title">اعمال تغییرات</h3>
                    <p class="settings-card-text">
                        مشخص کنید این تغییرات برای همهٔ بازدیدکنندگان اعمال شود یا فقط برای نمای شما.
                        @unless($canPublishEveryone)
                            <br><small>انتشار سراسری تنها برای مدیر مجموعه فعال است.</small>
                        @endunless
                    </p>

                    <div class="studio-save-actions">
                        <button type="submit" name="scope" value="preview" class="secondary-button">
                            <i class="fas fa-eye" aria-hidden="true"></i> پیش‌نمایش
                        </button>
                        <button type="submit" name="scope" value="me" class="secondary-button">
                            <i class="fas fa-user" aria-hidden="true"></i> فقط برای من
                        </button>
                        @if($canPublishEveryone)
                            <button type="submit" name="scope" value="everyone" class="primary-button">
                                <i class="fas fa-globe" aria-hidden="true"></i> برای همه
                            </button>
                        @endif
                    </div>
                </section>
            </div>
        </form>

        {{-- Reset (per key) posts here; JS fills path + scope then submits. --}}
        <form method="POST" action="{{ route('consultant.settings.appearance.reset') }}" id="studio-reset-form" class="hidden-form" data-router="off">
            @csrf
            <input type="hidden" name="path" value="">
            <input type="hidden" name="scope" value="everyone">
        </form>

        @if($overrides !== [] && $canPublishEveryone)
            <div class="studio-reset-all">
                <form method="POST" action="{{ route('consultant.settings.appearance.reset.all') }}" data-router="off"
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
         as CSS variables without a reload; structural ones reload it. --}}
    <aside class="studio-preview-pane" id="studio-preview-pane" hidden>
        <div class="studio-preview-head">
            <span class="studio-live-dot" data-studio-live-dot data-state="idle" aria-hidden="true"></span>
            <span>پیش‌نمایش زنده</span>
            <div class="studio-preview-devices" role="group" aria-label="اندازهٔ نمایش">
                <button type="button" class="studio-preview-device" data-studio-preview-device="desktop" title="نمایش دسکتاپ">
                    <i class="fas fa-display" aria-hidden="true"></i>
                </button>
                <button type="button" class="studio-preview-device" data-studio-preview-device="mobile" title="نمایش موبایل">
                    <i class="fas fa-mobile-screen-button" aria-hidden="true"></i>
                </button>
            </div>
            <button type="button" class="studio-preview-action" data-studio-preview-reload title="بارگذاری دوباره">
                <i class="fas fa-rotate" aria-hidden="true"></i>
            </button>
            <a class="studio-preview-action" href="{{ route('home') }}" target="_blank" rel="noopener" title="باز کردن در تب جدید">
                <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
            </a>
            <button type="button" class="studio-preview-action" data-studio-preview-max title="تمام‌صفحه">
                <i class="fas fa-expand" aria-hidden="true"></i>
            </button>
        </div>
        {{-- The iframe is sized to the emulated device's CSS width and
             scaled to fit this stage (theme-studio.js). --}}
        <div class="studio-preview-stage" data-studio-preview-stage>
            <iframe class="studio-preview-frame" data-studio-preview-frame title="پیش‌نمایش سایت" src="about:blank"></iframe>
        </div>
    </aside>
</div>

@vite(['resources/js/features/theme-studio.js'])
@endsection
