{{-- Teacher panel sidebar shell: the same tabs as the topnav, stacked in a
     fixed rail. Chosen per tenant via theme.layout.shell_nav, exactly like
     the consultant and student portals. --}}
<header class="sidebar-mobile-bar">
    <a href="{{ route('teacher.dashboard') }}" class="brand sidebar-mobile-brand">
        <span class="brand-mark brand-mark-small"></span>
        <strong>{{ $tenant['short_name'] }}</strong>
    </a>
    <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="منو">
        <span class="sidebar-toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span>
    </button>
</header>
<div class="sidebar-scrim" data-sidebar-scrim hidden></div>
<aside id="app-sidebar" class="consultant-sidebar" aria-label="ناوبری پنل">
    <div class="sidebar-brand">
        <a href="{{ route('teacher.dashboard') }}" class="brand">
            <span class="brand-mark brand-mark-small"></span>
            <span class="sidebar-brand-copy">
                <strong>{{ $tenant['short_name'] }}</strong>
                <small>پنل معلم</small>
            </span>
        </a>
    </div>

    <nav class="sidebar-links" aria-label="ناوبری اصلی">
        <span class="sidebar-section-label">کلاس‌ها</span>
        <a
            href="{{ route('teacher.dashboard') }}"
            class="sidebar-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}"
        >
            <i class="fas fa-gauge-high" aria-hidden="true"></i>
            <span>{{ $labels['teacher_dashboard'] ?? 'داشبورد معلم' }}</span>
        </a>

        @if(site('features.teacher_panel', false))
            <a
                href="{{ route('teacher.students') }}"
                class="sidebar-link {{ request()->routeIs('teacher.students*') ? 'active' : '' }}"
            >
                <i class="fas fa-user-group" aria-hidden="true"></i>
                <span>{{ $labels['teacher_students'] ?? 'دانش‌آموزان' }}</span>
            </a>
        @endif

        @if(site('features.teacher_materials', false))
            <a
                href="{{ route('teacher.materials.index') }}"
                class="sidebar-link {{ request()->routeIs('teacher.materials*') ? 'active' : '' }}"
            >
                <i class="fas fa-book-open-reader" aria-hidden="true"></i>
                <span>{{ $labels['teacher_materials'] ?? 'جزوه‌ها' }}</span>
            </a>
        @endif

        @if(site('features.teacher_assignments', false))
            <a
                href="{{ route('teacher.assignments.index') }}"
                class="sidebar-link {{ request()->routeIs('teacher.assignments*') ? 'active' : '' }}"
            >
                <i class="fas fa-tasks" aria-hidden="true"></i>
                <span>{{ $labels['teacher_assignments'] ?? 'تکالیف' }}</span>
            </a>
        @endif

        @if(site('features.teacher_schedule', false))
            <a
                href="{{ route('teacher.schedule.index') }}"
                class="sidebar-link {{ request()->routeIs('teacher.schedule*') ? 'active' : '' }}"
            >
                <i class="fas fa-calendar-week" aria-hidden="true"></i>
                <span>{{ $labels['teacher_schedule'] ?? 'برنامه کلاسی' }}</span>
            </a>
        @endif

        {{-- Lesson library: lessons_route() keeps this in step with the topnav
             and with the consultant panel's identical link. --}}
        @if(\App\Support\Lessons\LessonAccess::staffCanAuthor())
            <a
                href="{{ lessons_route('index') }}"
                class="sidebar-link {{ request()->routeIs('*.lessons.*') ? 'active' : '' }}"
            >
                <i class="fas fa-book-open-reader" aria-hidden="true"></i>
                <span>{{ $labels['lesson_library'] ?? 'کتاب و درس' }}</span>
            </a>
        @endif
    </nav>

    <div class="sidebar-user topnav-user">
        <div class="sidebar-account">
            <div class="sidebar-account-id">
                <span class="sidebar-avatar" aria-hidden="true">{{ mb_substr(auth()->user()?->name ?? $tenant['short_name'], 0, 1) }}</span>
                <span class="sidebar-account-copy">
                    <strong>{{ auth()->user()?->name ?? $tenant['short_name'] }}</strong>
                    <small>حساب کاربری</small>
                </span>
            </div>
            <div class="sidebar-account-actions">
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

        <form method="POST" action="{{ route('logout') }}" data-router="off">
            @csrf
            <button type="submit" class="topnav-icon-btn" title="خروج از حساب" aria-label="خروج از حساب">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            </button>
        </form>
            </div>
        </div>
    </div>
</aside>
