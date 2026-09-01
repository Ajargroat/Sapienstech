{{--
    PROTOTYPE: consultant exam-checking page for a single student.

    Rendered by App\Http\Controllers\Consultant\StudentExamController::index()
    through the `consultant.student.exams` route. Exam rows are sample data
    in the controller (no Exam model yet); this page validates the routing /
    view wiring and previews what the real feature needs: summary stats,
    status filter tabs, search, a results table and a create-exam dialog.
--}}
@extends('layouts.consultant')

@section('content')
<div id="exams-app">
    <style>
        #exams-app .exam-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 32px; }
        #exams-app .exam-stat { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-card); padding: 18px 20px; display: flex; flex-direction: column; gap: 4px; }
        #exams-app .exam-stat-icon { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; margin-bottom: 10px; }
        #exams-app .exam-stat-icon--total { color: var(--c-primary); background: color-mix(in srgb, var(--c-primary) 12%, transparent); }
        #exams-app .exam-stat-icon--average { color: var(--c-secondary); background: color-mix(in srgb, var(--c-secondary) 12%, transparent); }
        #exams-app .exam-stat-icon--completed { color: var(--c-success); background: color-mix(in srgb, var(--c-success) 12%, transparent); }
        #exams-app .exam-stat-icon--scheduled { color: var(--c-info); background: color-mix(in srgb, var(--c-info) 12%, transparent); }
        #exams-app .exam-stat-value { font-size: 28px; font-weight: 800; color: var(--c-text); line-height: 1.1; }
        #exams-app .exam-stat-label { font-size: 13px; color: var(--c-muted); }

        #exams-app .exam-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        #exams-app .exam-tab { display: inline-flex; align-items: center; gap: 7px; padding: 6px 14px; border-radius: 999px; border: 1px solid var(--c-border); background: var(--c-surface); color: var(--c-muted); font-size: 13px; font-weight: 600; transition: all var(--animation-duration) ease; }
        #exams-app .exam-tab span { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: var(--c-surface-alt); }
        #exams-app .exam-tab:hover { border-color: var(--c-border-strong); color: var(--c-text); }
        #exams-app .exam-tab.is-active { background: var(--c-primary); border-color: var(--c-primary); color: #fff; }
        #exams-app .exam-tab.is-active span { background: rgba(255, 255, 255, .2); color: #fff; }

        #exams-app .exam-table-wrap { overflow-x: auto; }
        #exams-app .exam-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        #exams-app .exam-table th { text-align: right; font-size: 12px; font-weight: 700; color: var(--c-muted); padding: 14px 18px; border-bottom: 1px solid var(--c-border); }
        #exams-app .exam-table td { padding: 14px 18px; border-bottom: 1px solid var(--c-border); font-size: 14px; color: var(--c-text); vertical-align: middle; }
        #exams-app .exam-table tbody tr:last-child td { border-bottom: none; }
        #exams-app .exam-table tbody tr { transition: background var(--animation-duration) ease; }
        #exams-app .exam-table tbody tr:hover { background: var(--c-surface-alt); }

        #exams-app .exam-title-cell { display: flex; flex-direction: column; gap: 3px; }
        #exams-app .exam-title { font-weight: 700; }
        #exams-app .exam-duration { font-size: 12px; color: var(--c-muted); }

        #exams-app .exam-date { display: block; font-weight: 600; }
        #exams-app .exam-time { display: block; font-size: 12px; color: var(--c-muted); }

        #exams-app .exam-score small { color: var(--c-muted); font-weight: 400; }
        #exams-app .exam-score--empty { color: var(--c-muted); }

        #exams-app .exam-percent { display: flex; align-items: center; gap: 10px; }
        #exams-app .exam-percent strong { font-size: 13px; min-width: 34px; }
        #exams-app .exam-progress { width: 90px; height: 8px; border-radius: 999px; background: var(--c-surface-alt); border: 1px solid var(--c-border); overflow: hidden; }
        #exams-app .exam-progress span { display: block; height: 100%; border-radius: 999px; }

        #exams-app .exam-status { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        #exams-app .exam-status--completed { color: var(--c-success); background: color-mix(in srgb, var(--c-success) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-success) 25%, transparent); }
        #exams-app .exam-status--grading { color: var(--c-info); background: color-mix(in srgb, var(--c-info) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-info) 25%, transparent); }
        #exams-app .exam-status--scheduled { color: var(--c-primary); background: color-mix(in srgb, var(--c-primary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-primary) 25%, transparent); }
        #exams-app .exam-status--missed { color: var(--c-danger); background: color-mix(in srgb, var(--c-danger) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-danger) 25%, transparent); }

        #exams-app .exam-action { font-size: 13px; font-weight: 600; color: var(--c-primary); white-space: nowrap; }
        #exams-app .exam-action:hover { color: var(--c-primary-hover); text-decoration: underline; }

        #exams-app .exam-modal { border: 1px solid var(--c-border); border-radius: var(--radius-card); background: var(--c-surface); color: var(--c-text); padding: 0; width: min(560px, calc(100% - 32px)); }
        #exams-app .exam-modal::backdrop { background: rgba(0, 0, 0, .55); backdrop-filter: blur(4px); }
        #exams-app .exam-modal-head { padding: 20px 24px 0; }
        #exams-app .exam-modal-head h3 { margin: 0; font-size: 19px; font-weight: 800; }
        #exams-app .exam-modal-head p { margin: 6px 0 0; font-size: 12.5px; color: var(--c-muted); }
        #exams-app .exam-modal-body { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; padding: 20px 24px; }
        #exams-app .exam-field { display: flex; flex-direction: column; gap: 6px; }
        #exams-app .exam-field--wide { grid-column: 1 / -1; }
        #exams-app .exam-field label { font-size: 12.5px; font-weight: 600; color: var(--c-muted); }
        #exams-app .exam-field input,
        #exams-app .exam-field select { background: var(--c-surface-alt); border: 1px solid var(--c-border); border-radius: 10px; padding: 9px 12px; color: var(--c-text); font: inherit; font-size: 14px; }
        #exams-app .exam-field input:focus,
        #exams-app .exam-field select:focus { outline: none; border-color: var(--c-primary); }
        #exams-app .exam-modal-foot { display: flex; gap: 10px; padding: 0 24px 20px; }
        #exams-app .exam-modal-foot .primary-button[disabled] { opacity: .5; cursor: not-allowed; }
        @media (max-width: 680px) {
            #exams-app .exam-modal-body { grid-template-columns: 1fr; }
        }
    </style>

    <div class="dashboard-header">
        <div>
            <h1>آزمون‌های {{ $student->name }}</h1>
            <p>
                <a href="{{ route('consultant.student.profile', $student) }}" class="secondary-button">
                    <i class="fas fa-arrow-right"></i> بازگشت به پروفایل {{ $student->name }}
                </a>
            </p>
        </div>
        <button type="button" class="primary-button" onclick="document.getElementById('create-exam-modal').showModal()">
            <i class="fas fa-plus"></i> ساخت آزمون جدید
        </button>
    </div>

    <section class="exam-stats">
        <div class="exam-stat">
            <span class="exam-stat-icon exam-stat-icon--total"><i class="fas fa-tasks"></i></span>
            <span class="exam-stat-value">{{ persian_digits($stats['total']) }}</span>
            <span class="exam-stat-label">کل آزمون‌ها</span>
        </div>
        <div class="exam-stat">
            <span class="exam-stat-icon exam-stat-icon--average"><i class="fas fa-chart-line"></i></span>
            <span class="exam-stat-value">@if($stats['average_percent'] === null)—@else{{ persian_digits($stats['average_percent']) }}٪@endif</span>
            <span class="exam-stat-label">میانگین درصد نمرات</span>
        </div>
        <div class="exam-stat">
            <span class="exam-stat-icon exam-stat-icon--completed"><i class="fas fa-check-circle"></i></span>
            <span class="exam-stat-value">{{ persian_digits($stats['completed']) }}</span>
            <span class="exam-stat-label">آزمون انجام‌شده</span>
        </div>
        <div class="exam-stat">
            <span class="exam-stat-icon exam-stat-icon--scheduled"><i class="fas fa-clock"></i></span>
            <span class="exam-stat-value">{{ persian_digits($stats['scheduled']) }}</span>
            <span class="exam-stat-label">آزمون پیش‌رو</span>
        </div>
    </section>

    <div class="panel-heading">
        <div class="panel-heading-title">
            <h2>فهرست آزمون‌ها</h2>
            <span class="count-badge">{{ persian_digits($exams->count()) }} آزمون</span>
        </div>

        <div class="panel-heading-actions">
            <div class="search-reveal @if($search !== '') is-open @endif">
                <form method="GET" action="{{ route('consultant.student.exams', $student) }}" class="search-reveal-form">
                    <button type="submit" class="search-reveal-toggle" aria-label="جستجو">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="text" name="search" value="{{ $search }}" placeholder="جستجوی آزمون یا درس…" class="search-reveal-input" aria-label="جستجوی آزمون">
                    @if($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    @if($search !== '')
                        <a href="{{ route('consultant.student.exams', $student) }}" class="search-reveal-clear" aria-label="پاک کردن جستجو">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <nav class="exam-tabs" aria-label="فیلتر وضعیت آزمون‌ها">
        <a href="{{ route('consultant.student.exams', ['student' => $student, 'search' => $search !== '' ? $search : null]) }}"
           class="exam-tab @if($status === '') is-active @endif">
            همه <span>{{ persian_digits($stats['total']) }}</span>
        </a>
        @foreach($statuses as $key => $label)
            <a href="{{ route('consultant.student.exams', ['student' => $student, 'status' => $key, 'search' => $search !== '' ? $search : null]) }}"
               class="exam-tab @if($status === $key) is-active @endif">
                {{ $label }} <span>{{ persian_digits($statusCounts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    @if($exams->isNotEmpty())
        <div class="panel exam-table-wrap">
            <table class="exam-table">
                <thead>
                    <tr>
                        <th>آزمون</th>
                        <th>درس</th>
                        <th>تاریخ</th>
                        <th>نمره</th>
                        <th>درصد</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($exams as $exam)
                        @php
                            $percent = $exam['score'] !== null ? round($exam['score'] / $exam['total'] * 100) : null;
                            $barColor = match (true) {
                                $percent === null => 'var(--c-muted)',
                                $percent >= 80 => 'var(--c-success)',
                                $percent >= 50 => 'var(--c-info)',
                                default => 'var(--c-danger)',
                            };
                        @endphp
                        <tr>
                            <td class="exam-title-cell">
                                <span class="exam-title">{{ $exam['title'] }}</span>
                                <span class="exam-duration"><i class="far fa-hourglass"></i> {{ persian_digits($exam['duration']) }} دقیقه</span>
                            </td>
                            <td><span class="student-tag student-tag-major">{{ $exam['lesson'] }}</span></td>
                            <td>
                                <span class="exam-date">{{ $exam['date'] }}</span>
                                <span class="exam-time">{{ $exam['time'] }}</span>
                            </td>
                            <td>
                                @if($exam['score'] !== null)
                                    <span class="exam-score">{{ persian_digits($exam['score']) }} <small>از {{ persian_digits($exam['total']) }}</small></span>
                                @else
                                    <span class="exam-score exam-score--empty">—</span>
                                @endif
                            </td>
                            <td>
                                @if($percent !== null)
                                    <div class="exam-percent">
                                        <div class="exam-progress" role="img" aria-label="درصد نمره {{ persian_digits($percent) }}">
                                            <span style="width: {{ $percent }}%; background: {{ $barColor }};"></span>
                                        </div>
                                        <strong>{{ persian_digits($percent) }}٪</strong>
                                    </div>
                                @else
                                    <span class="exam-score exam-score--empty">—</span>
                                @endif
                            </td>
                            <td><span class="exam-status exam-status--{{ $exam['status'] }}">{{ $statuses[$exam['status']] }}</span></td>
                            <td>
                                @if($exam['status'] === 'completed')
                                    <a href="#" class="exam-action">مشاهده کارنامه</a>
                                @elseif($exam['status'] === 'scheduled')
                                    <a href="#" class="exam-action">ویرایش آزمون</a>
                                @else
                                    <span class="exam-score exam-score--empty">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            @if($search !== '' || $status !== '')
                <h3>آزمونی با این مشخصات پیدا نشد</h3>
                <p>فیلترها یا عبارت جستجو را تغییر دهید.</p>
            @else
                <h3>هنوز آزمونی ثبت نشده است</h3>
                <p>با دکمهٔ «ساخت آزمون جدید» اولین آزمون {{ $student->name }} را ایجاد کنید.</p>
            @endif
        </div>
    @endif

    <dialog id="create-exam-modal" class="exam-modal">
        <form method="dialog" class="exam-modal-form">
            <div class="exam-modal-head">
                <h3><i class="fas fa-plus-circle"></i> ساخت آزمون جدید</h3>
                <p>نمونهٔ اولیه — ثبت واقعی پس از افزودن مدل Exam فعال می‌شود.</p>
            </div>
            <div class="exam-modal-body">
                <div class="exam-field exam-field--wide">
                    <label for="exam-title">عنوان آزمون</label>
                    <input id="exam-title" type="text" placeholder="مثلاً: آزمون جامع ریاضیات — نوبت دوم">
                </div>
                <div class="exam-field">
                    <label for="exam-lesson">درس</label>
                    <select id="exam-lesson">
                        <option>ریاضی</option>
                        <option>فیزیک</option>
                        <option>شیمی</option>
                        <option>زیست‌شناسی</option>
                        <option>ادبیات فارسی</option>
                        <option>عربی</option>
                        <option>دین و زندگی</option>
                        <option>زبان انگلیسی</option>
                    </select>
                </div>
                <div class="exam-field">
                    <label for="exam-type">نوع آزمون</label>
                    <select id="exam-type">
                        <option>پیش‌رفت تحصیلی</option>
                        <option>آزمایشی/شبیه‌سازی کنکور</option>
                        <option>کوئیز آنلاین</option>
                        <option>تک‌درس</option>
                    </select>
                </div>
                <div class="exam-field">
                    <label for="exam-date">تاریخ برگزاری</label>
                    <input id="exam-date" type="text" placeholder="۱۴۰۴/۰۷/۱۰" dir="ltr">
                </div>
                <div class="exam-field">
                    <label for="exam-time">ساعت شروع</label>
                    <input id="exam-time" type="text" placeholder="۰۸:۰۰" dir="ltr">
                </div>
                <div class="exam-field">
                    <label for="exam-duration">مدت (دقیقه)</label>
                    <input id="exam-duration" type="number" min="10" step="5" value="90">
                </div>
                <div class="exam-field">
                    <label for="exam-total">نمره از</label>
                    <input id="exam-total" type="number" min="1" value="20">
                </div>
                <div class="exam-field exam-field--wide">
                    <label for="exam-source">منبع سوالات</label>
                    <select id="exam-source">
                        <option>انتخاب از بانک سوال…</option>
                        <option>بارگذاری فایل سوالات</option>
                    </select>
                </div>
            </div>
            <div class="exam-modal-foot">
                <button type="submit" class="secondary-button">انصراف</button>
                <button type="button" class="primary-button" disabled title="نمونهٔ اولیه">ایجاد آزمون</button>
            </div>
        </form>
    </dialog>
</div>
@endsection
