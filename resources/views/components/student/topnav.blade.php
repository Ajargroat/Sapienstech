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
    </div>
</header>
