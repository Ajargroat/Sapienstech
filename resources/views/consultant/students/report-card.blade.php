{{--
    PROTOTYPE: consultant report-card (کارنامه) page for a single student.

    Rendered by App\Http\Controllers\Consultant\StudentReportCardController::index()
    through the `consultant.student.report-card` route. Report-card rows are sample
    data in the controller (no ReportCard model yet); this page validates the
    routing / view wiring and previews what the real feature needs: term tabs,
    per-lesson grades, descriptive evaluation, trend vs the previous term, search
    and a send-to-parents dialog.
--}}
@extends('layouts.consultant')

@section('content')
<div id="report-card-app">
    <style>
        #report-card-app .rc-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 32px; }
        #report-card-app .rc-stat { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-card); padding: 18px 20px; display: flex; flex-direction: column; gap: 4px; }
        #report-card-app .rc-stat-icon { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; margin-bottom: 10px; }
        #report-card-app .rc-stat-icon--average { color: var(--c-primary); background: color-mix(in srgb, var(--c-primary) 12%, transparent); }
        #report-card-app .rc-stat-icon--lessons { color: var(--c-secondary); background: color-mix(in srgb, var(--c-secondary) 12%, transparent); }
        #report-card-app .rc-stat-icon--absences { color: var(--c-info); background: color-mix(in srgb, var(--c-info) 12%, transparent); }
        #report-card-app .rc-stat-icon--attention { color: var(--c-danger); background: color-mix(in srgb, var(--c-danger) 12%, transparent); }
        #report-card-app .rc-stat-value { font-size: 28px; font-weight: 800; color: var(--c-text); line-height: 1.1; }
        #report-card-app .rc-stat-label { font-size: 13px; color: var(--c-muted); }

        #report-card-app .rc-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        #report-card-app .rc-tab { display: inline-flex; align-items: center; gap: 7px; padding: 6px 14px; border-radius: 999px; border: 1px solid var(--c-border); background: var(--c-surface); color: var(--c-muted); font-size: 13px; font-weight: 600; transition: all var(--animation-duration) ease; }
        #report-card-app .rc-tab span { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: var(--c-surface-alt); }
        #report-card-app .rc-tab:hover { border-color: var(--c-border-strong); color: var(--c-text); }
        #report-card-app .rc-tab.is-active { background: var(--c-primary); border-color: var(--c-primary); color: #fff; }
        #report-card-app .rc-tab.is-active span { background: rgba(255, 255, 255, .2); color: #fff; }

        #report-card-app .rc-table-wrap { overflow-x: auto; }
        #report-card-app .rc-table { width: 100%; border-collapse: collapse; min-width: 860px; }
        #report-card-app .rc-table th { text-align: right; font-size: 12px; font-weight: 700; color: var(--c-muted); padding: 14px 18px; border-bottom: 1px solid var(--c-border); }
        #report-card-app .rc-table td { padding: 14px 18px; border-bottom: 1px solid var(--c-border); font-size: 14px; color: var(--c-text); vertical-align: middle; }
        #report-card-app .rc-table tbody tr:last-child td { border-bottom: none; }
        #report-card-app .rc-table tbody tr { transition: background var(--animation-duration) ease; }
        #report-card-app .rc-table tbody tr:hover { background: var(--c-surface-alt); }

        #report-card-app .rc-lesson-cell { display: flex; flex-direction: column; gap: 3px; }
        #report-card-app .rc-lesson { font-weight: 700; }
        #report-card-app .rc-absences { font-size: 12px; color: var(--c-danger); }

        #report-card-app .rc-score { display: flex; align-items: baseline; gap: 8px; white-space: nowrap; }
        #report-card-app .rc-score strong { font-size: 15px; }
        #report-card-app .rc-score small { color: var(--c-muted); font-weight: 400; }
        #report-card-app .rc-trend { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 700; }
        #report-card-app .rc-trend--up { color: var(--c-success); }
        #report-card-app .rc-trend--down { color: var(--c-danger); }
        #report-card-app .rc-trend--flat { color: var(--c-muted); }

        #report-card-app .rc-percent { display: flex; align-items: center; gap: 10px; }
        #report-card-app .rc-percent strong { font-size: 13px; min-width: 34px; }
        #report-card-app .rc-progress { width: 90px; height: 8px; border-radius: 999px; background: var(--c-surface-alt); border: 1px solid var(--c-border); overflow: hidden; }
        #report-card-app .rc-progress span { display: block; height: 100%; border-radius: 999px; }

        #report-card-app .rc-grade { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        #report-card-app .rc-grade--excellent { color: var(--c-success); background: color-mix(in srgb, var(--c-success) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-success) 25%, transparent); }
        #report-card-app .rc-grade--good { color: var(--c-info); background: color-mix(in srgb, var(--c-info) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-info) 25%, transparent); }
        #report-card-app .rc-grade--ok { color: var(--c-primary); background: color-mix(in srgb, var(--c-primary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-primary) 25%, transparent); }
        #report-card-app .rc-grade--effort { color: var(--c-danger); background: color-mix(in srgb, var(--c-danger) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-danger) 25%, transparent); }

        #report-card-app .rc-action { font-size: 13px; font-weight: 600; color: var(--c-primary); white-space: nowrap; }
        #report-card-app .rc-action:hover { color: var(--c-primary-hover); text-decoration: underline; }

        #report-card-app .rc-modal { border: 1px solid var(--c-border); border-radius: var(--radius-card); background: var(--c-surface); color: var(--c-text); padding: 0; width: min(560px, calc(100% - 32px)); }
        #report-card-app .rc-modal::backdrop { background: rgba(0, 0, 0, .55); backdrop-filter: blur(4px); }
        #report-card-app .rc-modal-head { padding: 20px 24px 0; }
        #report-card-app .rc-modal-head h3 { margin: 0; font-size: 19px; font-weight: 800; }
        #report-card-app .rc-modal-head p { margin: 6px 0 0; font-size: 12.5px; color: var(--c-muted); }
        #report-card-app .rc-modal-body { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; padding: 20px 24px; }
        #report-card-app .rc-field { display: flex; flex-direction: column; gap: 6px; }
        #report-card-app .rc-field--wide { grid-column: 1 / -1; }
        #report-card-app .rc-field label { font-size: 12.5px; font-weight: 600; color: var(--c-muted); }
        #report-card-app .rc-field select,
        #report-card-app .rc-field textarea { background: var(--c-surface-alt); border: 1px solid var(--c-border); border-radius: 10px; padding: 9px 12px; color: var(--c-text); font: inherit; font-size: 14px; }
        #report-card-app .rc-field select:focus,
        #report-card-app .rc-field textarea:focus { outline: none; border-color: var(--c-primary); }
        #report-card-app .rc-modal-foot { display: flex; gap: 10px; padding: 0 24px 20px; }
        #report-card-app .rc-modal-foot .primary-button[disabled] { opacity: .5; cursor: not-allowed; }
        @media (max-width: 680px) {
            #report-card-app .rc-modal-body { grid-template-columns: 1fr; }
        }
    </style>

    <div class="dashboard-header">
        <div>
            <h1>کارنامه {{ $student->name }}</h1>
            <p>
                <a href="{{ route('consultant.student.profile', $student) }}" class="secondary-button">
                    <i class="fas fa-arrow-right"></i> بازگشت به پروفایل {{ $student->name }}
                </a>
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="secondary-button" onclick="window.print()">
                <i class="fas fa-print"></i> چاپ کارنامه
            </button>
            <button type="button" class="primary-button" onclick="document.getElementById('send-report-card-modal').showModal()">
                <i class="fas fa-paper-plane"></i> ارسال کارنامه به اولیا
            </button>
        </div>
    </div>

    <section class="rc-stats">
        <div class="rc-stat">
            <span class="rc-stat-icon rc-stat-icon--average"><i class="fas fa-chart-line"></i></span>
            <span class="rc-stat-value">{{ persian_digits($stats['average']) }}</span>
            <span class="rc-stat-label">میانگین نمرات ترم</span>
        </div>
        <div class="rc-stat">
            <span class="rc-stat-icon rc-stat-icon--lessons"><i class="fas fa-book-open"></i></span>
            <span class="rc-stat-value">{{ persian_digits($stats['lessons']) }}</span>
            <span class="rc-stat-label">تعداد دروس</span>
        </div>
        <div class="rc-stat">
            <span class="rc-stat-icon rc-stat-icon--absences"><i class="fas fa-user-clock"></i></span>
            <span class="rc-stat-value">{{ persian_digits($stats['absences']) }}</span>
            <span class="rc-stat-label">غیبت‌های ترم</span>
        </div>
        <div class="rc-stat">
            <span class="rc-stat-icon rc-stat-icon--attention"><i class="fas fa-exclamation-triangle"></i></span>
            <span class="rc-stat-value">{{ persian_digits($stats['needs_attention']) }}</span>
            <span class="rc-stat-label">درس نیازمند توجه</span>
        </div>
    </section>

    <div class="panel-heading">
        <div class="panel-heading-title">
            <h2>دروس {{ $terms[$term] }}</h2>
            <span class="count-badge">{{ persian_digits($lessons->count()) }} درس</span>
        </div>

        <div class="panel-heading-actions">
            <div class="search-reveal @if($search !== '') is-open @endif">
                <form method="GET" action="{{ route('consultant.student.report-card', $student) }}" class="search-reveal-form">
                    <button type="submit" class="search-reveal-toggle" aria-label="جستجو">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="text" name="search" value="{{ $search }}" placeholder="جستجوی درس…" class="search-reveal-input" aria-label="جستجوی درس">
                    <input type="hidden" name="term" value="{{ $term }}">
                    @if($search !== '')
                        <a href="{{ route('consultant.student.report-card', ['student' => $student, 'term' => $term]) }}" class="search-reveal-clear" aria-label="پاک کردن جستجو">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <nav class="rc-tabs" aria-label="انتخاب ترم کارنامه">
        @foreach($terms as $key => $label)
            <a href="{{ route('consultant.student.report-card', ['student' => $student, 'term' => $key, 'search' => $search !== '' ? $search : null]) }}"
               class="rc-tab @if($term === $key) is-active @endif">
                {{ $label }} <span>{{ persian_digits($termCounts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    @if($lessons->isNotEmpty())
        <div class="panel rc-table-wrap">
            <table class="rc-table">
                <thead>
                    <tr>
                        <th>درس</th>
                        <th>مستمر</th>
                        <th>میان‌ترم</th>
                        <th>پایان‌ترم</th>
                        <th>نمره نهایی</th>
                        <th>درصد</th>
                        <th>ارزیابی توصیفی</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lessons as $lesson)
                        <tr>
                            <td>
                                <div class="rc-lesson-cell">
                                    <span class="rc-lesson">{{ $lesson['lesson'] }}</span>
                                    @if($lesson['absences'] > 0)
                                        <span class="rc-absences"><i class="fas fa-user-slash"></i> {{ persian_digits($lesson['absences']) }} غیبت</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ persian_digits($lesson['continuous']) }}</td>
                            <td>{{ persian_digits($lesson['midterm']) }}</td>
                            <td>{{ persian_digits($lesson['final']) }}</td>
                            <td>
                                <div class="rc-score">
                                    <strong>{{ persian_digits($lesson['total']) }}</strong> <small>از ۲۰</small>
                                    @if($lesson['trend'] !== null)
                                        @if($lesson['trend'] > 0)
                                            <span class="rc-trend rc-trend--up"><i class="fas fa-arrow-up"></i> {{ persian_digits(abs($lesson['trend'])) }}</span>
                                        @elseif($lesson['trend'] < 0)
                                            <span class="rc-trend rc-trend--down"><i class="fas fa-arrow-down"></i> {{ persian_digits(abs($lesson['trend'])) }}</span>
                                        @else
                                            <span class="rc-trend rc-trend--flat"><i class="fas fa-minus"></i> بدون تغییر</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="rc-percent">
                                    <div class="rc-progress">
                                        <span style="width: {{ $lesson['percent'] }}%; background: {{ $lesson['percent'] >= 85 ? 'var(--c-success)' : ($lesson['percent'] >= 60 ? 'var(--c-info)' : 'var(--c-danger)') }};"></span>
                                    </div>
                                    <strong>{{ persian_digits($lesson['percent']) }}٪</strong>
                                </div>
                            </td>
                            <td>
                                <span class="rc-grade rc-grade--{{ $lesson['grade'] }}">{{ $grades[$lesson['grade']] }}</span>
                            </td>
                            <td>
                                <a href="#" class="rc-action">تحلیل نمرات <i class="fas fa-chevron-left"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="panel empty-state">
            @if($search !== '')
                <i class="fas fa-search"></i>
                <h3>نتیجه‌ای پیدا نشد</h3>
                <p>درسی با «{{ $search }}» در {{ $terms[$term] }} وجود ندارد.</p>
            @else
                <i class="fas fa-file-invoice"></i>
                <h3>کارنامه‌ای موجود نیست</h3>
                <p>برای این دانش‌آموز هنوز کارنامه‌ای ثبت نشده است.</p>
            @endif
        </div>
    @endif

    <dialog id="send-report-card-modal" class="rc-modal">
        <form>
            <div class="rc-modal-head">
                <h3><i class="fas fa-paper-plane"></i> ارسال کارنامه به اولیا</h3>
                <p>نمونه اولیه — ارسال واقعی پس از راه‌اندازی مدل کارنامه فعال می‌شود.</p>
            </div>
            <div class="rc-modal-body">
                <div class="rc-field">
                    <label for="rc-term">ترم</label>
                    <select id="rc-term" name="term">
                        @foreach($terms as $key => $label)
                            <option value="{{ $key }}" @selected($key === $term)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="rc-field">
                    <label for="rc-recipient">گیرنده</label>
                    <select id="rc-recipient" name="recipient">
                        <option>مادر</option>
                        <option>پدر</option>
                        <option>پدر و مادر</option>
                    </select>
                </div>
                <div class="rc-field rc-field--wide">
                    <label for="rc-note">یادداشت مشاور</label>
                    <textarea id="rc-note" name="note" rows="4" placeholder="مثلاً: پیشرفت ریاضی خوب بوده اما فیزیک نیاز به برنامه جبرانی دارد…"></textarea>
                </div>
            </div>
            <div class="rc-modal-foot">
                <button type="button" class="secondary-button" onclick="document.getElementById('send-report-card-modal').close()">بستن</button>
                <button type="submit" class="primary-button" disabled>ارسال کارنامه</button>
            </div>
        </form>
    </dialog>
</div>
@endsection
