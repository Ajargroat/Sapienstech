{{--
    Student portal dashboard — the student's own workspace.

    Rendered by App\Http\Controllers\Student\StudentDashboardController::index()
    through the `student.dashboard` route. Mirrors the consultant dashboard's
    design language (layouts.student reuses the consultant shell), showing the
    student's weekly schedule, next month's upcoming exams and recent results.

    The schedule renders as read-only colored boxes (the same visual language
    as the consultant calendar's event cards, without the grid): clicking a box
    opens a details dialog populated from a per-item <template> and wired by
    resources/js/features/student-dashboard.js. Exam cards reuse the
    consultant exams workspace (.exam-grid/.exam-card), and finished exams link
    to the student-side result review page (student.exams.result). The old
    "کارنامه" teaser panel moved to its own student.report-card page/tab.

    Jalali dates render server-side as a Gregorian fallback inside
    <time class="fa-date"> and are rewritten by
    resources/js/features/student-dashboard.js (same pattern as the
    consultant exams page). Styling lives in resources/css/app.css under
    "STUDENT PORTAL DASHBOARD".
--}}
@extends('layouts.student')

@section('content')
{{-- Studio ids: top-level regions of the student dashboard, addressable from
     the canvas under the template-owned `public.student.*` root (see
     StudioStyles::TEMPLATE_ROOTS). --}}
<template data-studio-section-marker="welcome"></template>
<div class="dash-page">
<header class="dash-hero" data-studio-path="public.student.welcome">
    <div class="dash-hero-main">
        <span class="dash-eyebrow">پنل دانش‌آموز</span>
        <h1 class="dash-title">خوش آمدید، {{ $student->name }}</h1>
        <p class="dash-subtitle">برنامهٔ این هفته، آزمون‌های پیش‌رو و نتیجه‌های اخیرت را یک‌جا ببین.</p>
        <div class="dash-hero-meta">
            <span class="student-avatar-lg">{{ mb_substr($student->name, 0, 1) }}</span>
            <div>
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
            <div class="student-today">
                <span class="student-today-label">امروز</span>
                <time class="fa-date" datetime="{{ now()->format('Y-m-d\TH:i') }}Z">{{ persian_digits(now()->format('Y/m/d')) }}</time>
            </div>
        </div>
        <div class="dash-actions">
            @if(site('features.report_cards', false) && \Illuminate\Support\Facades\Route::has('student.report-card'))
                <a href="{{ route('student.report-card') }}" class="primary-button">
                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                    کارنامهٔ من
                </a>
            @elseif(site('features.student_timetable', false) && \Illuminate\Support\Facades\Route::has('student.timetable'))
                <a href="{{ route('student.timetable') }}" class="primary-button">
                    <i class="fas fa-calendar-day" aria-hidden="true"></i>
                    برنامهٔ کلاسی
                </a>
            @endif
        </div>
    </div>

    @include('partials.dashboard.ring', [
        'value' => $weekPercent,
        'label' => 'پیشرفت این هفته',
        'caption' => $stats['week_sessions'] > 0
            ? persian_digits($stats['week_done']).' از '.persian_digits($stats['week_sessions']).' جلسه'
            : 'هنوز جلسه‌ای ثبت نشده',
    ])
</header>

@if($dealBanner)
    <a href="{{ route('student.deals.index') }}" class="dash-alert">
        <span class="dash-alert-title"><i class="fas fa-file-invoice" aria-hidden="true"></i> دورهٔ مشاورهٔ شما رو به پایان است</span>
        <span class="dash-alert-text">
            @if($dealBanner->daysLeft() < 0) سرسید گذشته — @else {{ persian_digits($dealBanner->daysLeft()) }} روز مانده — @endif
            لطفاً ادامهٔ مسیر یا انصراف خود را اعلام کنید.
        </span>
    </a>
@endif

<template data-studio-section-marker="stats"></template>
<section class="dash-stats" data-stagger data-studio-path="public.student.stats">
    @include('partials.dashboard.stat', [
        'tone' => 'primary',
        'icon' => 'fa-calendar-day',
        'label' => 'جلسه‌های این هفته',
        'value' => $stats['week_sessions'],
        'meta' => persian_digits($stats['week_done']).' جلسه تکمیل‌شده',
        'percent' => $weekPercent,
    ])
    @include('partials.dashboard.stat', [
        'tone' => 'success',
        'icon' => 'fa-check-circle',
        'label' => 'جلسه‌های تکمیل‌شده',
        'value' => $stats['week_done'],
        'meta' => $stats['week_sessions'] > 0
            ? persian_digits((int) round(($stats['week_done'] / $stats['week_sessions']) * 100)).'٪ از برنامهٔ هفته'
            : 'برنامه‌ای برای این هفته ثبت نشده',
    ])
    @include('partials.dashboard.stat', [
        'tone' => 'info',
        'icon' => 'fa-tasks',
        'label' => 'آزمون‌های پیش‌رو',
        'value' => $stats['upcoming_exams'],
        'meta' => 'تا ۳۰ روز آینده',
    ])
    @include('partials.dashboard.stat', [
        'tone' => 'secondary',
        'icon' => 'fa-chart-line',
        'label' => 'میانگین درصد آزمون‌ها',
        'value' => $stats['average_percent'],
        'meta' => $stats['average_percent'] !== null
            ? 'بر پایهٔ آزمون‌های تکمیل‌شده'
            : 'هنوز آزمونی تکمیل نشده',
    ])
</section>

<div class="dash-layout">
    <div class="dash-main">
    <section class="panel dash-panel" data-studio-path="public.student.schedule">
        <header class="dash-panel-head">
            <h2><i class="fas fa-calendar-week"></i> برنامه هفتگی</h2>
            <span class="count-badge">{{ persian_digits($stats['week_sessions']) }} مورد</span>
        </header>

        @if($weekItems->isNotEmpty())
            {{-- Saturday-anchored Persian week as a seven-day board. Boxes keep
                 the consultant calendar's colored-card language but stay
                 strictly read-only. --}}
            <div class="dash-week">
                @foreach($weekDays as $day)
                    <div class="dash-week-day @if($day['date']->isToday()) is-today @endif">
                        <div class="dash-week-head">
                            <span class="dash-week-weekday fa-weekday" data-fa-weekday="{{ $day['date']->format('Y-m-d\TH:i') }}Z">{{ $day['weekday'] }}</span>
                            <time class="dash-week-date fa-date" datetime="{{ $day['date']->format('Y-m-d\TH:i') }}Z">{{ persian_digits($day['date']->format('Y/m/d')) }}</time>
                        </div>
                        <div class="dash-week-items">
                            @forelse($day['items'] as $item)
                                @php
                                    $isPersonal = $item->item_type === 'student_personal_block';
                                    $accent = $item->color ?: 'var(--c-primary)';
                                @endphp
                                <button
                                    type="button"
                                    class="sched-box @if($isPersonal) sched-box--personal @endif @if($item->is_completed) sched-box--done @endif"
                                    style="--accent: {{ $accent }}"
                                    data-sched-item="sched-tpl-{{ $item->id }}"
                                    data-sched-title="{{ $item->title }}"
                                    data-sched-id="{{ $item->id }}"
                                    data-sched-completed="{{ $item->is_completed ? '1' : '0' }}"
                                    aria-haspopup="dialog"
                                >
                                    <span class="sched-box-time">
                                        <span>{{ persian_digits($item->start_datetime->format('H:i')) }} – {{ persian_digits($item->end_datetime->format('H:i')) }}</span>
                                        @if($item->is_completed)
                                            <i class="fas fa-check-circle" title="تکمیل شد"></i>
                                        @endif
                                    </span>
                                    <span class="sched-box-title">{{ $item->title }}</span>
                                    @if($item->book_name || $item->page_count || $item->test_count)
                                        <span class="sched-box-tags">
                                            @if($item->book_name)
                                                <span><i class="fas fa-book"></i> {{ $item->book_name }}</span>
                                            @endif
                                            @if($item->page_count)
                                                <span><i class="fas fa-file-alt"></i> {{ persian_digits($item->page_count) }} صفحه</span>
                                            @endif
                                            @if($item->test_count)
                                                <span><i class="fas fa-check-square"></i> {{ persian_digits($item->test_count) }} تست</span>
                                            @endif
                                        </span>
                                    @endif
                                </button>
                            @empty
                                <p class="dash-week-empty">بدون برنامه</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state empty-state--compact">
                <i class="far fa-calendar"></i>
                <h3>برنامه‌ای برای این هفته ثبت نشده</h3>
                <p>مشاور شما هنوز برنامه این هفته را تنظیم نکرده است.</p>
            </div>
        @endif
    </section>

    @if(site('features.student_schedule', false) && \Illuminate\Support\Facades\Route::has('student.schedule.notes'))
        {{-- Read-only mirror of the consultant's «توضیحات هفته»; filled by
             student-dashboard.js from the portal's own notes endpoint and
             revealed only once the week's data has actually rendered. --}}
        <section class="panel dash-panel" id="student-week-notes" data-react-island="student-week-notes" aria-labelledby="student-week-notes-title" data-url-notes="{{ route('student.schedule.notes') }}" data-week-start="{{ $weekStart->toDateString() }}" hidden>
            <header class="dash-panel-head">
                <h2 id="student-week-notes-title"><i class="fas fa-note-sticky"></i> توضیحات هفته</h2>
            </header>
            <div id="student-week-notes-list"></div>
            <p class="student-panel-note" id="student-week-notes-empty" hidden>برای این هفته توضیحی ثبت نشده</p>
        </section>
    @endif

    <section class="panel dash-panel" data-studio-path="public.student.exams">
        <header class="dash-panel-head">
            <h2><i class="fas fa-tasks"></i> آزمون‌های پیش‌رو</h2>
            @if($upcomingExams->isNotEmpty())
                <span class="count-badge">{{ persian_digits($upcomingExams->count()) }} آزمون</span>
            @endif
        </header>

        @if($upcomingExams->isNotEmpty())
            {{-- Same card anatomy as the consultant exams workspace
                 (.exam-grid/.exam-card), minus the run button. --}}
            @php
                $typeSvg = [
                    'quiz' => '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M13 34l2-6.5L31 11.5l5.5 5.5L20.5 33 13 34z"/>
                        <path d="M27.5 15.5l5 5"/>
                        <path d="M12 40h24"/>
                    </svg>',
                    'comprehensive' => '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="11" y="7" width="26" height="34" rx="6" fill="currentColor" fill-opacity=".12"/>
                        <rect x="11" y="7" width="26" height="34" rx="6"/>
                        <path d="M18 17h12M18 24h12M18 31h7"/>
                    </svg>',
                ];
                $typeLabels = ['quiz' => 'تمرینی', 'comprehensive' => 'آزمون'];
            @endphp
            <div class="exam-grid" data-stagger>
                @foreach($upcomingExams as $exam)
                    @php
                        $test = $exam->test;
                        $type = in_array($test->exam_type, $quizTypes, true) ? 'quiz' : 'comprehensive';
                    @endphp
                    @php
                        $cover = $test->cover_image_path ? tenant_asset($test->cover_image_path) : null;
                        $live = $exam->windowOpen();
                    @endphp
                    <article class="exam-card" data-exam-card
                             data-status-open="{{ $statuses['in_progress'] ?? 'in_progress' }}">
                        <div class="exam-card-icon exam-card-icon--{{ $type }} @if($cover) has-cover @endif">
                            <span class="exam-status exam-status--{{ $exam->effectiveStatus() }}"
                                  data-exam-status>{{ $statuses[$exam->effectiveStatus()] ?? $exam->effectiveStatus() }}</span>
                            @if($cover)
                                <img src="{{ $cover }}" class="exam-card-cover" alt="" loading="lazy">
                            @else
                                {!! $typeSvg[$type] !!}
                                <span class="exam-card-icon-label">{{ $typeLabels[$type] }}</span>
                            @endif
                        </div>
                        <div class="exam-card-body">
                            <h3 class="exam-card-title">{{ $test->test_title }}</h3>
                            <ul class="exam-card-facts">
                                <li><i class="fas fa-layer-group"></i>{{ $test->lesson ?: '—' }}</li>
                                <li><i class="fas fa-circle-question"></i>{{ persian_digits((int) ($test->questions_count ?: 0)) }} سوال</li>
                                @if($test->time_limit_minutes)
                                    <li><i class="fas fa-hourglass-half"></i>{{ persian_digits((int) $test->time_limit_minutes) }} دقیقه</li>
                                @endif
                                <li>
                                    <i class="fas fa-calendar-day"></i>
                                    <time class="fa-date" datetime="{{ $exam->scheduled_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($exam->scheduled_at->format('Y/m/d')) }}</time>
                                    — {{ persian_digits($exam->scheduled_at->format('H:i')) }}
                                </li>
                                @if($exam->ends_at)
                                    <li>
                                        <i class="fas fa-calendar-check"></i>
                                        پایان:
                                        <time class="fa-date" datetime="{{ $exam->ends_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($exam->ends_at->format('Y/m/d')) }}</time>
                                        — {{ persian_digits($exam->ends_at->format('H:i')) }}
                                    </li>
                                @endif
                            </ul>
                            {{-- Always rendered, hidden until its start instant:
                                 student-dashboard.js flips it live so the card
                                 needs no reload when the window opens. --}}
                            {{-- Timing attribute: compared against the real
                                 browser clock, so it must be a true instant
                                 (with the +03:30 offset), never a wall-clock
                                 string suffixed with a fake `Z`. --}}
                            <a href="{{ route('student.exams.show', $exam) }}" class="exam-run-btn"
                               data-start-at="{{ $exam->scheduled_at->toIso8601String() }}"
                               @unless($live) hidden @endunless>
                                <i class="fas fa-play"></i> شروع آزمون
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <p class="student-panel-note">آزمونی برای ماه آینده زمان‌بندی نشده است.</p>
        @endif
    </section>
</div>

<aside class="dash-rail">
    <section class="dash-feature" data-studio-path="public.student.next_exam">
        <span class="dash-feature-icon"><i class="fas fa-hourglass-half" aria-hidden="true"></i></span>
        <span class="dash-feature-eyebrow">آزمون بعدی</span>
        @if($nextExam)
            <h3 class="dash-feature-title">{{ $nextExam->test->test_title }}</h3>
            <p class="dash-feature-meta">
                {{ $nextExam->test->lesson ?: 'آزمون' }}
                ·
                <time class="fa-date" datetime="{{ $nextExam->scheduled_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($nextExam->scheduled_at->format('Y/m/d')) }}</time>
                — {{ persian_digits($nextExam->scheduled_at->format('H:i')) }}
            </p>
            @if($nextExam->windowOpen())
                <a href="{{ route('student.exams.show', $nextExam) }}" class="dash-feature-cta">
                    شروع آزمون
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </a>
            @else
                <p class="dash-feature-meta">پنجرهٔ آزمون در زمان مقرر باز می‌شود.</p>
            @endif
        @else
            <h3 class="dash-feature-title">فعلاً آزمونی زمان‌بندی نشده</h3>
            <p class="dash-feature-meta">آزمون‌های تازه در همین صفحه نمایش داده می‌شوند.</p>
        @endif
    </section>

    <section class="panel dash-panel" data-studio-path="public.student.results">
        <header class="dash-panel-head">
            <h2><i class="fas fa-chart-line"></i> آخرین نتایج</h2>
            @if($stats['completed_exams'] > 0)
                <span class="count-badge">{{ persian_digits($stats['completed_exams']) }} آزمون</span>
            @endif
        </header>

        @if($recentResults->isNotEmpty())
            <ul class="mini-list">
                @foreach($recentResults as $exam)
                    <li>
                        <a class="mini-item mini-item--link" href="{{ route('student.exams.result', $exam) }}">
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
                            <span class="mini-item-open" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="student-panel-note">هنوز نتیجه‌ای ثبت نشده است.</p>
        @endif
    </section>

    @php
        $quickActions = [];

        if (site('features.student_materials', false) && \Illuminate\Support\Facades\Route::has('student.lessons.index')) {
            $quickActions[] = [
                'label' => 'درسنامه‌ها',
                'href' => route('student.lessons.index'),
                'icon' => 'fa-book-open-reader',
                'tone' => 'info',
            ];
        }

        if (site('features.student_assignments', false) && \Illuminate\Support\Facades\Route::has('student.assignments.index')) {
            $quickActions[] = [
                'label' => 'تکالیف',
                'href' => route('student.assignments.index'),
                'icon' => 'fa-clipboard-list',
                'tone' => 'warning',
            ];
        }

        if (site('features.student_timetable', false) && \Illuminate\Support\Facades\Route::has('student.timetable')) {
            $quickActions[] = [
                'label' => 'برنامهٔ کلاسی',
                'href' => route('student.timetable'),
                'icon' => 'fa-calendar-day',
                'tone' => 'primary',
            ];
        }

        if (site('features.report_cards', false) && \Illuminate\Support\Facades\Route::has('student.report-card')) {
            $quickActions[] = [
                'label' => 'کارنامه',
                'href' => route('student.report-card'),
                'icon' => 'fa-chart-line',
                'tone' => 'secondary',
            ];
        }

        if (site('features.student_chat', false) && \Illuminate\Support\Facades\Route::has('student.direct-chat.page')) {
            $quickActions[] = [
                'label' => 'گفتگو',
                'href' => route('student.direct-chat.page'),
                'icon' => 'fa-paper-plane',
                'tone' => 'success',
            ];
        }
    @endphp

    @if($quickActions)
        <section class="panel dash-panel" data-studio-path="public.student.quick_actions">
            <header class="dash-panel-head">
                <h2><i class="fas fa-paper-plane" aria-hidden="true"></i> دسترسی سریع</h2>
            </header>
            @include('partials.dashboard.quick-actions', ['actions' => $quickActions])
        </section>
    @endif
</aside>
</div>
</div>

{{-- Read-only schedule item details: cloned into the dialog by
     student-dashboard.js when a box is clicked. --}}
@foreach($weekItems as $item)
    <template id="sched-tpl-{{ $item->id }}">
        <div class="exam-field">
            <label>روز و تاریخ</label>
            <span class="sched-modal-value">
                <span class="fa-weekday" data-fa-weekday="{{ $item->start_datetime->format('Y-m-d\TH:i') }}Z"></span>
                <time class="fa-date" datetime="{{ $item->start_datetime->format('Y-m-d\TH:i') }}Z">{{ persian_digits($item->start_datetime->format('Y/m/d')) }}</time>
            </span>
        </div>
        <div class="exam-field">
            <label>ساعت</label>
            <span class="sched-modal-value">{{ persian_digits($item->start_datetime->format('H:i')) }} – {{ persian_digits($item->end_datetime->format('H:i')) }}</span>
        </div>
        @if($item->book_name)
            <div class="exam-field">
                <label>کتاب</label>
                <span class="sched-modal-value">{{ $item->book_name }}</span>
            </div>
        @endif
        @if($item->page_count)
            <div class="exam-field">
                <label>تعداد صفحه</label>
                <span class="sched-modal-value">{{ persian_digits($item->page_count) }}</span>
            </div>
        @endif
        @if($item->test_count)
            <div class="exam-field">
                <label>تعداد تست</label>
                <span class="sched-modal-value">{{ persian_digits($item->test_count) }}</span>
            </div>
        @endif
        {{-- The student is the one who knows whether it got done, so the status
             line is a control, not a read-out. --}}
        <div class="exam-field">
            <label>وضعیت انجام</label>
            <button
                type="button"
                class="sched-modal-value sched-done-toggle @if($item->is_completed) is-done @endif"
                data-student-done-toggle
                data-item-id="{{ $item->id }}"
                aria-pressed="{{ $item->is_completed ? 'true' : 'false' }}"
            >
                <i class="fas @if($item->is_completed) fa-check-circle @else fa-circle @endif" aria-hidden="true"></i>
                <span class="sched-done-label">@if($item->is_completed)تکمیل‌شده@else انجام‌نشده @endif</span>
            </button>
            <p class="sched-done-status" data-student-done-status hidden></p>
        </div>
        {{-- The student's own thread on the block; the consultant reads it
             back on their side. --}}
        <div class="exam-field exam-field--wide">
            <label>یادداشت من</label>
            <div class="comment-composer" data-student-comment-composer data-item-id="{{ $item->id }}">
                <div class="comment-composer-row">
                    <textarea
                        class="sched-comment-input"
                        rows="2"
                        maxlength="2000"
                        data-student-comment-text
                    ></textarea>
                    <button type="button" class="sched-comment-submit shrink-0" data-student-comment-submit>
                        <i class="fas fa-paper-plane w-4 h-4" aria-hidden="true"></i>
                        <span>ثبت</span>
                    </button>
                </div>
                <p class="comment-composer-status" data-student-comment-status hidden></p>
            </div>
            <div class="comment-thread" data-student-comment-thread data-react-island="student-comments">
                <p class="comment-item comment-item--empty" data-student-comment-empty>هنوز یادداشتی ثبت نکرده‌اید.</p>
            </div>
        </div>
        @if($item->link_url)
            <div class="exam-field exam-field--wide">
                <label>لینک محتوا / آزمون</label>
                <a class="sched-modal-link" href="{{ $item->link_url }}" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt"></i> {{ $item->link_url }}
                </a>
            </div>
        @endif
        <div class="exam-field exam-field--wide">
            <label>{{ $item->item_type === 'student_personal_block' ? 'توضیحات' : 'توضیحات مشاور' }}</label>
            <p class="sched-modal-desc">{{ $item->description ?: '—' }}</p>
        </div>
    </template>
@endforeach

{{-- The student portal's own endpoints. Server-rendered so the script never
     hardcodes a route and a tenant prefix is never guessed. --}}
<dialog id="schedule-detail-modal" class="exam-modal"
    data-csrf="{{ csrf_token() }}"
    data-url-completion-template="{{ url('/student/schedule/items/__ITEM__/completion') }}"
    data-url-comments-template="{{ url('/student/schedule/items/__ITEM__/comments') }}"
    aria-labelledby="sched-modal-title">
    <div class="exam-modal-head sched-modal-head">
        <h3 id="sched-modal-title"><i class="fas fa-calendar-check"></i> <span></span></h3>
        <button type="button" class="sched-modal-close" aria-label="بستن" data-sched-close>
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="sched-modal-body"></div>
</dialog>

@vite(['resources/js/features/student-dashboard.js'])
@endsection
