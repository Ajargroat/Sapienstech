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

@if($dealBanner)
    <a href="{{ route('student.deals.index') }}"
       class="mt-4 block rounded-2xl border p-4 no-underline"
       style="border-color:color-mix(in srgb,var(--c-warning,#FBBF24) 45%,var(--c-border));background:color-mix(in srgb,var(--c-warning,#FBBF24) 10%,transparent)">
        <b><i class="fas fa-file-invoice"></i> دورهٔ مشاورهٔ شما رو به پایان است</b>
        <span class="block text-sm" style="color:var(--c-muted)">
            @if($dealBanner->daysLeft() < 0) سرسید گذشته — @else {{ persian_digits($dealBanner->daysLeft()) }} روز مانده — @endif
            لطفاً ادامهٔ مسیر یا انصراف خود را اعلام کنید.
        </span>
    </a>
@endif

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

<div class="student-side">
    <section class="panel student-panel">
        <header class="student-panel-head">
            <h2><i class="fas fa-calendar-week"></i> برنامه هفتگی</h2>
            <span class="count-badge">{{ persian_digits($stats['week_sessions']) }} مورد</span>
        </header>

        @if($weekItems->isNotEmpty())
            {{-- Saturday-anchored Persian week, one day-header row of colored
                 boxes per day. Boxes are styled like the consultant calendar's
                 event cards (16% tint of the category color + solid accent
                 border) but are strictly read-only. --}}
            @foreach($weekItems->groupBy(fn ($item) => $item->start_datetime->toDateString()) as $dayItems)
                @php $dayStart = $dayItems->first()->start_datetime; @endphp
                <div class="sched-day">
                    <h3 class="sched-day-head">
                        <span class="fa-weekday" data-fa-weekday="{{ $dayStart->format('Y-m-d\TH:i') }}Z"></span>
                        <time class="fa-date" datetime="{{ $dayStart->format('Y-m-d\TH:i') }}Z">{{ persian_digits($dayStart->format('Y/m/d')) }}</time>
                    </h3>
                    <div class="sched-boxes">
                        @foreach($dayItems as $item)
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
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            <div class="empty-state empty-state--compact">
                <i class="far fa-calendar"></i>
                <h3>برنامه‌ای برای این هفته ثبت نشده</h3>
                <p>مشاور شما هنوز برنامه این هفته را تنظیم نکرده است.</p>
            </div>
        @endif
    </section>

    <section class="panel student-panel">
        <header class="student-panel-head">
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
                    <article class="exam-card">
                        <div class="exam-card-icon exam-card-icon--{{ $type }}">
                            <span class="exam-status exam-status--{{ $exam->status }}">{{ $statuses[$exam->status] ?? $exam->status }}</span>
                            {!! $typeSvg[$type] !!}
                            <span class="exam-card-icon-label">{{ $typeLabels[$type] }}</span>
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
                            </ul>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <p class="student-panel-note">آزمونی برای ماه آینده زمان‌بندی نشده است.</p>
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
        <div class="exam-field">
            <label>وضعیت انجام</label>
            <span class="sched-modal-value @if($item->is_completed) is-done @endif">
                @if($item->is_completed)<i class="fas fa-check-circle"></i> تکمیل‌شده@else <i class="far fa-circle"></i> انجام‌نشده @endif
            </span>
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

<dialog id="schedule-detail-modal" class="exam-modal" aria-labelledby="sched-modal-title">
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
