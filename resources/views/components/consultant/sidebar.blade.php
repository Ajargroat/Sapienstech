{{--
    Consultant sidebar shell: the same tabs as the topnav, stacked in a fixed
    rail on the inline-start side. Chosen per tenant via theme.layout.shell_nav
    (Appearance studio → «جای منوی پنل»).

    The links carry `sidebar-link`, which page-router syncs for the active
    state exactly like `.topnav-link`; the user controls reuse the topnav
    classes so the theme toggle, settings dropdown and their delegated JS
    keep working unchanged.
--}}
<header class="sidebar-mobile-bar">
    <a href="{{ route('consultant.dashboard') }}" class="brand">
        <span class="brand-mark brand-mark-small"></span>
        <strong>{{ $tenant['short_name'] }}</strong>
    </a>
    <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="منو">
        <span class="sidebar-toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span>
    </button>
</header>
<div class="sidebar-scrim" data-sidebar-scrim hidden></div>
<aside id="app-sidebar" class="consultant-sidebar" aria-label="ناوبری پنل">
    <div class="sidebar-brand">
        <a href="{{ route('consultant.dashboard') }}" class="brand">
            <span class="brand-mark brand-mark-small"></span>
            <span class="sidebar-brand-copy">
                <strong>{{ $tenant['short_name'] }}</strong>
                <small>پنل مدیریت</small>
            </span>
        </a>
    </div>

    <nav class="sidebar-links" aria-label="ناوبری اصلی">
        <span class="sidebar-section-label">فضای کاری</span>
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

        @if(site('features.student_join_requests', false) && \Illuminate\Support\Facades\Route::has('consultant.requests.index'))
        @php
            // Mirrors the topnav badge: pending join requests the tenant should see.
            $pendingJoinRequests = \App\Models\StudentJoinRequest::query()
                ->where('status', \App\Models\StudentJoinRequest::STATUS_PENDING)
                ->count();
        @endphp
        <a
            href="{{ route('consultant.requests.index') }}"
            class="sidebar-link sidebar-link--badged {{ request()->routeIs('consultant.requests*') ? 'active' : '' }}"
        >
            <i class="fas fa-inbox" aria-hidden="true"></i>
            <span>درخواست‌ها</span>
            @if($pendingJoinRequests)
                <span class="topnav-notif-badge">{{ persian_digits($pendingJoinRequests) }}</span>
            @endif
        </a>
        @endif
        @if(site('features.consultation_requests', false) && \Illuminate\Support\Facades\Route::has('consultant.consultations.index'))
        @php
            // Mirrors the topnav badge: pending consultation requests the
            // tenant should see.
            $pendingConsultationRequests = \App\Models\ConsultationRequest::query()
                ->where('status', \App\Models\ConsultationRequest::STATUS_PENDING)
                ->count();
        @endphp
        <a
            href="{{ route('consultant.consultations.index') }}"
            class="sidebar-link sidebar-link--badged {{ request()->routeIs('consultant.consultations*') ? 'active' : '' }}"
        >
            <i class="fas fa-inbox" aria-hidden="true"></i>
            <span>درخواست مشاوره</span>
            @if($pendingConsultationRequests)
                <span class="topnav-notif-badge">{{ persian_digits($pendingConsultationRequests) }}</span>
            @endif
        </a>
        @endif

        @if(\App\Support\Lessons\LessonAccess::staffCanAuthor())
        {{-- Lesson library (کتاب و درس) — the staff surface for importing a
             book PDF and authoring lessons; same gate as the topnav twin. --}}
        <a
            href="{{ lessons_route('index') }}"
            class="sidebar-link {{ request()->routeIs('consultant.lessons*') || request()->routeIs('teacher.lessons*') ? 'active' : '' }}"
        >
            <i class="fas fa-book-open" aria-hidden="true"></i>
            <span>{{ $labels['lesson_library'] ?? 'کتاب و درس' }}</span>
        </a>
        @endif

        {{-- Sidebar mirrors the topnav; bulk assignment lives in the
             dashboard filter popover, so the nav entry is gone from both. --}}
    </nav>

    <div class="sidebar-user topnav-user">
        <div class="sidebar-account">
            <div class="sidebar-account-id">
                <span class="sidebar-avatar" aria-hidden="true">{{ mb_substr(auth()->user()?->name ?? $tenant['short_name'], 0, 1) }}</span>
                <span class="sidebar-account-copy">
                    <strong>{{ auth()->user()?->name ?? $tenant['short_name'] }}</strong>
                    <small>حساب کاربری</small>
                </span>
            </div>
            <div class="sidebar-account-actions">
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
        </div>
    </div>
</aside>
