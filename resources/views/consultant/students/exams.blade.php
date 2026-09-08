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
    // Practice vs exam glyphs: minimal, single-color (currentColor) so the card's
    // pale tenant plate tints them automatically.
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
<div id="exams-app">
    <div class="panel-heading">
        <div class="panel-heading-title">
            <h2>فهرست آزمون‌ها</h2>
            <span class="count-badge" data-router-region="results">{{ persian_digits($total) }} آزمون</span>
        </div>

        <div class="panel-heading-actions exam-toolbar">
            <div class="search-reveal @if($search !== '') is-open @endif" data-router-region="results">
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
                    {{-- Always rendered (hidden when empty) so the router's region
                         pairing stays stable across partial swaps. --}}
                    <span class="filter-count" data-router-region="results" @if($status === '') hidden @endif>{{ persian_digits($statusCounts[$status] ?? 0) }}</span>
                </label>
                <div class="filter-popover" data-router-region="results">
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

            <button type="button" id="open-create-exam" class="exam-tool" aria-label="ساخت آزمون جدید" title="ساخت آزمون جدید">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>

    @if(session('success'))
        <p class="exam-flash"><i class="fas fa-check-circle"></i> {{ session('success') }}</p>
    @endif

    <div data-router-region="results">
    @if($exams->isNotEmpty())
        <div class="exam-grid" id="exam-grid" data-stagger>
            @foreach($exams as $exam)
                <article class="exam-card">
                    <div class="exam-card-icon exam-card-icon--{{ $exam['type'] }}">
                        <span class="exam-status exam-status--{{ $exam['status'] }}">{{ $statuses[$exam['status']] ?? $exam['status'] }}</span>
                        {!! $typeSvg[$exam['type']] !!}
                        <span class="exam-card-icon-label">{{ $typeLabels[$exam['type']] }}</span>
                    </div>
                    <div class="exam-card-body">
                        <h3 class="exam-card-title">{{ $exam['title'] }}</h3>
                        @if($exam['percent'] !== null)
                            <p class="exam-card-percent">{{ persian_digits($exam['percent']) }}<small>٪</small></p>
                        @endif
                        <ul class="exam-card-facts">
                            <li><i class="fas fa-layer-group"></i>{{ $exam['lesson'] }}</li>
                            <li><i class="fas fa-circle-question"></i>{{ persian_digits($exam['questions']) }} سوال</li>
                            @if($exam['date_iso'])
                                <li>
                                    <i class="fas fa-calendar-day"></i>
                                    <time class="fa-date" datetime="{{ $exam['date_iso'] }}">{{ $exam['date_text'] }}</time>
                                </li>
                            @endif
                        </ul>
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
    </div>

    <div id="exam-form-errors" hidden data-has-errors="{{ $errors->any() ? 1 : 0 }}"></div>

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
