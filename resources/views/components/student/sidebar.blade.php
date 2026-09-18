{{--
    Student sidebar shell: the sidebar counterpart of the student topnav,
    following the same tenant choice (theme.layout.shell_nav). Shares the
    consultant sidebar's styles so both portals stay visually identical, and
    keeps the `sidebar-link` contract page-router uses for the active state.
--}}
<aside class="consultant-sidebar">
    <a href="{{ route('student.dashboard') }}" class="brand">
        <span class="brand-mark brand-mark-small"></span>
        <strong>{{ $tenant['short_name'] }}</strong>
    </a>

    <nav class="sidebar-links" aria-label="ناوبری اصلی">
        <a
            href="{{ route('student.dashboard') }}"
            class="sidebar-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}"
        >
            <i class="fas fa-gauge-high" aria-hidden="true"></i>
            <span>داشبورد</span>
        </a>

        @if(site('features.report_cards', false))
            <a
                href="{{ route('student.report-card') }}"
                class="sidebar-link {{ request()->routeIs('student.report-card') ? 'active' : '' }}"
            >
                <i class="fas fa-chart-pie" aria-hidden="true"></i>
                <span>کارنامه</span>
            </a>
        @endif

        @if(site('features.student_materials', false) && \Illuminate\Support\Facades\Route::has('student.lessons.index'))
            <a
                href="{{ route('student.lessons.index') }}"
                class="sidebar-link {{ request()->routeIs('student.lessons*') ? 'active' : '' }}"
            >
                <i class="fas fa-book-open-reader" aria-hidden="true"></i>
                <span>درس‌ها</span>
            </a>
        @endif

        @if(site('features.student_assignments', false) && \Illuminate\Support\Facades\Route::has('student.assignments.index'))
            <a
                href="{{ route('student.assignments.index') }}"
                class="sidebar-link {{ request()->routeIs('student.assignments*') ? 'active' : '' }}"
            >
                <i class="fas fa-tasks" aria-hidden="true"></i>
                <span>تکالیف</span>
            </a>
        @endif

        @if(site('features.student_timetable', false) && \Illuminate\Support\Facades\Route::has('student.timetable'))
            <a
                href="{{ route('student.timetable') }}"
                class="sidebar-link {{ request()->routeIs('student.timetable') ? 'active' : '' }}"
            >
                <i class="fas fa-calendar-week" aria-hidden="true"></i>
                <span>جدول کلاس</span>
            </a>
        @endif

        @if(site('features.deals', false) && \Illuminate\Support\Facades\Route::has('student.deals.index'))
            <a
                href="{{ route('student.deals.index') }}"
                class="sidebar-link {{ request()->routeIs('student.deals*') ? 'active' : '' }}"
            >
                <i class="fas fa-file-invoice" aria-hidden="true"></i>
                <span>دورهٔ من</span>
            </a>
        @endif

        @if(site('features.student_chat', false) && \Illuminate\Support\Facades\Route::has('student.direct-chat.page'))
            <a
                href="{{ route('student.direct-chat.page') }}"
                class="sidebar-link {{ request()->routeIs('student.direct-chat*') ? 'active' : '' }}"
            >
                <i class="fas fa-comments" aria-hidden="true"></i>
                <span>{{ $labels['student_chat'] ?? 'گفتگو' }}</span>
                @include('partials.chat.unread-badge', ['chatActorKey' => 'student'])
            </a>
        @endif
    </nav>

    <div class="sidebar-user topnav-user">
        <button
            type="button"
            id="theme-toggle-btn"
            class="topnav-icon-btn theme-toggle"
            title="تغییر حالت روشن/تاریک"
            aria-label="تغییر حالت روشن/تاریک"
        >
            <i class="fas fa-moon"></i>
            <i class="fas fa-sun"></i>
        </button>

        {{-- Profile hub (mirrors the student topnav): direct link. --}}
        <a
            href="{{ route('student.settings.profile') }}"
            class="topnav-icon-btn topnav-profile {{ request()->routeIs('student.settings.profile') ? 'active' : '' }}"
            title="{{ $labels['settings_profile'] ?? 'پروفایل' }}"
            aria-label="{{ $labels['settings_profile'] ?? 'پروفایل' }}"
            @if(request()->routeIs('student.settings.profile')) aria-current="page" @endif
        >
            <i class="fas fa-user"></i>
        </a>
    </div>
</aside>
