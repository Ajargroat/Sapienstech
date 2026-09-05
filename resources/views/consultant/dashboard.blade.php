@extends('layouts.consultant')

@section('content')
<div class="panel-heading">
    <div class="panel-heading-title">
        <h2>{{ $labels['student_list'] }}</h2>
        <span class="count-badge">{{ persian_digits($students->total()) }} نفر</span>
    </div>

    <div class="panel-heading-actions">
        <div class="search-reveal @if($search !== '') is-open @endif">
            <form method="GET" action="{{ route('consultant.dashboard') }}" class="search-reveal-form">
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
                @if($filters['grade'] !== '')<input type="hidden" name="grade" value="{{ $filters['grade'] }}">@endif
                @if($filters['gender'] !== '')<input type="hidden" name="gender" value="{{ $filters['gender'] }}">@endif
                @if($filters['major'] !== '')<input type="hidden" name="major" value="{{ $filters['major'] }}">@endif
                @if($filters['sort'] !== '')<input type="hidden" name="sort" value="{{ $filters['sort'] }}">@endif
                @if($search !== '')
                    <a href="{{ route('consultant.dashboard') }}" class="search-reveal-clear" aria-label="{{ $labels['clear_search'] }}">
                        <i class="fas fa-times"></i>
                    </a>
                @endif
            </form>
        </div>

        <div class="filter-wrap">
            <input type="checkbox" id="filter-toggle" class="filter-toggle-input" @checked($activeFilterCount > 0)>
            <label for="filter-toggle" class="filter-toggle-btn" aria-label="{{ $labels['filter_button'] }}">
                <i class="fas fa-sliders-h"></i>
                <span class="filter-toggle-label">{{ $labels['filter_button'] }}</span>
            </label>

            @if($activeFilterCount > 0)
                <span class="filter-count">{{ persian_digits($activeFilterCount) }}</span>
            @endif

            <div class="filter-popover">
                <form method="GET" action="{{ route('consultant.dashboard') }}">
                    <input type="hidden" name="search" value="{{ $search }}">

                    <div class="filter-carousel-head">
                        <button type="button" class="filter-page-btn filter-page-prev" data-filter-prev aria-label="گروه فیلتر قبلی">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <p class="filter-popover-title" data-filter-title>{{ $labels['filter_title'] }}</p>
                        <button type="button" class="filter-page-btn filter-page-next" data-filter-next aria-label="گروه فیلتر بعدی">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>

                    <div class="filter-carousel" data-filter-carousel>
                        <div class="filter-carousel-track">
                            <div class="filter-page" data-filter-name="{{ $labels['filter_title'] }}">
                                <div class="filter-field">
                                    <span class="filter-field-name">{{ $labels['filter_grade'] }}</span>
                                    <div class="filter-chips">
                                        <input type="radio" name="grade" value="" id="grade-all" class="filter-chip-input" @checked($filters['grade'] === '')>
                                        <label for="grade-all" class="filter-chip">{{ $labels['filter_all'] }}</label>
                                        @foreach($gradeOptions as $option)
                                            <input type="radio" name="grade" value="{{ $option }}" id="grade-{{ $loop->index }}" class="filter-chip-input" @checked($filters['grade'] === $option)>
                                            <label for="grade-{{ $loop->index }}" class="filter-chip">{{ $option }}</label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="filter-field">
                                    <span class="filter-field-name">{{ $labels['filter_gender'] }}</span>
                                    <div class="filter-chips">
                                        <input type="radio" name="gender" value="" id="gender-all" class="filter-chip-input" @checked($filters['gender'] === '')>
                                        <label for="gender-all" class="filter-chip">{{ $labels['filter_all'] }}</label>
                                        @foreach($genderOptions as $option)
                                            <input type="radio" name="gender" value="{{ $option }}" id="gender-{{ $loop->index }}" class="filter-chip-input" @checked($filters['gender'] === $option)>
                                            <label for="gender-{{ $loop->index }}" class="filter-chip">{{ $option }}</label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="filter-field">
                                    <span class="filter-field-name">{{ $labels['filter_major'] }}</span>
                                    <div class="filter-chips">
                                        <input type="radio" name="major" value="" id="major-all" class="filter-chip-input" @checked($filters['major'] === '')>
                                        <label for="major-all" class="filter-chip">{{ $labels['filter_all'] }}</label>
                                        @foreach($majorOptions as $option)
                                            <input type="radio" name="major" value="{{ $option }}" id="major-{{ $loop->index }}" class="filter-chip-input" @checked($filters['major'] === $option)>
                                            <label for="major-{{ $loop->index }}" class="filter-chip">{{ $option }}</label>
                                        @endforeach
                                    </div>
                                </div>

                                @php($sortOptions = [
                                    'name_asc' => $labels['sort_name_asc'],
                                    'name_desc' => $labels['sort_name_desc'],
                                    'newest' => $labels['sort_newest'],
                                    'oldest' => $labels['sort_oldest'],
                                ])
                                <div class="filter-field">
                                    <span class="filter-field-name">{{ $labels['filter_sort'] }}</span>
                                    <div class="filter-chips">
                                        <input type="radio" name="sort" value="" id="sort-all" class="filter-chip-input" @checked($filters['sort'] === '')>
                                        <label for="sort-all" class="filter-chip">{{ $labels['filter_all'] }}</label>
                                        @foreach($sortOptions as $value => $label)
                                            <input type="radio" name="sort" value="{{ $value }}" id="sort-{{ $loop->index }}" class="filter-chip-input" @checked($filters['sort'] === $value)>
                                            <label for="sort-{{ $loop->index }}" class="filter-chip">{{ $label }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="filter-page" data-filter-name="آزمون">
                                <p class="filter-page-placeholder">فیلترهای آزمون…</p>
                            </div>
                            <div class="filter-page" data-filter-name="کارنامه">
                                <p class="filter-page-placeholder">فیلترهای کارنامه…</p>
                            </div>
                            <div class="filter-page" data-filter-name="برنامه هفتگی">
                                <p class="filter-page-placeholder">فیلترهای برنامه هفتگی…</p>
                            </div>
                        </div>
                    </div>

                    <div class="filter-dots">
                        <button type="button" class="filter-dot is-active" data-filter-dot="0" aria-label="فیلترها"></button>
                        <button type="button" class="filter-dot" data-filter-dot="1" aria-label="آزمون"></button>
                        <button type="button" class="filter-dot" data-filter-dot="2" aria-label="کارنامه"></button>
                        <button type="button" class="filter-dot" data-filter-dot="3" aria-label="برنامه هفتگی"></button>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="primary-button">{{ $labels['filter_apply'] }}</button>
                        <a href="{{ route('consultant.dashboard') }}" class="secondary-button">{{ $labels['filter_reset'] }}</a>
                    </div>
                </form>
            </div>
        </div>

        @if($students->hasPages())
            <div class="pager">
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
        @endif
    </div>
</div>

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
@endsection
