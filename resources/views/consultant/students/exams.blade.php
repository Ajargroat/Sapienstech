{{--
    Consultant exam workspace for one student.

    Rendered by App\Http\Controllers\Consultant\StudentExamController::index()
    through the `consultant.student.exams` route.

    Cards come from student_assigned_quizzes + tests + student_test_attempts.
    All styling lives in resources/css/app.css (search for "EXAMS WORKSPACE"),
    behavior in resources/js/features/consultant-exams.js.
--}}
@extends('layouts.consultant')

@section('content')
@php
    // Quiz vs comprehensive badges: same geometry, same 2px stroke language.
    $typeSvg = [
        'quiz' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="10" y="7" width="28" height="34" rx="6" fill="currentColor" opacity=".12"/>
            <rect x="10" y="7" width="28" height="34" rx="6" stroke="currentColor" stroke-width="2"/>
            <path d="M17 17h14M17 24h14M17 31h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <circle cx="34" cy="34" r="7.5" fill="var(--c-surface)" stroke="currentColor" stroke-width="2"/>
            <path d="M30.8 34l2.3 2.3 4.1-4.6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>',
        'comprehensive' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="8" y="15" width="32" height="27" rx="6" fill="currentColor" opacity=".12"/>
            <rect x="8" y="15" width="32" height="27" rx="6" stroke="currentColor" stroke-width="2"/>
            <path d="M15 25h18M15 32h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M24 5l14 6.2-14 6.2-14-6.2L24 5z" fill="currentColor"/>
            <path d="M35.5 14v6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>',
    ];
    $typeLabels = ['quiz' => 'کوئیز', 'comprehensive' => 'آزمون جامع'];
@endphp
<div id="exams-app">
    <div class="panel-heading">
        <div class="panel-heading-title">
            <h2>فهرست آزمون‌ها</h2>
            <span class="count-badge">{{ persian_digits($total) }} آزمون</span>
        </div>

        <div class="panel-heading-actions exam-toolbar">
            <div class="search-reveal @if($search !== '') is-open @endif">
                <form method="GET" action="{{ route('consultant.student.exams', $student) }}" class="search-reveal-form">
                    <button type="submit" class="search-reveal-toggle" aria-label="جستجو">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="text" name="search" value="{{ $search }}" placeholder="جستجوی آزمون، درس یا توضیح…" class="search-reveal-input" aria-label="جستجوی آزمون">
                    @if($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                    @if($search !== '')
                        <a href="{{ route('consultant.student.exams', $student) }}" class="search-reveal-clear" aria-label="پاک کردن جستجو">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </form>
            </div>

            <div class="filter-wrap">
                <input type="checkbox" id="filter-toggle" class="filter-toggle-input" @checked($status !== '')>
                <label for="filter-toggle" class="filter-toggle-btn" aria-label="فیلتر وضعیت آزمون">
                    <i class="fas fa-sliders-h"></i>
                    @if($status !== '')<span class="filter-count">{{ persian_digits($statusCounts[$status] ?? 0) }}</span>@endif
                </label>
                <div class="filter-popover">
                    <p class="filter-popover-title">وضعیت آزمون</p>
                    <a href="{{ route('consultant.student.exams', ['student' => $student, 'search' => $search !== '' ? $search : null]) }}"
                       class="exam-filter-option @if($status === '') is-active @endif">
                        همه <span>{{ persian_digits($total) }}</span>
                    </a>
                    @foreach($statuses as $key => $label)
                        <a href="{{ route('consultant.student.exams', ['student' => $student, 'status' => $key, 'search' => $search !== '' ? $search : null]) }}"
                           class="exam-filter-option @if($status === $key) is-active @endif">
                            {{ $label }} <span>{{ persian_digits($statusCounts[$key] ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <button type="button" id="view-toggle" class="exam-tool" data-view="grid"
                    aria-label="تغییر بین نمای کارتی و فهرستی" title="نمایش فهرستی">
                <span class="exam-tool-stack">
                    <svg class="exam-tool-icon icon-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3.5" y="3.5" width="7" height="7" rx="2.5"/>
                        <rect x="13.5" y="3.5" width="7" height="7" rx="2.5"/>
                        <rect x="3.5" y="13.5" width="7" height="7" rx="2.5"/>
                        <rect x="13.5" y="13.5" width="7" height="7" rx="2.5"/>
                    </svg>
                    <svg class="exam-tool-icon icon-list" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 6h11M9 12h11M9 18h11"/>
                        <circle cx="4.6" cy="6" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="4.6" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="4.6" cy="18" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
            </button>

            <button type="button" id="open-report-modal" class="exam-tool" aria-label="کارنامه و فعالیت کلی" title="کارنامه">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 2.5h8l4.5 4.5v14.5H6z"/>
                    <path d="M14 2.5V7h4.5"/>
                    <path d="M9.2 18v-3.4M12.2 18v-6.6M15.2 18v-4.6"/>
                </svg>
            </button>

            <button type="button" id="open-create-exam" class="exam-tool" aria-label="ساخت آزمون جدید" title="ساخت آزمون جدید">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>

    @if(session('success'))
        <p class="exam-flash"><i class="fas fa-check-circle"></i> {{ session('success') }}</p>
    @endif

    @if($exams->isNotEmpty())
        <div class="exam-grid" id="exam-grid" data-stagger>
            @foreach($exams as $exam)
                <article class="exam-card">
                    <div class="exam-card-head">
                        <span class="exam-type exam-type--{{ $exam['type'] }}">
                            {!! $typeSvg[$exam['type']] !!}
                            <span class="exam-type-label">{{ $typeLabels[$exam['type']] }}</span>
                        </span>
                        <span class="exam-status exam-status--{{ $exam['status'] }}">{{ $statuses[$exam['status']] ?? $exam['status'] }}</span>
                    </div>
                    <div class="exam-card-main">
                        <h3 class="exam-card-title">{{ $exam['title'] }}</h3>
                        <p class="exam-card-desc">{{ $exam['description'] }}</p>
                        <ul class="exam-card-meta">
                            <li><i class="fas fa-book-open"></i>{{ $exam['lesson'] }}</li>
                            <li><i class="fas fa-question-circle"></i>{{ persian_digits($exam['questions']) }} سوال</li>
                            @if($exam['duration'] > 0)
                                <li><i class="far fa-clock"></i>{{ persian_digits($exam['duration']) }} دقیقه</li>
                            @endif
                        </ul>
                    </div>
                    <div class="exam-card-foot">
                        @if($exam['date_iso'])
                            <span>
                                <i class="fas fa-calendar-day"></i>
                                <time class="fa-date" datetime="{{ $exam['date_iso'] }}">{{ $exam['date_text'] }}</time>
                                @if($exam['time']) — {{ $exam['time'] }}@endif
                            </span>
                        @endif
                        @if($exam['score'] !== null)
                            <span class="exam-card-score">{{ persian_digits($exam['score']) }} <small>از {{ persian_digits($exam['total']) }}</small></span>
                        @endif
                        @if($exam['can_run'])
                            <a href="{{ route('consultant.student.exams.run', [$student, $exam['id']]) }}"
                               class="exam-run-btn" title="مشاهده و برگزاری آزمون">
                                <i class="fas fa-play"></i> برگزاری
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            @if($search !== '' || $status !== '')
                <h3>آزمونی با این مشخصات پیدا نشد</h3>
                <p>عبارت جستجو یا فیلتر وضعیت را تغییر دهید.</p>
            @else
                <h3>هنوز آزمونی برای این دانش‌آموز ثبت نشده است</h3>
                <p>با دکمهٔ «＋» در نوار ابزار، اولین آزمون را بسازید.</p>
            @endif
        </div>
    @endif

    <div id="exam-form-errors" hidden data-has-errors="{{ $errors->any() ? 1 : 0 }}"></div>

    <dialog id="report-modal" class="report-modal">
        <div class="report-modal-head">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 2.5h8l4.5 4.5v14.5H6z"/>
                    <path d="M14 2.5V7h4.5"/>
                    <path d="M9.2 18v-3.4M12.2 18v-6.6M15.2 18v-4.6"/>
                </svg>
                کارنامهٔ {{ $student->name }}
            </h3>
            <button type="button" class="exam-tool report-modal-close" aria-label="بستن کارنامه">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <iframe title="کارنامه" data-src="{{ route('consultant.student.report-card', $student) }}"></iframe>
    </dialog>

    <dialog id="create-exam-modal" class="exam-modal exam-modal--builder">
        <form method="POST" id="create-exam-form" action="{{ route('consultant.student.exams.store', $student) }}">
            @csrf
            <div id="exam-questions-inputs" data-selected='@json(old("questions", []))'></div>

            <div class="exam-modal-head">
                <h3><i class="fas fa-plus-circle"></i> ساخت آزمون جدید</h3>
                <p>مشخصات را وارد کنید، سپس سوالات را از بانک انتخاب کنید.</p>
                <div class="builder-steps" role="tablist">
                    <button type="button" class="bstep is-active" data-step="1">
                        <span>۱</span> مشخصات آزمون
                    </button>
                    <button type="button" class="bstep" data-step="2">
                        <span>۲</span> انتخاب سوالات
                        <em id="step2-count"></em>
                    </button>
                </div>
            </div>

            <div class="exam-modal-body bstep-pane" data-pane="1">
                {{-- the existing fields, unchanged, plus a source toggle at the top: --}}
                <div class="exam-field exam-field--wide">
                    <label>منبع سوالات</label>
                    <div class="source-toggle">
                        <label><input type="radio" name="question_source" value="bank" checked> انتخاب از بانک سوالات</label>
                        <label><input type="radio" name="question_source" value="manual"> فقط تعداد (بدون سوال)</label>
                    </div>
                </div>
                <div class="exam-field exam-field--wide">
                    <label for="exam-title">عنوان آزمون</label>
                    <input id="exam-title" name="title" type="text" required maxlength="255"
                           value="{{ old('title') }}" placeholder="مثلاً: آزمون جامع ریاضیات — نوبت دوم">
                    @error('title')<span class="exam-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="exam-field">
                    <label for="exam-type">نوع آزمون</label>
                    <select id="exam-type" name="exam_type">
                        <option value="quiz" @selected(old('exam_type') === 'quiz')>کوئیز کوتاه</option>
                        <option value="comprehensive" @selected(old('exam_type', 'comprehensive') === 'comprehensive')>آزمون جامع</option>
                    </select>
                </div>
                <div class="exam-field">
                    <label for="exam-lesson">درس / موضوع</label>
                    <input id="exam-lesson" name="lesson" type="text" required maxlength="100"
                           list="exam-lesson-options" value="{{ old('lesson') }}" placeholder="ریاضی">
                    <datalist id="exam-lesson-options">
                        @foreach(['ریاضی','فیزیک','شیمی','زیست‌شناسی','ادبیات فارسی','عربی','دین و زندگی','زبان انگلیسی','علوم تجربی + ریاضی'] as $lesson)
                            <option value="{{ $lesson }}"></option>
                        @endforeach
                    </datalist>
                    @error('lesson')<span class="exam-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="exam-field">
                    <label for="exam-questions">تعداد سوال</label>
                    <input id="exam-questions" name="question_count" type="number" min="1" max="500"
                           value="{{ old('question_count', 20) }}">
                </div>
                <div class="exam-field">
                    <label for="exam-total">نمره از</label>
                    <input id="exam-total" name="total_marks" type="number" min="1" step="0.5"
                           value="{{ old('total_marks', 20) }}">
                </div>
                <div class="exam-field">
                    <label for="exam-date">تاریخ برگزاری</label>
                    <input id="exam-date" name="date_jalali" type="text" dir="ltr" inputmode="numeric"
                           maxlength="10" placeholder="۱۴۰۵/۰۶/۲۰" value="{{ old('date_jalali') }}" autocomplete="off">
                    <input id="exam-date-g" name="date" type="hidden" value="{{ old('date') }}">
                    <p class="exam-field-error" id="exam-date-error" hidden></p>
                    @error('date')<span class="exam-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="exam-field">
                    <label for="exam-time">ساعت شروع</label>
                    <input id="exam-time" name="time" type="text" dir="ltr" inputmode="numeric"
                           maxlength="5" placeholder="۰۸:۳۰" value="{{ old('time') }}">
                </div>
                <div class="exam-field">
                    <label for="exam-duration">مدت (دقیقه)</label>
                    <input id="exam-duration" name="time_limit_minutes" type="number" min="5" step="5"
                           value="{{ old('time_limit_minutes', 90) }}">
                </div>
                <div class="exam-field exam-field--wide">
                    <label for="exam-desc">توضیح مشاور</label>
                    <textarea id="exam-desc" name="description" rows="2" maxlength="2000"
                              placeholder="موضوعات پوشش‌داده‌شده و نکات مهم…">{{ old('description') }}</textarea>
                </div>
                @error('questions')<span class="exam-field-error exam-field--wide">{{ $message }}</span>@enderror
            </div>

            <div class="exam-modal-body bstep-pane picker-pane" data-pane="2" hidden>
                <div class="picker-toolbar">
                    <input type="search" id="picker-search" class="picker-search"
                           placeholder="جستجو در متن سوالات…" value="{{ $search ?? '' }}">
                    <select id="picker-difficulty" class="picker-difficulty">
                        <option value="">همه سطوح</option>
                        <option value="Easy">آسان</option>
                        <option value="Medium">متوسط</option>
                        <option value="Hard">سخت</option>
                    </select>
                </div>
                <div id="picker-root"
                     data-url="{{ route('consultant.student.exams.questions', $student) }}"
                     class="picker-root"><p class="picker-empty">در حال بارگذاری…</p></div>
                <div class="picker-tray-wrap">
                    <p class="picker-tray-title">ترتیب سوالات در آزمون <small>(با ↑↓ جابه‌جا کنید)</small></p>
                    <ol id="picker-tray" class="picker-tray"></ol>
                </div>
            </div>

            <div class="exam-modal-foot">
                <button type="button" id="builder-back" class="secondary-button" hidden>مرحله قبل</button>
                <button type="button" id="builder-next" class="primary-button">مرحله بعد: انتخاب سوالات</button>
                <button type="submit" id="builder-submit" class="primary-button" hidden>ثبت آزمون</button>
                <button type="button" class="secondary-button exam-modal-cancel">انصراف</button>
            </div>
        </form>
    </dialog>
</div>
@vite(['resources/js/features/consultant-exams.js'])
@endsection
