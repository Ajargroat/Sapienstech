@extends('layouts.consultant')

@section('content')
    <div class="dashboard-header">
        <div>
            <h1>{{ $labels['dashboard_heading'] }}</h1>
            <p>{{ $labels['welcome_prefix'] }} <strong>{{ $username }}</strong>!</p>
        </div>
        
        @if(config('consultant.features.create_post_action') && config('consultant.features.blog_management'))
            <a href="{{ route('consultant.blog') }}" class="primary-button">
                <i class="fas fa-plus"></i>
                {{ $labels['create_post'] }}
            </a>
        @endif
    </div>

    @if(config('consultant.features.student_statistics'))
        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <strong>{{ persian_digits(count($assigned_students)) }}</strong>
                    <span>{{ $labels['student_count'] }}</span>
                </div>
            </article>
            
            <article class="stat-card">
                <div class="stat-icon secondary">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <strong>{{ persian_digits($active_quizzes_count) }}</strong>
                    <span>{{ $labels['active_quizzes'] }}</span>
                </div>
            </article>
        </section>
    @endif

    @if(config('consultant.features.student_search') || config('consultant.features.student_filters') || config('consultant.features.student_sorting'))
        <section class="panel filter-panel">
            <h2><i class="fas fa-filter"></i> {{ $labels['filters_heading'] }}</h2>
            
            <div class="filter-grid">
                @if(config('consultant.features.student_search'))
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" type="text" placeholder="{{ $labels['search_placeholder'] }}">
                    </div>
                @endif 
                
                @if(config('consultant.features.student_filters'))
                    <select id="gradeFilter">
                        <option value="all">{{ $labels['all_grades'] }}</option>
                        @foreach($filters['grades'] as $f)
                            <option value="{{ $f['value'] }}">{{ $f['label'] }}</option>
                        @endforeach
                    </select>
                    
                    <select id="majorFilter">
                        <option value="all">{{ $labels['all_majors'] }}</option>
                        @foreach($filters['majors'] as $f)
                            <option value="{{ $f['value'] }}">{{ $f['label'] }}</option>
                        @endforeach
                    </select>
                    
                    <select id="genderFilter">
                        <option value="all">{{ $labels['all_genders'] }}</option>
                        @foreach($filters['genders'] as $f)
                            <option value="{{ $f['value'] }}">{{ $f['label'] }}</option>
                        @endforeach
                    </select>
                @endif 
                
                @if(config('consultant.features.student_sorting'))
                    <select id="sortSelect">
                        <option value="name_asc">{{ $labels['sort_asc'] }}</option>
                        <option value="name_desc">{{ $labels['sort_desc'] }}</option>
                    </select>
                @endif
            </div>
        </section>
    @endif

    <section class="panel student-panel">
        <div class="panel-heading">
            <h2>{{ $labels['student_list'] }}</h2>
            <span id="studentCountBadge" class="count-badge">{{ persian_digits(count($assigned_students)) }} نفر</span>
        </div>
        
        <div id="studentListContainer" class="student-list">
            @forelse($assigned_students as $student)
                @php
                    $girl = $student['gender'] === 'دختر';
                    $majorClass = match($student['major']) {
                        'تجربی' => 'major-green',
                        'ریاضی' => 'major-blue',
                        'انسانی' => 'major-orange',
                        default => ''
                    };
                @endphp

                <article 
                    class="student-card"
                    data-name="{{ $student['username'] }}"
                    data-grade="{{ $student['grade'] }}"
                    data-gender="{{ $student['gender'] }}"
                    data-major="{{ $student['major'] }}"
                >
                    <div class="student-main">
                        <div class="student-avatar {{ $girl ? 'girl' : 'boy' }}">
                            {{ mb_substr($student['username'], 0, 1) }}
                        </div>
                        <div class="student-info">
                            <div class="student-name-row">
                                <strong>{{ $student['username'] }}</strong>
                                <span class="gender-badge {{ $girl ? 'girl' : 'boy' }}">
                                    <i class="fas {{ $girl ? 'fa-venus' : 'fa-mars' }}"></i>
                                    {{ $student['gender'] }}
                                </span>
                            </div>
                            <span class="student-email">{{ $student['email'] }}</span>
                            
                            <div class="student-meta">
                                <span>
                                    <i class="fas fa-graduation-cap"></i>
                                    {{ $labels['student_grade'] }} {{ persian_digits($student['grade']) }}ام
                                </span>
                                <span class="{{ $majorClass }}">
                                    <i class="fas fa-book-open"></i>
                                    {{ $labels['student_major'] }} {{ $student['major'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="student-actions">
                        @if(config('consultant.features.student_schedule'))
                            <a href="{{ route('consultant.student.schedule', $student['id']) }}" class="secondary-button">
                                <i class="fas fa-calendar-alt"></i>
                                {{ $labels['schedule'] }}
                            </a>
                        @endif 
                        
                        @if(config('consultant.features.student_quizzes'))
                            <a href="{{ route('consultant.student.quizzes', $student['id']) }}" class="secondary-button">
                                <i class="fas fa-tasks"></i>
                                {{ $labels['quizzes'] }}
                            </a>
                        @endif 
                        
                        @if(config('consultant.features.report_cards'))
                            <a href="{{ route('consultant.student.report-card', $student['id']) }}" class="secondary-button">
                                <i class="fas fa-chart-line"></i>
                                {{ $labels['report_card'] }}
                            </a>
                        @endif
                    </div>
                </article>

            @empty
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>{{ $labels['empty_students_title'] }}</h3>
                    <p>{{ $labels['empty_students_text'] }}</p>
                </div>
            @endforelse
        </div>
        
        <div id="searchEmptyState" class="empty-state hidden">
            <i class="fas fa-search"></i>
            <h3>{{ $labels['empty_search_title'] }}</h3>
            <p>{{ $labels['empty_search_text'] }}</p>
        </div>
    </section>
@endsection