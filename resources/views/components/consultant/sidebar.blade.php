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

        @if(!tenant()?->hierarchy_type || auth()->user()?->isTenantOwner())
        <a
            href="{{ route('consultant.blog.index') }}"
            class="sidebar-link {{ request()->routeIs('consultant.blog.*') ? 'active' : '' }}"
        >
            <i class="fas fa-newspaper" aria-hidden="true"></i>
            <span>{{ $labels['blog_management'] ?? 'وبلاگ' }}</span>
        </a>
        @endif

        <a
            href="{{ route('consultant.direct-chat') }}"
            class="sidebar-link {{ request()->routeIs('consultant.direct-chat') ? 'active' : '' }}"
        >
            <i class="fas fa-comments" aria-hidden="true"></i>
            <span>{{ $labels['direct_chat'] ?? 'گفتگوی مستقیم' }}</span>
            @include('partials.chat.unread-badge')
        </a>

        @if(site('features.deals', false) && \Illuminate\Support\Facades\Route::has('consultant.deals.index'))
        <a
            href="{{ route('consultant.deals.index') }}"
            class="sidebar-link {{ request()->routeIs('consultant.deals*') ? 'active' : '' }}"
        >
            <i class="fas fa-sack-dollar" aria-hidden="true"></i>
            <span>تمدید و پرداخت</span>
        </a>
        @endif

        {{-- Sidebar mirrors the topnav; bulk assignment lives in the
             dashboard filter popover, so the nav entry is gone from both. --}}
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

        @if(site('features.theme_studio', false) && \Illuminate\Support\Facades\Route::has('studio.index'))
        {{-- The Theme Studio is a standalone page (new tab): its editor
             chrome must not live inside the tenant-themed hub. --}}
        @if(!tenant()?->hierarchy_type || auth()->user()?->isTenantOwner())
        <a
            href="{{ route('studio.index') }}"
            class="topnav-icon-btn"
            target="_blank" rel="noopener" data-router="off"
            title="استودیوی ظاهر"
            aria-label="استودیوی ظاهر"
        >
            <i class="fas fa-wand-magic-sparkles"></i>
        </a>
        @endif
        @endif

        {{-- Profile hub (mirrors the topnav): direct link, no dropdown. --}}
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

        @if(site('features.deals', false) && \Illuminate\Support\Facades\Route::has('consultant.notifications'))
            @php
                $notifDueCount = \App\Models\StudentDeal::query()
                    ->whereNull('renewed_at')
                    ->where('decision', '!=', \App\Models\StudentDeal::DECISION_WITHDRAW)
                    ->whereDate('ends_on', '<', \Illuminate\Support\Carbon::today())
                    ->count();
                $notifPaymentCount = auth()->user()?->isTenantAdmin()
                    ? \App\Models\DealPayment::query()->where('status', \App\Models\DealPayment::STATUS_PENDING)->count()
                    : 0;
                $notifCount = $notifDueCount + $notifPaymentCount;
            @endphp
            <a
                href="{{ route('consultant.notifications') }}"
                class="topnav-icon-btn {{ request()->routeIs('consultant.notifications') ? 'active' : '' }}"
                title="اطلاعیه‌ها"
                aria-label="اطلاعیه‌ها"
                @if(request()->routeIs('consultant.notifications')) aria-current="page" @endif
            >
                <i class="fas fa-bell"></i>
                @if($notifCount)
                    <span class="topnav-notif-badge">{{ persian_digits($notifCount) }}</span>
                @endif
            </a>
        @endif
    </div>
</aside>
