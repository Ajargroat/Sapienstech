{{--
    Consultant sidebar shell: the same tabs as the topnav, stacked in a fixed
    rail on the inline-start side. Chosen per tenant via theme.layout.shell_nav
    (Appearance studio → «جای منوی پنل»).

    The links carry `sidebar-link`, which page-router syncs for the active
    state exactly like `.topnav-link`; the user controls reuse the topnav
    classes so the theme toggle, settings dropdown and their delegated JS
    keep working unchanged.
--}}
<aside class="consultant-sidebar">
    <a href="{{ route('consultant.dashboard') }}" class="brand">
        <span class="brand-mark brand-mark-small"></span>
        <strong>{{ $tenant['short_name'] }}</strong>
    </a>

    <nav class="sidebar-links" aria-label="ناوبری اصلی">
        <a
            href="{{ route('consultant.dashboard') }}"
            class="sidebar-link {{ request()->routeIs('consultant.dashboard') || request()->routeIs('consultant.student.*') ? 'active' : '' }}"
        >
            <i class="fas fa-gauge-high" aria-hidden="true"></i>
            <span>{{ $labels['dashboard'] ?? 'داشبورد' }}</span>
        </a>

        <a
            href="{{ route('consultant.settings.blog.index') }}"
            class="sidebar-link {{ request()->routeIs('consultant.settings.blog.*') ? 'active' : '' }}"
        >
            <i class="fas fa-newspaper" aria-hidden="true"></i>
            <span>{{ $labels['blog_management'] ?? 'وبلاگ' }}</span>
        </a>

        <a
            href="{{ route('consultant.direct-chat') }}"
            class="sidebar-link {{ request()->routeIs('consultant.direct-chat') ? 'active' : '' }}"
        >
            <i class="fas fa-comments" aria-hidden="true"></i>
            <span>{{ $labels['direct_chat'] ?? 'گفتگوی مستقیم' }}</span>
        </a>

        @if(site('features.bulk_actions', false))
            <a
                href="{{ route('consultant.bulk.exams') }}"
                class="sidebar-link {{ request()->routeIs('consultant.bulk.*') ? 'active' : '' }}"
            >
                <i class="fas fa-layer-group" aria-hidden="true"></i>
                <span>{{ $labels['bulk_actions'] ?? 'اقدامات گروهی' }}</span>
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
</aside>
