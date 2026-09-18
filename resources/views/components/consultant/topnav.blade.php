{{--
    Tenant-resolved unread badge for the direct-chat nav item; live-updated
    on the chat page by direct-chat.js. Shared partial keeps the topnav and
    sidebar in lockstep with the student portal's nav.
--}}
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

            @if(!tenant()?->hierarchy_type || auth()->user()?->isTenantOwner())
            <a
                href="{{ route('consultant.blog.index') }}"
                class="topnav-link {{ request()->routeIs('consultant.blog.*') ? 'active' : '' }}"
            >
                {{ $labels['blog_management'] ?? 'وبلاگ' }}
            </a>
            @endif

            <a
                href="{{ route('consultant.direct-chat') }}"
                class="topnav-link {{ request()->routeIs('consultant.direct-chat') ? 'active' : '' }}"
            >
                {{ $labels['direct_chat'] ?? 'گفتگوی مستقیم' }}
                @include('partials.chat.unread-badge')
            </a>

            @if(site('features.deals', false) && \Illuminate\Support\Facades\Route::has('consultant.deals.index'))
            <a
                href="{{ route('consultant.deals.index') }}"
                class="topnav-link {{ request()->routeIs('consultant.deals*') ? 'active' : '' }}"
            >
                تمدید و پرداخت
            </a>
            @endif

            {{-- Bulk assignment now lives inside the dashboard filter popover;
                 the «اقدامات گروهی» nav entry is intentionally gone. The
                 تاریخچه page is still reachable from the popover footer. --}}
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

            {{-- Profile is the hub now (account, appearance studio, chat
                 settings): the button links straight into it — no dropdown. --}}
            @if(!tenant()?->hierarchy_type || auth()->user()?->isTenantOwner())
            <a
                href="{{ route('consultant.settings.profile') }}"
                class="topnav-icon-btn topnav-profile {{ request()->routeIs('consultant.settings.profile') ? 'active' : '' }}"
                title="{{ $labels['settings_profile'] ?? 'پروفایل' }}"
                aria-label="{{ $labels['settings_profile'] ?? 'پروفایل' }}"
                @if(request()->routeIs('consultant.settings.profile')) aria-current="page" @endif
            >
                <i class="fas fa-user"></i>
            </a>
            @else
            <form method="POST" action="{{ route('logout') }}" data-router="off">
                @csrf
                <button type="submit" class="topnav-icon-btn" title="خروج از حساب" aria-label="خروج از حساب">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                </button>
            </form>
            @endif

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
