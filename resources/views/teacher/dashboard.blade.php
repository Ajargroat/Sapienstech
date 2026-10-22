{{--
    Teacher panel dashboard — overview of the teacher's own workspace.

    Rendered by App\Http\Controllers\Teacher\TeacherDashboardController::index()
    through `teacher.dashboard`. Mirrors the consultant/student dashboard
    language (hero, KPI band, main + rail) and rewrites Jalali dates through
    the teacher-panel.js feature bundle.
--}}
@extends('layouts.teacher')

@section('content')
{{-- Studio ids: top-level regions of the teacher dashboard, addressable from
     the canvas under the template-owned `public.teacher.*` root (see
     StudioStyles::TEMPLATE_ROOTS). --}}
@php
    $reviewCaption = $stats['reviews_total'] > 0
        ? persian_digits($stats['completed']).' از '.persian_digits($stats['reviews_total']).' تحویل'
        : 'هنوز تحویلی ثبت نشده';
    $firstClass = $todayClasses->first();
@endphp

<template data-studio-section-marker="welcome"></template>
<div class="dash-page">
    <header class="dash-hero" data-studio-path="public.teacher.welcome">
        <div class="dash-hero-main">
            <span class="dash-eyebrow">پنل معلم</span>
            <h1 class="dash-title">خوش آمدید، {{ $teacher->name }}</h1>
            <p class="dash-subtitle">کلاس‌های امروز، تحویل‌های تازه و مهلت‌های پیش‌روی کلاس‌هایت را یک‌جا ببین.</p>
            <div class="dash-hero-meta">
                <span class="student-avatar-lg">{{ mb_substr($teacher->name, 0, 1) }}</span>
                <div>
                    <div class="student-tags">
                        <span class="student-tag student-tag-grade">معلم</span>
                    </div>
                    <span class="student-email" dir="ltr">{{ $teacher->email }}</span>
                </div>
                <div class="student-today">
                    <span class="student-today-label">امروز</span>
                    <time class="fa-date" datetime="{{ now()->format('Y-m-d\TH:i') }}Z">{{ persian_digits(now()->format('Y/m/d')) }}</time>
                </div>
            </div>
            <div class="dash-actions">
                @if(site('features.teacher_assignments', false) && \Illuminate\Support\Facades\Route::has('teacher.assignments.index'))
                    <a href="{{ route('teacher.assignments.index') }}" class="primary-button">
                        <i class="fas fa-tasks" aria-hidden="true"></i>
                        تکالیف
                    </a>
                @elseif(site('features.teacher_schedule', false) && \Illuminate\Support\Facades\Route::has('teacher.schedule.index'))
                    <a href="{{ route('teacher.schedule.index') }}" class="primary-button">
                        <i class="fas fa-calendar-day" aria-hidden="true"></i>
                        برنامهٔ کلاسی
                    </a>
                @elseif(\Illuminate\Support\Facades\Route::has('teacher.students'))
                    <a href="{{ route('teacher.students') }}" class="primary-button">
                        <i class="fas fa-user-group" aria-hidden="true"></i>
                        دانش‌آموزان
                    </a>
                @endif
            </div>
        </div>

        @include('partials.dashboard.ring', [
            'value' => $stats['review_percent'],
            'label' => 'پیشرفت بررسی تحویل‌ها',
            'caption' => $reviewCaption,
        ])
    </header>

    <template data-studio-section-marker="stats"></template>
    <section class="dash-stats" data-stagger data-studio-path="public.teacher.stats">
        @include('partials.dashboard.stat', [
            'tone' => 'primary',
            'icon' => 'fa-user-group',
            'label' => 'دانش‌آموزان',
            'value' => $stats['students'],
            'meta' => 'در دسترس شما',
        ])
        @include('partials.dashboard.stat', [
            'tone' => 'info',
            'icon' => 'fa-book-open-reader',
            'label' => 'جزوهٔ منتشرشده',
            'value' => $stats['materials'],
            'meta' => 'منبع کلاس',
        ])
        @include('partials.dashboard.stat', [
            'tone' => 'warning',
            'icon' => 'fa-inbox',
            'label' => 'در انتظار بررسی',
            'value' => $stats['pending_reviews'],
            'meta' => 'تحویل تازه',
        ])
        @include('partials.dashboard.stat', [
            'tone' => 'success',
            'icon' => 'fa-check-circle',
            'label' => 'بررسی‌شده',
            'value' => $stats['completed'],
            'meta' => persian_digits($stats['review_percent']).'٪ از تحویل‌ها',
            'percent' => $stats['review_percent'],
        ])
    </section>

    <div class="dash-layout">
        <div class="dash-main">
            <section class="panel dash-panel">
                <header class="dash-panel-head">
                    <h2><i class="fas fa-clock"></i> کلاس‌های امروز</h2>
                    @if($todayClasses->isNotEmpty())
                        <span class="count-badge">{{ persian_digits($todayClasses->count()) }} زنگ</span>
                    @endif
                </header>

                @if($todayClasses->isNotEmpty())
                    <div class="teacher-class-list">
                        @foreach($todayClasses as $class)
                            <div class="teacher-class-row" style="--accent: {{ $class->color ?: 'var(--c-primary)' }}">
                                <span class="teacher-class-time" dir="ltr">
                                    {{ persian_digits($class->start_time->format('H:i')) }} – {{ persian_digits($class->end_time->format('H:i')) }}
                                </span>
                                <div class="teacher-class-body">
                                    <strong>{{ $class->title }}</strong>
                                    <span class="teacher-class-meta">
                                        @if($class->grade)<span><i class="fas fa-layer-group"></i> {{ $class->grade }}</span>@endif
                                        @if($class->room)<span><i class="fas fa-location-dot"></i> {{ $class->room }}</span>@endif
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state empty-state--compact">
                        <i class="far fa-calendar"></i>
                        <h3>امروز کلاسی ندارید</h3>
                        <p>زنگ‌های این روز در «برنامه کلاسی» ثبت نشده است.</p>
                    </div>
                @endif
            </section>

            <section class="panel dash-panel">
                <header class="dash-panel-head">
                    <h2><i class="fas fa-inbox"></i> آخرین تحویل‌ها</h2>
                    @if(site('features.teacher_assignments', false) && $recentSubmissions->isNotEmpty())
                        <a href="{{ route('teacher.assignments.index') }}" class="count-badge count-badge--link">مشاهده تکالیف</a>
                    @endif
                </header>

                @if($recentSubmissions->isNotEmpty())
                    <div class="teacher-class-list">
                        @foreach($recentSubmissions as $submission)
                            <a
                                href="{{ route('teacher.assignments.show', $submission->assignment) }}"
                                class="teacher-class-row"
                                style="--accent: var(--c-info)"
                            >
                                <span class="teacher-class-time">
                                    <i class="fas fa-user"></i> {{ $submission->student?->name ?? '—' }}
                                </span>
                                <div class="teacher-class-body">
                                    <strong>{{ $submission->assignment->title }}</strong>
                                    <span class="teacher-class-meta">
                                        <span class="status-pill status-pill--submitted">تحویل‌شده</span>
                                        <time class="fa-date" datetime="{{ $submission->submitted_at?->format('Y-m-d\TH:i') }}Z">
                                            {{ persian_digits(optional($submission->submitted_at)->format('Y/m/d') ?? '—') }}
                                        </time>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state empty-state--compact">
                        <i class="far fa-inbox"></i>
                        <h3>تحویل تازه‌ای نیست</h3>
                        <p>به‌محض اینکه دانش‌آموزی تکلیفی را تحویل دهد، اینجا می‌بینید.</p>
                    </div>
                @endif
            </section>
        </div>

        <aside class="dash-rail" data-studio-path="public.teacher.rail">
            <section class="dash-feature">
                <span class="dash-feature-icon"><i class="fas fa-clock" aria-hidden="true"></i></span>
                <span class="dash-feature-eyebrow">برنامهٔ امروز</span>
                <h3 class="dash-feature-title">
                    @if($todayClasses->isNotEmpty())
                        {{ persian_digits($todayClasses->count()) }} زنگ پیش‌رو
                    @else
                        کلاسی ثبت نشده
                    @endif
                </h3>
                @if($firstClass)
                    <p class="dash-feature-meta">
                        اولین زنگ: {{ persian_digits($firstClass->start_time->format('H:i')) }} — {{ $firstClass->title }}
                        @if($firstClass->room)
                            · {{ $firstClass->room }}
                        @endif
                    </p>
                @else
                    <p class="dash-feature-meta">برای امروز زنگی در برنامه ثبت نشده است.</p>
                @endif
                @if(site('features.teacher_schedule', false) && \Illuminate\Support\Facades\Route::has('teacher.schedule.index'))
                    <a href="{{ route('teacher.schedule.index') }}" class="dash-feature-cta">
                        برنامهٔ کلاسی
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    </a>
                @endif
            </section>

            @if(site('features.teacher_assignments', false))
                <section class="panel dash-panel">
                    <header class="dash-panel-head">
                        <h2><i class="fas fa-hourglass-half"></i> مهلت‌های نزدیک</h2>
                    </header>

                    @if($upcomingAssignments->isNotEmpty())
                        <div class="teacher-class-list">
                            @foreach($upcomingAssignments as $assignment)
                                <a href="{{ route('teacher.assignments.show', $assignment) }}" class="teacher-class-row" style="--accent: var(--c-warning)">
                                    <span class="teacher-class-time">
                                        <i class="fas fa-calendar-day"></i>
                                        <time class="fa-date" datetime="{{ $assignment->due_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($assignment->due_at->format('Y/m/d')) }}</time>
                                    </span>
                                    <div class="teacher-class-body">
                                        <strong>{{ $assignment->title }}</strong>
                                        <span class="teacher-class-meta">
                                            @if($assignment->grade)<span><i class="fas fa-layer-group"></i> {{ $assignment->grade }}</span>@endif
                                            @if($assignment->subject)<span><i class="fas fa-book"></i> {{ $assignment->subject }}</span>@endif
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state empty-state--compact">
                            <i class="far fa-clock"></i>
                            <h3>مهلت فعالی وجود ندارد</h3>
                            <p>تکلیفی با مهلت آینده ثبت نکرده‌اید.</p>
                        </div>
                    @endif
                </section>
            @endif

            @php
                $quickActions = [];

                if (\Illuminate\Support\Facades\Route::has('teacher.students')) {
                    $quickActions[] = [
                        'label' => 'دانش‌آموزان',
                        'href' => route('teacher.students'),
                        'icon' => 'fa-user-group',
                        'tone' => 'primary',
                    ];
                }

                if (site('features.teacher_materials', false) && \Illuminate\Support\Facades\Route::has('teacher.materials.index')) {
                    $quickActions[] = [
                        'label' => 'جزوه‌ها',
                        'href' => route('teacher.materials.index'),
                        'icon' => 'fa-book-open-reader',
                        'tone' => 'info',
                    ];
                }

                if (site('features.teacher_assignments', false) && \Illuminate\Support\Facades\Route::has('teacher.assignments.index')) {
                    $quickActions[] = [
                        'label' => 'تکالیف',
                        'href' => route('teacher.assignments.index'),
                        'icon' => 'fa-tasks',
                        'tone' => 'warning',
                    ];
                }

                if (site('features.teacher_schedule', false) && \Illuminate\Support\Facades\Route::has('teacher.schedule.index')) {
                    $quickActions[] = [
                        'label' => 'برنامهٔ کلاسی',
                        'href' => route('teacher.schedule.index'),
                        'icon' => 'fa-calendar-day',
                        'tone' => 'secondary',
                    ];
                }
            @endphp

            @if($quickActions)
                <section class="panel dash-panel" data-studio-path="public.teacher.quick_actions">
                    <header class="dash-panel-head">
                        <h2><i class="fas fa-paper-plane" aria-hidden="true"></i> دسترسی سریع</h2>
                    </header>
                    @include('partials.dashboard.quick-actions', ['actions' => $quickActions])
                </section>
            @endif
        </aside>
    </div>
</div>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
