{{-- Teacher panel top navigation. Shares the consultant shell's nav styles
     (.consultant-topnav and friends) so all three portals stay visually
     identical. Links render only when their tenant feature flag is on. --}}
<header class="consultant-topnav">
    <div class="topnav-inner">
        <a href="{{ route('teacher.dashboard') }}" class="brand">
            <span class="brand-mark brand-mark-small"></span>
            <strong>{{ $tenant['short_name'] }}</strong>
        </a>

        <nav class="topnav-links">
            <a
                href="{{ route('teacher.dashboard') }}"
                class="topnav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}"
            >
                {{ $labels['teacher_dashboard'] ?? 'داشبورد معلم' }}
            </a>

            @if(site('features.teacher_panel', false))
                <a
                    href="{{ route('teacher.students') }}"
                    class="topnav-link {{ request()->routeIs('teacher.students*') ? 'active' : '' }}"
                >
                    {{ $labels['teacher_students'] ?? 'دانش‌آموزان' }}
                </a>
            @endif

            @if(site('features.teacher_materials', false))
                <a
                    href="{{ route('teacher.materials.index') }}"
                    class="topnav-link {{ request()->routeIs('teacher.materials*') ? 'active' : '' }}"
                >
                    {{ $labels['teacher_materials'] ?? 'جزوه‌ها' }}
                </a>
            @endif

            @if(site('features.teacher_assignments', false))
                <a
                    href="{{ route('teacher.assignments.index') }}"
                    class="topnav-link {{ request()->routeIs('teacher.assignments*') ? 'active' : '' }}"
                >
                    {{ $labels['teacher_assignments'] ?? 'تکالیف' }}
                </a>
            @endif

            @if(site('features.teacher_schedule', false))
                <a
                    href="{{ route('teacher.schedule.index') }}"
                    class="topnav-link {{ request()->routeIs('teacher.schedule*') ? 'active' : '' }}"
                >
                    {{ $labels['teacher_schedule'] ?? 'برنامه کلاسی' }}
                </a>
            @endif
        </nav>

        <div class="topnav-user">
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
</header>
