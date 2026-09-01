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
                @if($activeFilterCount > 0)
                    <span class="filter-count">{{ persian_digits($activeFilterCount) }}</span>
                @endif
            </label>

            <div class="filter-popover">
                <p class="filter-popover-title">{{ $labels['filter_title'] }}</p>
                <form method="GET" action="{{ route('consultant.dashboard') }}">
                    <input type="hidden" name="search" value="{{ $search }}">

                    <div class="filter-field">
                        <label for="filter-grade">{{ $labels['filter_grade'] }}</label>
                        <select id="filter-grade" name="grade">
                            <option value="">{{ $labels['filter_all'] }}</option>
                            @foreach($gradeOptions as $option)
                                <option value="{{ $option }}" @selected($filters['grade'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="filter-gender">{{ $labels['filter_gender'] }}</label>
                        <select id="filter-gender" name="gender">
                            <option value="">{{ $labels['filter_all'] }}</option>
                            @foreach($genderOptions as $option)
                                <option value="{{ $option }}" @selected($filters['gender'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="filter-major">{{ $labels['filter_major'] }}</label>
                        <select id="filter-major" name="major">
                            <option value="">{{ $labels['filter_all'] }}</option>
                            @foreach($majorOptions as $option)
                                <option value="{{ $option }}" @selected($filters['major'] === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="filter-sort">{{ $labels['filter_sort'] }}</label>
                        <select id="filter-sort" name="sort">
                            <option value="" @selected($filters['sort'] === '' || $filters['sort'] === 'name_asc')>{{ $labels['sort_name_asc'] }}</option>
                            <option value="name_desc" @selected($filters['sort'] === 'name_desc')>{{ $labels['sort_name_desc'] }}</option>
                            <option value="newest" @selected($filters['sort'] === 'newest')>{{ $labels['sort_newest'] }}</option>
                            <option value="oldest" @selected($filters['sort'] === 'oldest')>{{ $labels['sort_oldest'] }}</option>
                        </select>
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
        <div class="student-grid">
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
                    <i class="fas fa-shield-alt"></i>
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
