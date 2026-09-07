{{--
    Settings hub shell: the tab bar shared by every settings page.

    $tabs      — visible tab definitions from App\Support\SettingsTabs
    $activeTab — key of the current tab

    Children fill @section('settings-content'). The tab list is feature- and
    route-gated in PHP, so a tab can never point at a route that 404s.
--}}
@extends('layouts.consultant')

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
