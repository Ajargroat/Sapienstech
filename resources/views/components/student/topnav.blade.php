{{-- Student portal top navigation. Shares the consultant shell's nav styles
     (.consultant-topnav and friends) so both portals stay visually identical. --}}
<header class="consultant-topnav">
    <div class="topnav-inner">
        <a href="{{ route('student.dashboard') }}" class="brand">
            <span class="brand-mark brand-mark-small"></span>
            <strong>{{ $tenant['short_name'] }}</strong>
        </a>

        <nav class="topnav-links">
            <a
                href="{{ route('student.dashboard') }}"
                class="topnav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}"
            >
                داشبورد
            </a>
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

            <span
                class="topnav-icon-btn"
                title="{{ auth('student')->user()->name ?? 'دانش‌آموز' }}"
                aria-label="کاربر"
            >
                <i class="fas fa-user"></i>
            </span>
        </div>

        @auth('student')
            <form method="POST" action="{{ route('student.logout') }}" class="inline">
                @csrf
                <button
                    type="submit"
                    class="topnav-icon-btn"
                    title="خروج"
                    aria-label="خروج"
                >
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        @endauth
    </div>
</header>
