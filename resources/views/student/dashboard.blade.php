{{--
    Student portal dashboard — the student's own workspace.

    Rendered by App\Http\Controllers\Student\StudentDashboardController::index()
    through the `student.dashboard` route. Mirrors the consultant dashboard's
    design language (layouts.student reuses the consultant shell), showing the
    student's weekly schedule, upcoming exams and recent results.

    Jalali dates render server-side as a Gregorian fallback inside
    <time class="fa-date"> and are rewritten by
    resources/js/features/student-dashboard.js (same pattern as the
    consultant exams page). Styling lives in resources/css/app.css under
    "STUDENT PORTAL DASHBOARD".
--}}
@extends('layouts.student')

@section('content')
<div class="student-welcome">
    <section class="student-profile-head">
        <span class="student-avatar-lg">{{ mb_substr($student->name, 0, 1) }}</span>
        <div>
            <h2>خوش آمدید، {{ $student->name }}</h2>
            <div class="student-tags">
                @if($student->grade)
                    <span class="student-tag student-tag-grade">{{ $student->grade }}</span>
                @endif
                @if($student->gender)
                    <span class="student-tag student-tag-gender">{{ $student->gender }}</span>
                @endif
                @if($student->major)
                    <span class="student-tag student-tag-major">{{ $student->major }}</span>
                @endif
            </div>
            <span class="student-email">{{ $student->email }}</span>
        </div>
    </section>

    <div class="student-today">
        <span class="student-today-label">امروز</span>
        <time class="fa-date" datetime="{{ now()->format('Y-m-d\TH:i') }}Z">{{ persian_digits(now()->format('Y/m/d')) }}</time>
    </div>
</div>

<div class="student-stats" data-stagger>
    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--primary"><i class="fas fa-calendar-alt"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">{{ persian_digits($stats['week_sessions']) }}</span>
            <span class="student-stat-label">جلسه این هفته</span>
        </div>
    </div>

    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--success"><i class="fas fa-check-circle"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">{{ persian_digits($stats['week_done']) }}</span>
            <span class="student-stat-label">تکمیل‌شده</span>
        </div>
    </div>

    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--info"><i class="fas fa-clipboard-list"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">{{ persian_digits($stats['upcoming_exams']) }}</span>
            <span class="student-stat-label">آزمون پیش‌رو</span>
        </div>
    </div>

    <div class="student-stat">
        <span class="student-stat-icon student-stat-icon--secondary"><i class="fas fa-percentage"></i></span>
        <div class="student-stat-text">
            <span class="student-stat-value">
                @if($stats['average_percent'] !== null){{ persian_digits($stats['average_percent']) }}٪@else—@endif
            </span>
            <span class="student-stat-label">میانگین درصد آزمون‌ها</span>
        </div>
    </div>
</div>

<div class="student-columns">
    <section class="panel student-panel">
        <header class="student-panel-head">
            <h2><i class="fas fa-calendar-week"></i> برنامه هفتگی</h2>
            <span class="count-badge">{{ persian_digits($stats['week_sessions']) }} مورد</span>
        </header>

        @if($weekItems->isNotEmpty())
            <ul class="week-list" data-stagger>
                @foreach($weekItems as $item)
                    <li class="week-item @if($item->is_completed) is-done @endif">
                        <span class="week-item-bar" style="--bar: {{ $item->color ?: 'var(--c-primary)' }}"></span>
                        <div class="week-item-body">
                            <h3>{{ $item->title }}</h3>
                            <p class="week-item-meta">
                                <span>
                                    <i class="fas fa-calendar-day"></i>
                                    <span class="fa-weekday" data-fa-weekday="{{ $item->start_datetime->format('Y-m-d\TH:i') }}Z"></span>
                                    <time class="fa-date" datetime="{{ $item->start_datetime->format('Y-m-d\TH:i') }}Z">{{ persian_digits($item->start_datetime->format('Y/m/d')) }}</time>
                                </span>
                                <span>
                                    <i class="far fa-clock"></i>
                                    {{ persian_digits($item->start_datetime->format('H:i')) }} – {{ persian_digits($item->end_datetime->format('H:i')) }}
                                </span>
                                @if($item->book_name)
                                    <span>
                                        <i class="fas fa-book"></i>
                                        {{ $item->book_name }}
                                        @if($item->page_count) — {{ persian_digits($item->page_count) }} صفحه @endif
                                    </span>
                                @endif
                            </p>
                        </div>
                        @if($item->is_completed)
                            <span class="week-item-done" title="تکمیل شد">
                                <i class="fas fa-check-circle"></i>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="empty-state empty-state--compact">
                <i class="far fa-calendar"></i>
                <h3>برنامه‌ای برای این هفته ثبت نشده</h3>
                <p>مشاور شما هنوز برنامه این هفته را تنظیم نکرده است.</p>
            </div>
        @endif
    </section>

    <div class="student-side">
        <section class="panel student-panel">
            <header class="student-panel-head">
                <h2><i class="fas fa-tasks"></i> آزمون‌های پیش‌رو</h2>
                @if($stats['upcoming_exams'] > 0)
                    <span class="count-badge">{{ persian_digits($stats['upcoming_exams']) }}</span>
                @endif
            </header>

            @if($upcomingExams->isNotEmpty())
                <ul class="mini-list">
                    @foreach($upcomingExams as $exam)
                        <li class="mini-item">
                            <span class="mini-item-icon"><i class="fas fa-file-alt"></i></span>
                            <div class="mini-item-main">
                                <span class="mini-item-title">{{ $exam->test->test_title }}</span>
                                <span class="mini-item-sub">
                                    {{ $exam->test->lesson ?: '—' }}
                                    @if($exam->scheduled_at)
                                        ·
                                        <time class="fa-date" datetime="{{ $exam->scheduled_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($exam->scheduled_at->format('Y/m/d')) }}</time>
                                        — {{ persian_digits($exam->scheduled_at->format('H:i')) }}
                                    @endif
                                </span>
                            </div>
                            <span class="exam-status exam-status--{{ $exam->status }}">{{ $statuses[$exam->status] ?? $exam->status }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="student-panel-note">آزمون زمان‌بندی‌شده‌ای ندارید.</p>
            @endif
        </section>

        <section class="panel student-panel">
            <header class="student-panel-head">
                <h2><i class="fas fa-chart-line"></i> آخرین نتایج</h2>
                @if($stats['completed_exams'] > 0)
                    <span class="count-badge">{{ persian_digits($stats['completed_exams']) }} آزمون</span>
                @endif
            </header>

            @if($recentResults->isNotEmpty())
                <ul class="mini-list">
                    @foreach($recentResults as $exam)
                        <li class="mini-item">
                            <span class="mini-item-icon mini-item-icon--success"><i class="fas fa-check-double"></i></span>
                            <div class="mini-item-main">
                                <span class="mini-item-title">{{ $exam->test->test_title }}</span>
                                <span class="mini-item-sub">
                                    {{ $exam->test->lesson ?: '—' }}
                                    @if($exam->latestAttempt->completed_at)
                                        ·
                                        <time class="fa-date" datetime="{{ $exam->latestAttempt->completed_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($exam->latestAttempt->completed_at->format('Y/m/d')) }}</time>
                                    @endif
                                </span>
                            </div>
                            <span class="mini-score">
                                {{ persian_digits((float) $exam->latestAttempt->score_raw) }}
                                <small>از {{ persian_digits((float) $exam->test->total_marks) }}</small>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="student-panel-note">هنوز نتیجه‌ای ثبت نشده است.</p>
            @endif
        </section>

        <section class="panel student-panel student-soon">
            <span class="student-soon-icon"><i class="fas fa-chart-pie"></i></span>
            <div>
                <h3>کارنامه</h3>
                <p>کارنامه تحصیلی شما به‌زودی در همین پنل در دسترس خواهد بود.</p>
            </div>
        </section>
    </div>
</div>

@vite(['resources/js/features/student-dashboard.js'])
@endsection
