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
            <button type="button" id="theme-toggle-btn" class="theme-toggle" title="تغییر حالت روشن/تاریک" aria-label="تغییر حالت روشن/تاریک">
                <i class="fas fa-moon"></i>
                <i class="fas fa-sun"></i>
            </button>
            <span class="user-avatar user-avatar-sm">
                <i class="fas fa-user"></i>
            </span>
            <span class="user-name">{{ session('username', 'مدیر سیستم') }}</span>
        </div>
    </div>
</header>
