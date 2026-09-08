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

        <div class="topnav-dropdown" data-topnav-dropdown>
            <button
                type="button"
                class="topnav-icon-btn"
                title="{{ $labels['settings'] ?? 'تنظیمات' }}"
                aria-label="{{ $labels['settings'] ?? 'تنظیمات' }}"
                aria-haspopup="true"
                aria-expanded="false"
            >
                <i class="fas fa-user"></i>
            </button>

            <div class="topnav-dropdown-menu">
                <div class="topnav-dropdown-head">
                    <strong>{{ auth('student')->user()->name ?? 'دانش‌آموز' }}</strong>
                </div>
                @foreach(\App\Support\SettingsTabs::visible('student') as $tab)
                    <a
                        href="{{ route($tab['route']) }}"
                        class="topnav-dropdown-link {{ request()->routeIs($tab['route']) ? 'is-active' : '' }}"
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</aside>
