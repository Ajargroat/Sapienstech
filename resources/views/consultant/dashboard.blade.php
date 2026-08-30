@extends('layouts.consultant')

@section('content')
    <div class="dashboard-header">
        <div>
            <h1>{{ $labels['dashboard_heading'] }}</h1>
            <p>{{ $labels['welcome_prefix'] }} <strong>{{ $username }}</strong>!</p>
        </div>
    </div>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('consultant.dashboard') }}" class="search-form">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ $labels['search_placeholder'] }}"
                >
            </div>
            <button type="submit" class="primary-button">{{ $labels['search_button'] }}</button>
            @if($search !== '')
                <a href="{{ route('consultant.dashboard') }}" class="secondary-button">
                    {{ $labels['clear_search'] }}
                </a>
            @endif
        </form>
    </section>

    <section class="panel student-panel">
        <div class="panel-heading">
            <h2>{{ $labels['student_list'] }}</h2>
            <span class="count-badge">{{ persian_digits($students->total()) }} نفر</span>
        </div>

        <div class="table-scroll">
            <table class="student-table">
                <thead>
                    <tr>
                        <th>{{ $labels['th_name'] }}</th>
                        <th>{{ $labels['th_email'] }}</th>
                        <th>{{ $labels['th_grade'] }}</th>
                        <th>{{ $labels['th_gender'] }}</th>
                        <th>{{ $labels['th_major'] }}</th>
                        <th>{{ $labels['th_actions'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr class="student-row" data-href="{{ route('consultant.student.profile', $student) }}">
                            <td data-label="{{ $labels['th_name'] }}">
                                <div class="student-cell">
                                    <span class="student-avatar-sm">
                                        @if($student->avatar)
                                            <img src="{{ asset('storage/' . $student->avatar) }}" alt="{{ $student->name }}" class="student-avatar-img">
                                        @else
                                            {{ mb_substr($student->name, 0, 1) }}
                                        @endif
                                    </span>
                                    <a href="{{ route('consultant.student.profile', $student) }}" class="student-name-link">{{ $student->name }}</a>
                                </div>
                            </td>
                            <td data-label="{{ $labels['th_email'] }}">{{ $student->email }}</td>
                            <td data-label="{{ $labels['th_grade'] }}">{{ $student->grade }}</td>
                            <td data-label="{{ $labels['th_gender'] }}">{{ $student->gender }}</td>
                            <td data-label="{{ $labels['th_major'] }}">{{ $student->major }}</td>
                            <td data-label="{{ $labels['th_actions'] }}">
                                <div class="actions-menu">
                                    <button type="button" class="secondary-button actions-toggle">
                                        {{ $labels['actions_label'] }}
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="actions-dropdown">
                                        <a href="{{ route('consultant.student.report-card', $student) }}">
                                            <i class="fas fa-chart-line"></i> {{ $labels['action_report_card'] }}
                                        </a>
                                        <a href="{{ route('consultant.student.exams', $student) }}">
                                            <i class="fas fa-tasks"></i> {{ $labels['action_exams'] }}
                                        </a>
                                        <a href="{{ route('consultant.student.schedule', $student) }}">
                                            <i class="fas fa-calendar-alt"></i> {{ $labels['action_schedule'] }}
                                        </a>
                                        <a href="{{ route('consultant.student.source-permissions', $student) }}">
                                            <i class="fas fa-shield-alt"></i> {{ $labels['action_source_permissions'] }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    @if($search !== '')
                                        <h3>{{ $labels['empty_search_title'] }}</h3>
                                        <p>{{ $labels['empty_search_text'] }}</p>
                                    @else
                                        <h3>{{ $labels['empty_students_title'] }}</h3>
                                        <p>{{ $labels['empty_students_text'] }}</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($students->hasPages())
            <div class="pagination-wrap">
                {{ $students->onEachSide(1)->links() }}
            </div>
        @endif
    </section>

    <script>
        document.querySelectorAll('.student-row').forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.actions-menu') || e.target.closest('a') || e.target.closest('button')) return;
                window.location = this.dataset.href;
            });
        });
    </script>
@endsection
