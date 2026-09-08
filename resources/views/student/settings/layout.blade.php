{{--
    Student settings hub shell. Mirrors the consultant settings layout so both
    portals look identical; extends layouts.student (same tenant/theme/labels
    composer). The student hub currently has only a Profile tab, but the tab
    bar is rendered from SettingsTabs so future tabs drop in unchanged.
--}}
@extends('layouts.student')

@section('content')
<div class="settings-shell">
    <nav class="settings-tabs" data-router="replace" aria-label="{{ $labels['settings'] ?? 'تنظیمات' }}">
        @foreach($tabs as $tab)
            <a
                href="{{ route($tab['route']) }}"
                class="settings-tab {{ ($activeTab ?? null) === $tab['key'] ? 'is-active' : '' }}"
                @if(($activeTab ?? null) === $tab['key']) aria-current="page" @endif
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    @if(session('success'))
        <div class="settings-flash settings-flash--success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="settings-flash settings-flash--error" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i> {{ $errors->first() }}
        </div>
    @endif

    <div class="settings-body">
        @yield('settings-content')
    </div>
</div>
@endsection
