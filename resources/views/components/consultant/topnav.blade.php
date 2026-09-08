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
                href="{{ route('consultant.settings.blog.index') }}"
                class="topnav-link {{ request()->routeIs('consultant.settings.blog.*') ? 'active' : '' }}"
            >
                {{ $labels['blog_management'] ?? 'وبلاگ' }}
            </a>

            <a
                href="{{ route('consultant.direct-chat') }}"
                class="topnav-link {{ request()->routeIs('consultant.direct-chat') ? 'active' : '' }}"
            >
                {{ $labels['direct_chat'] ?? 'گفتگوی مستقیم' }}
            </a>

            @if(site('features.bulk_actions', false))
                <a
                    href="{{ route('consultant.bulk.exams') }}"
                    class="topnav-link {{ request()->routeIs('consultant.bulk.*') ? 'active' : '' }}"
                >
                    {{ $labels['bulk_actions'] ?? 'اقدامات گروهی' }}
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
                        <strong>{{ auth()->user()->name ?? session('username', 'مدیر سیستم') }}</strong>
                    </div>
                    @foreach(\App\Support\SettingsTabs::visible('consultant') as $tab)
                        <a
                            href="{{ route($tab['route']) }}"
                            class="topnav-dropdown-link {{ request()->routeIs($tab['route']) ? 'is-active' : '' }}"
                        >
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <button
                type="button"
                class="topnav-icon-btn"
                title="اطلاعیه‌ها"
                aria-label="اطلاعیه‌ها"
            >
                <i class="fas fa-bell"></i>
            </button>
        </div>
    </div>
</header>
