{{--
    Student profile hub shell. Mirrors the consultant hub exactly (same
    header/hero machinery, same section dispatch through SettingsTabs) so
    both portals stay visually identical; extends layouts.student (same
    tenant/theme/labels composer).
--}}
@extends('layouts.student')

@php
    $isHome = ($activeTab ?? 'profile') === 'profile';
    $activeLabel = collect($tabs ?? [])->firstWhere('key', $activeTab ?? '')['label'] ?? null;
@endphp

@section('content')
<div class="settings-shell">
    @unless($isHome)
    <header class="profile-header">
            <a class="profile-header-back" href="{{ route('student.settings.profile') }}" data-router="replace"
               aria-label="{{ $labels['settings_profile'] ?? 'پروفایل' }}">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
        <span class="profile-header-title">
            {{ $activeLabel }}
        </span>
    </header>
    @endunless

    @if($isHome)
        @include('partials.profile-hero', ['profile' => auth('student')->user(), 'portal' => 'student'])
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
