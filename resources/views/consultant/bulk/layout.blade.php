{{--
    Bulk Actions ("اقدامات گروهی") shell: sub-tab bar over exams / schedule /
    history. Children fill @section('bulk-content').
--}}
@extends('layouts.consultant')

@section('content')
<div class="settings-shell">
    <div class="settings-head">
        <h2><i class="fas fa-layer-group" aria-hidden="true"></i> {{ $labels['bulk_actions'] ?? 'اقدامات گروهی' }}</h2>
    </div>

    <nav class="settings-tabs" data-router="replace" aria-label="{{ $labels['bulk_actions'] ?? 'اقدامات گروهی' }}">
        @foreach([
            ['key' => 'exams',    'label' => 'آزمون گروهی',  'route' => 'consultant.bulk.exams'],
            ['key' => 'schedule', 'label' => 'برنامه گروهی', 'route' => 'consultant.bulk.schedule'],
            ['key' => 'history',  'label' => 'تاریخچه',      'route' => 'consultant.bulk.history'],
        ] as $tab)
            <a href="{{ route($tab['route']) }}"
               class="settings-tab {{ ($activeBulk ?? null) === $tab['key'] ? 'is-active' : '' }}"
               @if(($activeBulk ?? null) === $tab['key']) aria-current="page" @endif>
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
        @yield('bulk-content')
    </div>
</div>
@endsection
