@extends('layouts.consultant')

@php
    // The carousel always has its four pages (the track width is CSS-bound to
    // 4); a page whose feature is switched off says so instead of filtering.
    // old('filter_open') is flashed back by a failed assignment POST, so the
    // popover reopens exactly on the page that produced the errors.
    $panelKeys = ['general', 'exams', 'reports', 'schedule'];
    $initialPanel = array_search((string) old('filter_open', ''), $panelKeys, true);
    $initialPanel = $initialPanel === false ? 0 : $initialPanel;
    $openOnError = $errors->any() && $initialPanel > 0;
@endphp

@section('content')
@if(session('success'))
    <div class="settings-flash settings-flash--success" role="status">
        <i class="fas fa-check-circle" aria-hidden="true"></i> {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="settings-flash settings-flash--error" role="alert">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i> {{ $errors->first() }}
    </div>
@endif

<div class="panel-heading">
    <div class="panel-heading-title">
        <h2>{{ $labels['student_list'] }}</h2>
        <span class="count-badge" data-router-region="results">{{ persian_digits($students->total()) }} نفر</span>
    </div>

    <div class="panel-heading-actions">
        <div class="search-reveal @if($search !== '') is-open @endif" data-router-region="results">
            <form method="GET" action="{{ route('consultant.dashboard') }}" class="search-reveal-form" data-filter-sync>
                <button type="submit" class="search-reveal-toggle" aria-label="{{ $labels['search_button'] }}">
                    <i class="fas fa-search"></i>
                </button>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ $labels['search_placeholder'] }}"
                    class="search-reveal-input"
                    aria-label="{{ $labels['search_placeholder'] }}"
                >
                {{-- Search keeps the whole current filter stack (incl. domain
                     pages); app.js refreshes these from the live popover. --}}
                @include('consultant.partials._filter_hiddens', ['visible' => ['search']])
                @if($search !== '')
                    <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="search-reveal-clear" aria-label="{{ $labels['clear_search'] }}">
                        <i class="fas fa-times"></i>
                    </a>
                @endif
            </form>
        </div>

        <div class="filter-wrap">
            <input type="checkbox" id="filter-toggle" class="filter-toggle-input" @checked($activeFilterCount > 0 || $openOnError)>
            <label for="filter-toggle" class="filter-toggle-btn" aria-label="{{ $labels['filter_button'] }}">
                <i class="fas fa-sliders-h"></i>
                <span class="filter-toggle-label">{{ $labels['filter_button'] }}</span>
            </label>

            {{-- Always rendered (hidden when empty) so the router's region
                 pairing stays stable across partial swaps. --}}
            <span class="filter-count" data-router-region="results" @if($activeFilterCount === 0) hidden @endif>{{ persian_digits($activeFilterCount) }}</span>

            <div class="filter-popover" data-filter-popover>
                <div class="filter-carousel-head">
                    <button type="button" class="filter-page-btn filter-page-prev" data-filter-prev aria-label="گروه فیلتر قبلی">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <p class="filter-popover-title" data-filter-title>{{ $labels['filter_title'] }}</p>
                    <button type="button" class="filter-page-btn filter-page-next" data-filter-next aria-label="گروه فیلتر بعدی">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                </div>

                <div class="filter-carousel" data-filter-carousel data-filter-page="{{ $initialPanel }}">
                    <div class="filter-carousel-track">
                        {{-- 0. General student filters --}}
                        <div class="filter-page" data-filter-name="{{ $labels['filter_title'] }}">
                            <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-general" data-filter-form data-filter-sync>
                                <input type="hidden" name="search" value="{{ $search }}">

                                @include('consultant.partials._filter_chips', [
                                    'name' => 'grade',
                                    'idPrefix' => 'grade',
                                    'label' => $labels['filter_grade'],
                                    'options' => $gradeOptions->mapWithKeys(fn ($o) => [(string) $o => $o]),
                                    'selected' => $filters['grade'],
                                    'labels' => $labels,
                                ])
                                @include('consultant.partials._filter_chips', [
                                    'name' => 'gender',
                                    'idPrefix' => 'gender',
                                    'label' => $labels['filter_gender'],
                                    'options' => $genderOptions->mapWithKeys(fn ($o) => [(string) $o => $o]),
                                    'selected' => $filters['gender'],
                                    'labels' => $labels,
                                ])
                                @include('consultant.partials._filter_chips', [
                                    'name' => 'major',
                                    'idPrefix' => 'major',
                                    'label' => $labels['filter_major'],
                                    'options' => $majorOptions->mapWithKeys(fn ($o) => [(string) $o => $o]),
                                    'selected' => $filters['major'],
                                    'labels' => $labels,
                                ])

                                @php($sortOptions = [
                                    'name_asc' => $labels['sort_name_asc'],
                                    'name_desc' => $labels['sort_name_desc'],
                                    'newest' => $labels['sort_newest'],
                                    'oldest' => $labels['sort_oldest'],
                                ])
                                @include('consultant.partials._filter_chips', [
                                    'name' => 'sort',
                                    'idPrefix' => 'sort',
                                    'label' => $labels['filter_sort'],
                                    'options' => $sortOptions,
                                    'selected' => $filters['sort'],
                                    'labels' => $labels,
                                ])

                                {{-- Keep the other panels' live selections when applying here. --}}
                                @include('consultant.partials._filter_hiddens', ['visible' => ['grade', 'gender', 'major', 'sort']])
                            </form>
                        </div>

                        {{-- 1. Exams: filter students by their exam assignments,
                             plus the bulk exam assignment (was «آزمون گروهی»). --}}
                        <div class="filter-page" data-filter-name="{{ $labels['panel_exams'] }}">
                            @if(!$panelFlags['exams'])
                                <p class="filter-page-placeholder">{{ $labels['panel_disabled'] }}</p>
                            @else
                                <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-exams" data-filter-form data-filter-sync>
                                    @include('consultant.partials._filter_chips', [
                                        'name' => 'exam_status',
                                        'idPrefix' => 'exam-status',
                                        'label' => $labels['filter_exam_status'],
                                        'options' => $examStatuses,
                                        'selected' => $filters['exam_status'],
                                        'labels' => $labels,
                                    ])
                                    @include('consultant.partials._filter_chips', [
                                        'name' => 'exam_lesson',
                                        'idPrefix' => 'exam-lesson',
                                        'label' => $labels['filter_exam_lesson'],
                                        'options' => array_combine($examLessons, $examLessons),
                                        'selected' => $filters['exam_lesson'],
                                        'labels' => $labels,
                                    ])
                                    @include('consultant.partials._filter_chips', [
                                        'name' => 'exam_type',
                                        'idPrefix' => 'exam-type',
                                        'label' => $labels['filter_exam_type'],
                                        'options' => $examTypes,
                                        'selected' => $filters['exam_type'],
                                        'labels' => $labels,
                                    ])

                                    @include('consultant.partials._filter_hiddens', ['visible' => ['exam_status', 'exam_lesson', 'exam_type']])
                                </form>

                                @if($panelFlags['assign'])
                                    <form method="POST" action="{{ route('consultant.bulk.exams.store') }}" class="filter-assign" data-filter-sync>
                                        @csrf
                                        <h4 class="filter-assign-title">{{ $labels['assign_exam_title'] }}</h4>
                                        @if($tests->isEmpty())
                                            <p class="filter-assign-hint">{{ $labels['assign_exam_none'] }}</p>
                                        @else
                                            <p class="filter-assign-hint">{{ $labels['assign_exam_hint'] }}</p>
                                            <label class="filter-assign-field">
                                                <span>{{ $labels['assign_exam_test'] }}</span>
                                                <select name="test_id" class="filter-assign-input" required>
                                                    @foreach($tests as $test)
                                                        <option value="{{ $test->id }}" @selected(old('filter_open') === 'exams' && old('test_id') == $test->id)>
                                                            {{ $test->test_title }}{{ $test->lesson ? ' — '.$test->lesson : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="filter-assign-field">
                                                <span>{{ $labels['assign_exam_date'] }}</span>
                                                <input type="datetime-local" name="scheduled_at" value="{{ old('filter_open') === 'exams' ? old('scheduled_at') : '' }}" class="filter-assign-input">
                                            </label>
                                        @endif

                                        {{-- The whole filter stack, mirrored for the
                                             server-side select_all re-derivation. --}}
                                        @include('consultant.partials._filter_hiddens', ['visible' => []])
                                        <input type="hidden" name="select_all" value="1">
                                        <input type="hidden" name="filter_open" value="exams">

                                        @if(!$tests->isEmpty())
                                            <button type="submit" class="primary-button filter-assign-submit">
                                                <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                                {{ $labels['assign_exam_submit'] }}
                                                <span class="filter-assign-count">{{ persian_digits($students->total()) }} {{ $labels['to_filtered_students'] }}</span>
                                            </button>
                                        @endif
                                    </form>
                                @endif
                            @endif
                        </div>

                        {{-- 2. Report cards: filter students by what their
                             کارنامه shows. No assignment section — creating
                             report-card entries doesn't exist yet. --}}
                        <div class="filter-page" data-filter-name="{{ $labels['panel_reports'] }}">
                            @if(!$panelFlags['reports'])
                                <p class="filter-page-placeholder">{{ $labels['panel_disabled'] }}</p>
                            @else
                                <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-reports" data-filter-form data-filter-sync>
                                    @include('consultant.partials._filter_chips', [
                                        'name' => 'report_source',
                                        'idPrefix' => 'report-source',
                                        'label' => $labels['filter_report_source'],
                                        'options' => $reportSources,
                                        'selected' => $filters['report_source'],
                                        'labels' => $labels,
                                    ])
                                    @include('consultant.partials._filter_chips', [
                                        'name' => 'report_status',
                                        'idPrefix' => 'report-status',
                                        'label' => $labels['filter_report_status'],
                                        'options' => $reportStatuses,
                                        'selected' => $filters['report_status'],
                                        'labels' => $labels,
                                    ])

                                    @include('consultant.partials._filter_hiddens', ['visible' => ['report_source', 'report_status']])
                                </form>
                            @endif
                        </div>

                        {{-- 3. Weekly schedule: filter by existing blocks, plus
                             the bulk block creator (was «برنامه گروهی»). --}}
                        <div class="filter-page" data-filter-name="{{ $labels['panel_schedule'] }}">
                            @if(!$panelFlags['schedule'])
                                <p class="filter-page-placeholder">{{ $labels['panel_disabled'] }}</p>
                            @else
                                <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-schedule" data-filter-form data-filter-sync>
                                    @include('consultant.partials._filter_chips', [
                                        'name' => 'schedule_day',
                                        'idPrefix' => 'schedule-day',
                                        'label' => $labels['filter_schedule_day'],
                                        'options' => collect($scheduleDays)->mapWithKeys(fn ($d, $i) => [(string) $i => $d]),
                                        'selected' => $filters['schedule_day'],
                                        'labels' => $labels,
                                    ])
                                    @include('consultant.partials._filter_chips', [
                                        'name' => 'schedule_done',
                                        'idPrefix' => 'schedule-done',
                                        'label' => $labels['filter_schedule_done'],
                                        'options' => $scheduleDoneOptions,
                                        'selected' => $filters['schedule_done'],
                                        'labels' => $labels,
                                    ])

                                    @include('consultant.partials._filter_hiddens', ['visible' => ['schedule_day', 'schedule_done']])
                                </form>

                                @if($panelFlags['assign'])
                                    <form method="POST" action="{{ route('consultant.bulk.schedule.store') }}" class="filter-assign" data-filter-sync>
                                        @csrf
                                        <h4 class="filter-assign-title">{{ $labels['assign_schedule_title'] }}</h4>
                                        <p class="filter-assign-hint">{{ $labels['assign_schedule_hint'] }}</p>

                                        <label class="filter-assign-field">
                                            <span>{{ $labels['assign_schedule_block_title'] }}</span>
                                            <input type="text" name="title" value="{{ old('filter_open') === 'schedule' ? old('title') : '' }}" required class="filter-assign-input">
                                        </label>

                                        <div class="filter-assign-row">
                                            <label class="filter-assign-field filter-assign-field--half">
                                                <span>{{ $labels['assign_schedule_week_start'] }}</span>
                                                <input type="date" name="week_start_date" value="{{ old('filter_open') === 'schedule' ? old('week_start_date', $weekStart) : $weekStart }}" class="filter-assign-input">
                                            </label>
                                            <label class="filter-assign-field filter-assign-field--half">
                                                <span>{{ $labels['filter_schedule_day'] }}</span>
                                                <select name="day_index" class="filter-assign-input">
                                                    @foreach($scheduleDays as $i => $day)
                                                        <option value="{{ $i }}" @selected(old('filter_open') === 'schedule' && old('day_index') == $i)>{{ $day }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>

                                        <div class="filter-assign-row">
                                            <label class="filter-assign-field filter-assign-field--half">
                                                <span>{{ $labels['assign_schedule_start'] }}</span>
                                                <input type="time" name="start_time" value="{{ old('filter_open') === 'schedule' ? old('start_time') : '' }}" required class="filter-assign-input">
                                            </label>
                                            <label class="filter-assign-field filter-assign-field--half">
                                                <span>{{ $labels['assign_schedule_end'] }}</span>
                                                <input type="time" name="end_time" value="{{ old('filter_open') === 'schedule' ? old('end_time') : '' }}" required class="filter-assign-input">
                                            </label>
                                        </div>

                                        <div class="filter-assign-row">
                                            <label class="filter-assign-field filter-assign-field--color">
                                                <span>{{ $labels['assign_schedule_color'] }}</span>
                                                <input type="color" name="color" value="{{ old('filter_open') === 'schedule' ? old('color', '#3b82f6') : '#3b82f6' }}" class="filter-assign-input">
                                            </label>
                                            <label class="filter-assign-field filter-assign-field--half">
                                                <span>{{ $labels['assign_schedule_book'] }}</span>
                                                <input type="text" name="book_name" value="{{ old('filter_open') === 'schedule' ? old('book_name') : '' }}" class="filter-assign-input">
                                            </label>
                                        </div>

                                        <div class="filter-assign-row">
                                            <label class="filter-assign-field filter-assign-field--half">
                                                <span>{{ $labels['assign_schedule_test_count'] }}</span>
                                                <input type="number" name="test_count" min="0" value="{{ old('filter_open') === 'schedule' ? old('test_count') : '' }}" class="filter-assign-input">
                                            </label>
                                            <label class="filter-assign-field filter-assign-field--half">
                                                <span>{{ $labels['assign_schedule_page_count'] }}</span>
                                                <input type="number" name="page_count" min="0" value="{{ old('filter_open') === 'schedule' ? old('page_count') : '' }}" class="filter-assign-input">
                                            </label>
                                        </div>

                                        @include('consultant.partials._filter_hiddens', ['visible' => []])
                                        <input type="hidden" name="select_all" value="1">
                                        <input type="hidden" name="filter_open" value="schedule">

                                        <button type="submit" class="primary-button filter-assign-submit">
                                            <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                            {{ $labels['assign_schedule_submit'] }}
                                            <span class="filter-assign-count">{{ persian_digits($students->total()) }} {{ $labels['to_filtered_students'] }}</span>
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <div class="filter-dots">
                    <button type="button" class="filter-dot @if($initialPanel === 0) is-active @endif" data-filter-dot="0" aria-label="فیلترها"></button>
                    <button type="button" class="filter-dot @if($initialPanel === 1) is-active @endif" data-filter-dot="1" aria-label="{{ $labels['panel_exams'] }}"></button>
                    <button type="button" class="filter-dot @if($initialPanel === 2) is-active @endif" data-filter-dot="2" aria-label="{{ $labels['panel_reports'] }}"></button>
                    <button type="button" class="filter-dot @if($initialPanel === 3) is-active @endif" data-filter-dot="3" aria-label="{{ $labels['panel_schedule'] }}"></button>
                </div>

                <div class="filter-actions">
                    {{-- One apply button for every page: app.js repoints its
                         form attribute at the visible panel's GET form. --}}
                    <button type="submit" form="filter-form-{{ $panelKeys[$initialPanel] }}" class="primary-button" data-filter-apply>{{ $labels['filter_apply'] }}</button>
                    <a href="{{ route('consultant.dashboard') }}" class="secondary-button">{{ $labels['filter_reset'] }}</a>
                </div>
            </div>
        </div>

        <div class="pager" data-router-region="results" @if(!$students->hasPages()) hidden @endif>
            <a
                href="{{ $students->previousPageUrl() ?? '#' }}"
                class="pager-btn pager-prev @if($students->onFirstPage()) is-disabled @endif"
                @if($students->onFirstPage()) aria-disabled="true" tabindex="-1" @endif
                aria-label="صفحه قبل"
            >
                <i class="fas fa-chevron-right"></i>
            </a>
            <span class="pager-info">
                {{ persian_digits($students->currentPage()) }} / {{ persian_digits($students->lastPage()) }}
            </span>
            <a
                href="{{ $students->nextPageUrl() ?? '#' }}"
                class="pager-btn pager-next @unless($students->hasMorePages()) is-disabled @endunless"
                @unless($students->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless
                aria-label="صفحه بعد"
            >
                <i class="fas fa-chevron-left"></i>
            </a>
        </div>
    </div>
</div>

<div data-router-region="results">
    @if($students->count() > 0)
        <div class="student-grid" data-stagger>
            @foreach($students as $student)
            <a
                href="{{ route('consultant.student.profile', $student) }}"
                class="student-card"
                aria-label="{{ $labels['action_profile'] }}: {{ $student->name }}"
            >
                <span class="student-avatar-sm">{{ mb_substr($student->name, 0, 1) }}</span>
                <span class="student-card-name">{{ $student->name }}</span>
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
                <span class="student-card-floats" aria-hidden="true">
                    <i class="fas fa-chart-line"></i>
                    <i class="fas fa-tasks"></i>
                    <i class="fas fa-calendar-alt"></i>
                </span>
            </a>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            @if($search !== '' || $activeFilterCount > 0)
                <h3>{{ $labels['empty_search_title'] }}</h3>
                <p>{{ $labels['empty_search_text'] }}</p>
            @else
                <h3>{{ $labels['empty_students_title'] }}</h3>
                <p>{{ $labels['empty_students_text'] }}</p>
            @endif
        </div>
    @endif
</div>
@endsection
