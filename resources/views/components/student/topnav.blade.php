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

            @if(site('features.report_cards', false))
                <a
                    href="{{ route('student.report-card') }}"
                    class="topnav-link {{ request()->routeIs('student.report-card') ? 'active' : '' }}"
                >
                    کارنامه
                </a>
            @endif

            @if(site('features.student_chat', false) && \Illuminate\Support\Facades\Route::has('student.direct-chat.page'))
                <a
                    href="{{ route('student.direct-chat.page') }}"
                    class="topnav-link {{ request()->routeIs('student.direct-chat*') ? 'active' : '' }}"
                >
                    {{ $labels['student_chat'] ?? 'گفتگو' }}
                    @include('partials.chat.unread-badge', ['chatActorKey' => 'student'])
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

            {{-- Profile is the hub now: direct link, no dropdown (mirrors
                 the consultant topnav). --}}
            <a
                href="{{ route('student.settings.profile') }}"
                class="topnav-icon-btn topnav-profile {{ request()->routeIs('student.settings.profile') ? 'active' : '' }}"
                title="{{ $labels['settings_profile'] ?? 'پروفایل' }}"
                aria-label="{{ $labels['settings_profile'] ?? 'پروفایل' }}"
                @if(request()->routeIs('student.settings.profile')) aria-current="page" @endif
            >
                <i class="fas fa-user"></i>
            </a>
        </div>
    </div>
</header>
