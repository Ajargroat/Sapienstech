{{--
    Teacher panel dashboard — overview of the teacher's own workspace.

    Rendered by App\Http\Controllers\Teacher\TeacherDashboardController::index()
    through `teacher.dashboard`. Mirrors the student dashboard's design
    language (stat cards, panels, Jalali dates rewritten by the
    teacher-panel.js feature bundle).
--}}
@extends('layouts.teacher')

@section('content')
{{-- Studio ids: top-level regions of the teacher dashboard, addressable from
     the canvas under the template-owned `public.teacher.*` root (see
     StudioStyles::TEMPLATE_ROOTS). --}}
<template data-studio-section-marker="welcome"></template>
<div class="student-welcome" data-studio-path="public.teacher.welcome">
    <section class="student-profile-head">
        <span class="student-avatar-lg">{{ mb_substr($teacher->name, 0, 1) }}</span>
        <div>
            <h2>خوش آمدید، {{ $teacher->name }}</h2>
            <div class="student-tags">
                <span class="student-tag student-tag-grade">پنل معلم</span>
            </div>
            <span class="student-email" dir="ltr">{{ $teacher->email }}</span>
        </div>
    </section>

    <div class="student-today">
        <span class="student-today-label">امروز</span>
        <time class="fa-date" datetime="{{ now()->format('Y-m-d\TH:i') }}Z">{{ persian_digits(now()->format('Y/m/d')) }}</time>
    </div>
</div>

<template data-studio-section-marker="stats"></template>
<div class="student-stats" data-stagger data-studio-path="public.teacher.stats">
    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--primary"><i class="fas fa-user-group"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">{{ persian_digits($stats['students']) }}</span>
            <span class="student-stat-label">دانش‌آموز</span>
        </div>
    </div>

    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--info"><i class="fas fa-book-open-reader"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">{{ persian_digits($stats['materials']) }}</span>
            <span class="student-stat-label">جزوه منتشرشده</span>
        </div>
    </div>

    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--secondary"><i class="fas fa-tasks"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">{{ persian_digits($stats['pending_reviews']) }}</span>
            <span class="student-stat-label">تحویل در انتظار بررسی</span>
        </div>
    </div>

    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--success"><i class="fas fa-check-circle"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">{{ persian_digits($stats['completed']) }}</span>
            <span class="student-stat-label">تکلیف تکمیل‌شده</span>
        </div>
    </div>
</div>

<div class="student-side">
    <section class="panel student-panel">
        <header class="student-panel-head">
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

    <section class="panel student-panel">
        <header class="student-panel-head">
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

    @if(site('features.teacher_assignments', false))
        <section class="panel student-panel">
            <header class="student-panel-head">
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
</div>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
