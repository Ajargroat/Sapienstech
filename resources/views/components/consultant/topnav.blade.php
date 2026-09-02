<header class="consultant-topnav">
    <div class="topnav-inner">
        <a href="{{ route('consultant.dashboard') }}" class="brand">
            <span class="brand-mark brand-mark-small"></span>
            <strong>{{ $tenant['short_name'] }}</strong>
        </a>

        <nav class="topnav-links">
            <a
                href="{{ route('consultant.dashboard') }}"
                class="topnav-link {{ request()->routeIs('consultant.dashboard') || request()->routeIs('consultant.student.*') ? 'active' : '' }}"
            >
                {{ $labels['dashboard'] ?? 'داشبورد' }}
            </a>

            <a
                href="{{ route('consultant.blog') }}"
                class="topnav-link {{ request()->routeIs('consultant.blog') ? 'active' : '' }}"
            >
                {{ $labels['blog_management'] ?? 'وبلاگ' }}
            </a>

            <a
                href="{{ route('consultant.direct-chat') }}"
                class="topnav-link {{ request()->routeIs('consultant.direct-chat') ? 'active' : '' }}"
            >
                {{ $labels['direct_chat'] ?? 'گفتگوی مستقیم' }}
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
                title="{{ session('username', 'مدیر سیستم') }}"
                aria-label="کاربر"
            >
                <i class="fas fa-user"></i>
            </span>

            <button
                type="button"
                class="topnav-icon-btn"
                title="اطلاعیه‌ها"
                aria-label="اطلاعیه‌ها"
            >
                <i class="fas fa-bell"></i>
            </button>
        </div>

        @auth
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button
                    type="submit"
                    class="text-sm text-gray-600 hover:text-red-600"
                >
                    خروج
                </button>
            </form>
        @endauth
    </div>
</header>
