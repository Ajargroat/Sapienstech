{{--
    Profile hub shell (consultant), Telegram-style:

      • a slim header row — just the title on the hub home, a back link plus
        the section title inside every other section (the settings list on
        the home page replaces the horizontal tab bar entirely: one vertical
        list instead of a fake navigation inside the page),
      • the centered identity hero, home only,
      • shared flashes, then the section content.

    $tabs      — visible section definitions from App\Support\SettingsTabs
                 (single hub route, ?tab= selectors — no per-section pages)
    $activeTab — key of the current section

    Everything reads tenant theme tokens, so the shell restyles itself with
    the tenant's theme without any per-page CSS.
--}}
@extends('layouts.consultant')

@php
    $isHome = ($activeTab ?? 'profile') === 'profile';
    $activeLabel = collect($tabs ?? [])->firstWhere('key', $activeTab ?? '')['label'] ?? null;
@endphp

@section('content')
<div class="settings-shell">
    <header class="profile-header">
        @unless($isHome)
            <a class="profile-header-back" href="{{ route('consultant.settings.profile') }}" data-router="replace"
               aria-label="{{ $labels['settings_profile'] ?? 'پروفایل' }}">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
        @endunless
        <span class="profile-header-title">
            {{ $isHome ? ($labels['settings_profile'] ?? 'پروفایل') : $activeLabel }}
        </span>
    </header>

    @if($isHome)
        @include('partials.profile-hero', ['profile' => auth()->user(), 'portal' => 'consultant'])
    @endif

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
