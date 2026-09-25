@extends('layouts.consultant')

@php
    // The filter modal always has its four tabs (general / exams / reports /
    // schedule); a tab whose feature is switched off says so instead of
    // filtering. old('filter_open') is flashed back by a failed assignment
    // POST, so the modal reopens exactly on the tab that produced the errors.
    $panelKeys = ['general', 'exams', 'reports', 'schedule'];
    $initialPanel = array_search((string) old('filter_open', ''), $panelKeys, true);
    $initialPanel = $initialPanel === false ? 0 : $initialPanel;
    $openOnError = $errors->any() && $initialPanel > 0;
@endphp

@section('content')
{{-- Studio ids: top-level regions of the consultant dashboard, addressable from
     the canvas under the template-owned `public.consultant.*` root (see
     StudioStyles::TEMPLATE_ROOTS). --}}
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

<template data-studio-section-marker="heading"></template>
<div class="panel-heading" data-studio-path="public.consultant.heading">
    <div class="panel-heading-title">
        <h2>{{ $labels['student_list'] }}</h2>
        <span class="count-badge" data-router-region="results">{{ persian_digits($students->total()) }} نفر</span>
    </div>

    <div class="panel-heading-actions">
        @if(site('features.theme_studio', false) && (!tenant()?->hierarchy_type || auth()->user()?->isTenantOwner()))
            {{-- The Theme Studio is a standalone page: open it in a new tab so
                 the tenant-themed hub never restyles the editor chrome. --}}
            <a href="{{ route('studio.index') }}" class="secondary-button" target="_blank" rel="noopener" data-router="off">
                <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
                استودیوی ظاهر
            </a>
        @endif
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
            <input type="checkbox" id="filter-toggle" class="filter-toggle-input" data-filter-modal-input @checked($openOnError)>
            <label for="filter-toggle" class="filter-toggle-btn" aria-label="{{ $labels['filter_button'] }}" title="{{ $labels['filter_button'] }}">
                <i class="fas fa-sliders-h"></i>
            </label>

            {{-- Always rendered (hidden when empty) so the router's region
                 pairing stays stable across partial swaps. --}}
            <span class="filter-count" data-router-region="results" @if($activeFilterCount === 0) hidden @endif>{{ persian_digits($activeFilterCount) }}</span>

            <div class="filter-popover filter-popover--modal" data-filter-popover data-filter-modal data-filter-page="{{ $initialPanel }}"
                 role="dialog" aria-modal="true" aria-label="{{ $labels['filter_title'] }}">
                <div class="filter-modal-card">
                    <div class="filter-modal-head">
                        <p class="filter-modal-title">
                            <i class="fas fa-sliders-h" aria-hidden="true"></i>
                            {{ $labels['filter_title'] }}
                        </p>
                        <button type="button" class="filter-modal-x" data-filter-close aria-label="بستن">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="filter-modal-body">
                        <div class="filter-modal-tabs" role="tablist" aria-label="{{ $labels['filter_title'] }}">
                            <button type="button" role="tab" id="filter-tab-general" aria-controls="filter-panel-general"
                                    class="filter-modal-tab @if($initialPanel === 0) is-active @endif"
                                    aria-selected="@if($initialPanel === 0) true @else false @endif" data-filter-tab="0">
                                <i class="fas fa-user-graduate" aria-hidden="true"></i>{{ $labels['filter_title'] }}
                            </button>
                            <button type="button" role="tab" id="filter-tab-exams" aria-controls="filter-panel-exams"
                                    class="filter-modal-tab @if($initialPanel === 1) is-active @endif"
                                    aria-selected="@if($initialPanel === 1) true @else false @endif" data-filter-tab="1">
                                <i class="fas fa-tasks" aria-hidden="true"></i>{{ $labels['panel_exams'] }}
                            </button>
                            <button type="button" role="tab" id="filter-tab-reports" aria-controls="filter-panel-reports"
                                    class="filter-modal-tab @if($initialPanel === 2) is-active @endif"
                                    aria-selected="@if($initialPanel === 2) true @else false @endif" data-filter-tab="2">
                                <i class="fas fa-chart-line" aria-hidden="true"></i>{{ $labels['panel_reports'] }}
                            </button>
                            <button type="button" role="tab" id="filter-tab-schedule" aria-controls="filter-panel-schedule"
                                    class="filter-modal-tab @if($initialPanel === 3) is-active @endif"
                                    aria-selected="@if($initialPanel === 3) true @else false @endif" data-filter-tab="3">
                                <i class="fas fa-calendar-alt" aria-hidden="true"></i>{{ $labels['panel_schedule'] }}
                            </button>
                        </div>

                        <div class="filter-modal-panels">
                        {{-- 0. General student filters --}}
                        <div class="filter-page @if($initialPanel === 0) is-active @endif" id="filter-panel-general" role="tabpanel" aria-labelledby="filter-tab-general">
                            <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-general" data-filter-form data-filter-sync>
                                <input type="hidden" name="search" value="{{ $search }}">

                                <div class="filter-fields">
                                    @include('consultant.partials._filter_select', [
                                        'name' => 'grade',
                                        'idPrefix' => 'grade',
                                        'label' => $labels['filter_grade'],
                                        'options' => $gradeOptions->mapWithKeys(fn ($o) => [(string) $o => $o]),
                                        'selected' => $filters['grade'],
                                        'counts' => $optionCounts['grade'] ?? [],
                                        'allCount' => $optionCounts['total'] ?? null,
                                        'labels' => $labels,
                                    ])
                                    @include('consultant.partials._filter_select', [
                                        'name' => 'gender',
                                        'idPrefix' => 'gender',
                                        'label' => $labels['filter_gender'],
                                        'options' => $genderOptions->mapWithKeys(fn ($o) => [(string) $o => $o]),
                                        'selected' => $filters['gender'],
                                        'counts' => $optionCounts['gender'] ?? [],
                                        'allCount' => $optionCounts['total'] ?? null,
                                        'labels' => $labels,
                                    ])
                                    @include('consultant.partials._filter_select', [
                                        'name' => 'major',
                                        'idPrefix' => 'major',
                                        'label' => $labels['filter_major'],
                                        'options' => $majorOptions->mapWithKeys(fn ($o) => [(string) $o => $o]),
                                        'selected' => $filters['major'],
                                        'counts' => $optionCounts['major'] ?? [],
                                        'allCount' => $optionCounts['total'] ?? null,
                                        'labels' => $labels,
                                    ])

                                    @php($sortOptions = [
                                        'name_asc' => $labels['sort_name_asc'],
                                        'name_desc' => $labels['sort_name_desc'],
                                        'newest' => $labels['sort_newest'],
                                        'oldest' => $labels['sort_oldest'],
                                    ])
                                    {{-- Ordering is single-choice by nature: no counts. --}}
                                    @include('consultant.partials._filter_select', [
                                        'name' => 'sort',
                                        'idPrefix' => 'sort',
                                        'label' => $labels['filter_sort'],
                                        'options' => $sortOptions,
                                        'selected' => $filters['sort'],
                                        'labels' => $labels,
                                    ])
                                </div>

                                {{-- Keep the other panels' live selections when applying here. --}}
                                @include('consultant.partials._filter_hiddens', ['visible' => ['grade', 'gender', 'major', 'sort']])
                            </form>
                        </div>

                        {{-- 1. Exams: filter students by their exam assignments,
                             plus the bulk exam assignment (was «آزمون گروهی»). --}}
                        <div class="filter-page @if($initialPanel === 1) is-active @endif" id="filter-panel-exams" role="tabpanel" aria-labelledby="filter-tab-exams">
                            @if(!$panelFlags['exams'])
                                <p class="filter-page-placeholder">{{ $labels['panel_disabled'] }}</p>
                            @else
                                <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-exams" data-filter-form data-filter-sync>
                                    <div class="filter-fields">
                                        @include('consultant.partials._filter_select', [
                                            'name' => 'exam_status',
                                            'idPrefix' => 'exam-status',
                                            'label' => $labels['filter_exam_status'],
                                            'options' => $examStatuses,
                                            'selected' => $filters['exam_status'],
                                            'counts' => $optionCounts['exam_status'] ?? [],
                                            'allCount' => $optionCounts['total'] ?? null,
                                            'labels' => $labels,
                                        ])
                                        @include('consultant.partials._filter_select', [
                                            'name' => 'exam_lesson',
                                            'idPrefix' => 'exam-lesson',
                                            'label' => $labels['filter_exam_lesson'],
                                            'options' => collect($examLessons)->mapWithKeys(fn ($o) => [(string) $o => $o]),
                                            'selected' => $filters['exam_lesson'],
                                            'counts' => $optionCounts['exam_lesson'] ?? [],
                                            'allCount' => $optionCounts['total'] ?? null,
                                            'labels' => $labels,
                                        ])
                                        @include('consultant.partials._filter_select', [
                                            'name' => 'exam_type',
                                            'idPrefix' => 'exam-type',
                                            'label' => $labels['filter_exam_type'],
                                            'options' => $examTypes,
                                            'selected' => $filters['exam_type'],
                                            'counts' => $optionCounts['exam_type'] ?? [],
                                            'allCount' => $optionCounts['total'] ?? null,
                                            'labels' => $labels,
                                        ])
                                    </div>

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
                                            <div class="filter-fields">
                                                @include('consultant.partials._filter_select', [
                                                    'name' => 'test_id',
                                                    'idPrefix' => 'assign-test',
                                                    'label' => $labels['assign_exam_test'],
                                                    'options' => $tests->mapWithKeys(fn ($test) => [
                                                        (string) $test->id => $test->test_title.($test->lesson ? ' — '.$test->lesson : ''),
                                                    ])->all(),
                                                    'selected' => old('filter_open') === 'exams' ? (string) old('test_id') : '',
                                                    'allowAll' => false,
                                                    'required' => true,
                                                    'placeholderText' => '— انتخاب آزمون —',
                                                    'labels' => $labels,
                                                ])
                                                <label class="filter-field">
                                                    <span class="filter-field-name">{{ $labels['assign_exam_date'] }}</span>
                                                    <input type="datetime-local" name="scheduled_at" value="{{ old('filter_open') === 'exams' ? old('scheduled_at') : '' }}" class="filter-input">
                                                </label>
                                            </div>
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
                        <div class="filter-page @if($initialPanel === 2) is-active @endif" id="filter-panel-reports" role="tabpanel" aria-labelledby="filter-tab-reports">
                            @if(!$panelFlags['reports'])
                                <p class="filter-page-placeholder">{{ $labels['panel_disabled'] }}</p>
                            @else
                                <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-reports" data-filter-form data-filter-sync>
                                    <div class="filter-fields">
                                        @include('consultant.partials._filter_select', [
                                            'name' => 'report_source',
                                            'idPrefix' => 'report-source',
                                            'label' => $labels['filter_report_source'],
                                            'options' => $reportSources,
                                            'selected' => $filters['report_source'],
                                            'counts' => $optionCounts['report_source'] ?? [],
                                            'allCount' => $optionCounts['total'] ?? null,
                                            'labels' => $labels,
                                        ])
                                        @include('consultant.partials._filter_select', [
                                            'name' => 'report_status',
                                            'idPrefix' => 'report-status',
                                            'label' => $labels['filter_report_status'],
                                            'options' => $reportStatuses,
                                            'selected' => $filters['report_status'],
                                            'counts' => $optionCounts['report_status'] ?? [],
                                            'allCount' => $optionCounts['total'] ?? null,
                                            'labels' => $labels,
                                        ])
                                    </div>

                                    @include('consultant.partials._filter_hiddens', ['visible' => ['report_source', 'report_status']])
                                </form>
                            @endif
                        </div>

                        {{-- 3. Weekly schedule: filter by existing blocks, plus
                             the bulk block creator (was «برنامه گروهی»). --}}
                        <div class="filter-page @if($initialPanel === 3) is-active @endif" id="filter-panel-schedule" role="tabpanel" aria-labelledby="filter-tab-schedule">
                            @if(!$panelFlags['schedule'])
                                <p class="filter-page-placeholder">{{ $labels['panel_disabled'] }}</p>
                            @else
                                <form method="GET" action="{{ route('consultant.dashboard') }}" id="filter-form-schedule" data-filter-form data-filter-sync>
                                    <div class="filter-fields">
                                        @include('consultant.partials._filter_select', [
                                            'name' => 'schedule_day',
                                            'idPrefix' => 'schedule-day',
                                            'label' => $labels['filter_schedule_day'],
                                            'options' => collect($scheduleDays)->mapWithKeys(fn ($d, $i) => [(string) $i => $d]),
                                            'selected' => $filters['schedule_day'],
                                            'counts' => $optionCounts['schedule_day'] ?? [],
                                            'allCount' => $optionCounts['total'] ?? null,
                                            'labels' => $labels,
                                        ])
                                        @include('consultant.partials._filter_select', [
                                            'name' => 'schedule_done',
                                            'idPrefix' => 'schedule-done',
                                            'label' => $labels['filter_schedule_done'],
                                            'options' => $scheduleDoneOptions,
                                            'selected' => $filters['schedule_done'],
                                            'counts' => $optionCounts['schedule_done'] ?? [],
                                            'allCount' => $optionCounts['total'] ?? null,
                                            'labels' => $labels,
                                        ])
                                    </div>

                                    @include('consultant.partials._filter_hiddens', ['visible' => ['schedule_day', 'schedule_done']])
                                </form>

                                @if($panelFlags['assign'])
                                    <form method="POST" action="{{ route('consultant.bulk.schedule.store') }}" class="filter-assign" data-filter-sync>
                                        @csrf
                                        <h4 class="filter-assign-title">{{ $labels['assign_schedule_title'] }}</h4>
                                        <p class="filter-assign-hint">{{ $labels['assign_schedule_hint'] }}</p>

                                        <label class="filter-field">
                                            <span class="filter-field-name">{{ $labels['assign_schedule_block_title'] }}</span>
                                            <input type="text" name="title" value="{{ old('filter_open') === 'schedule' ? old('title') : '' }}" required class="filter-input">
                                        </label>

                                        <div class="filter-fields">
                                            <label class="filter-field">
                                                <span class="filter-field-name">{{ $labels['assign_schedule_week_start'] }}</span>
                                                <input type="date" name="week_start_date" value="{{ old('filter_open') === 'schedule' ? old('week_start_date', $weekStart) : $weekStart }}" class="filter-input">
                                            </label>
                                            @include('consultant.partials._filter_select', [
                                                'name' => 'day_index',
                                                'idPrefix' => 'assign-day',
                                                'label' => $labels['filter_schedule_day'],
                                                'options' => collect($scheduleDays)->mapWithKeys(fn ($day, $i) => [(string) $i => $day])->all(),
                                                'selected' => old('filter_open') === 'schedule' && old('day_index') !== null ? (string) old('day_index') : '0',
                                                'allowAll' => false,
                                                'required' => true,
                                                'labels' => $labels,
                                            ])
                                            <label class="filter-field">
                                                <span class="filter-field-name">{{ $labels['assign_schedule_start'] }}</span>
                                                <input type="time" name="start_time" value="{{ old('filter_open') === 'schedule' ? old('start_time') : '' }}" required class="filter-input">
                                            </label>
                                            <label class="filter-field">
                                                <span class="filter-field-name">{{ $labels['assign_schedule_end'] }}</span>
                                                <input type="time" name="end_time" value="{{ old('filter_open') === 'schedule' ? old('end_time') : '' }}" required class="filter-input">
                                            </label>
                                            <label class="filter-field filter-field--color">
                                                <span class="filter-field-name">{{ $labels['assign_schedule_color'] }}</span>
                                                <input type="color" name="color" value="{{ old('filter_open') === 'schedule' ? old('color', '#3b82f6') : '#3b82f6' }}" class="filter-input">
                                            </label>
                                            <label class="filter-field">
                                                <span class="filter-field-name">{{ $labels['assign_schedule_book'] }}</span>
                                                <input type="text" name="book_name" value="{{ old('filter_open') === 'schedule' ? old('book_name') : '' }}" class="filter-input">
                                            </label>
                                            <label class="filter-field">
                                                <span class="filter-field-name">{{ $labels['assign_schedule_test_count'] }}</span>
                                                <input type="number" name="test_count" min="0" value="{{ old('filter_open') === 'schedule' ? old('test_count') : '' }}" class="filter-input">
                                            </label>
                                            <label class="filter-field">
                                                <span class="filter-field-name">{{ $labels['assign_schedule_page_count'] }}</span>
                                                <input type="number" name="page_count" min="0" value="{{ old('filter_open') === 'schedule' ? old('page_count') : '' }}" class="filter-input">
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

                    <div class="filter-modal-foot">
                        <div class="filter-actions">
                            {{-- One apply button for every tab: app.js repoints its
                                 form attribute at the visible panel's GET form. --}}
                            <button type="submit" form="filter-form-{{ $panelKeys[$initialPanel] }}" class="primary-button" data-filter-apply>{{ $labels['filter_apply'] }}</button>
                            <a href="{{ route('consultant.dashboard') }}" class="secondary-button">{{ $labels['filter_reset'] }}</a>
                        </div>

                        @if($panelFlags['assign'])
                            {{-- The bulk nav entry is gone; the assignment history (and
                                 revert controls) is reachable from here instead. --}}
                            <a href="{{ route('consultant.bulk.history') }}" class="filter-foot-link">
                                <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                                {{ $labels['filter_history'] }}
                            </a>
                        @endif
                    </div>
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

<div data-router-region="results" data-studio-path="public.consultant.students">
    @if($students->count() > 0)
        <div class="student-grid" data-stagger data-studio-path="public.consultant.students.grid">
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
