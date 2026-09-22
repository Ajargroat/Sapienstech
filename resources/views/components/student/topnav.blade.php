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

            @if(site('features.student_materials', false) && \Illuminate\Support\Facades\Route::has('student.lessons.index'))
                <a
                    href="{{ route('student.lessons.index') }}"
                    class="topnav-link {{ request()->routeIs('student.lessons*') ? 'active' : '' }}"
                >
                    درس‌ها
                </a>
            @endif

            @if(site('features.student_assignments', false) && \Illuminate\Support\Facades\Route::has('student.assignments.index'))
                <a
                    href="{{ route('student.assignments.index') }}"
                    class="topnav-link {{ request()->routeIs('student.assignments*') ? 'active' : '' }}"
                >
                    تکالیف
                </a>
            @endif

            @if(site('features.student_timetable', false) && \Illuminate\Support\Facades\Route::has('student.timetable'))
                <a
                    href="{{ route('student.timetable') }}"
                    class="topnav-link {{ request()->routeIs('student.timetable') ? 'active' : '' }}"
                >
                    جدول کلاس
                </a>
            @endif

            @if(site('features.deals', false) && \Illuminate\Support\Facades\Route::has('student.deals.index'))
                <a
                    href="{{ route('student.deals.index') }}"
                    class="topnav-link {{ request()->routeIs('student.deals*') ? 'active' : '' }}"
                >
                    دورهٔ من
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
            @if(site('features.deals', false) && \Illuminate\Support\Facades\Route::has('student.notifications'))
                @php
                    $notifs = \App\Models\DealNotification::query()
                        ->where('student_id', auth('student')->id())
                        ->with('deal:id,ends_on')
                        ->orderByRaw('read_at is null desc')
                        ->orderByDesc('created_at')
                        ->limit(8)
                        ->get();
                    $notifUnread = $notifs->whereNull('read_at')->count();
                @endphp
                <div class="topnav-notif">
                    <button
                        type="button"
                        class="topnav-icon-btn"
                        id="topnav-notif-btn"
                        title="اطلاعیه‌ها"
                        aria-label="اطلاعیه‌ها"
                        aria-haspopup="true"
                    >
                        <i class="fas fa-bell"></i>
                        @if($notifUnread)
                            <span class="topnav-notif-badge">{{ persian_digits($notifUnread) }}</span>
                        @endif
                    </button>
                    <div class="notif-panel" id="topnav-notif-panel" hidden>
                        <div class="notif-panel-head">
                            <b>اطلاعیه‌ها</b>
                            <a href="{{ route('student.notifications') }}" data-router="off">مشاهدهٔ همه</a>
                        </div>
                        <div class="notif-panel-body">
                            @forelse($notifs as $notification)
                                <a href="{{ route('student.notifications') }}" class="notif-item {{ $notification->read_at ? '' : 'notif-item--unread' }}" data-router="off">
                                    @if($notification->kind === 'payment')<i class="fas fa-sack-dollar"></i>
                                    @elseif($notification->kind === 'decision')<i class="fas fa-comment"></i>
                                    @else <i class="fas fa-bell"></i> @endif
                                    <span>
                                        {{ \Illuminate\Support\Str::limit($notification->message, 72) }}
                                        <small>{{ persian_digits($notification->created_at->format('Y/m/d H:i')) }}</small>
                                    </span>
                                </a>
                            @empty
                                <div class="notif-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    اطلاعیه‌ای نیست.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

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
