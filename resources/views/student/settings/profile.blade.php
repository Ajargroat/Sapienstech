@extends('student.settings.layout')

{{--
    Student hub home — same body as the consultant hub (shared partial); the
    action tiles mirror the student top navigation, so a feature turned off
    for the tenant disappears from both lists at once.
--}}
@section('settings-content')
@php
    $actions = [
        ['label' => $labels['dashboard'] ?? 'داشبورد', 'icon' => 'fa-gauge', 'url' => route('student.dashboard')],
    ];

    if (site('features.report_cards', false) && \Illuminate\Support\Facades\Route::has('student.report-card')) {
        $actions[] = ['label' => 'کارنامه', 'icon' => 'fa-file-lines', 'url' => route('student.report-card')];
    }

    if (site('features.student_chat', false) && \Illuminate\Support\Facades\Route::has('student.direct-chat.page')) {
        $actions[] = ['label' => $labels['student_chat'] ?? 'گفتگو', 'icon' => 'fa-comments', 'url' => route('student.direct-chat.page')];
    }
@endphp

@include('partials.profile-home', [
    'profile' => $student,
    'portal' => 'student',
    'actions' => $actions,
])
@endsection
