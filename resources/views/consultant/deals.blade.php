{{--
    Deal renewal manager — the panel side of تمدید و پرداخت.

    Rendered by App\Http\Controllers\Consultant\DealController::index()
    through `consultant.deals.index`. Reimplements the demo-feature mock
    (demo-feature.html) on the real schema:

    - Summary stats (active periods, overdue, receipts awaiting verification,
      collected) — demo's hero stats.
    - Consultant cards (demo's «مشاوران» view): one card per consultant with
      their open-deal rollup; clicking drills into the filtered deals list.
    - Pending receipts with owner verification (demo's approval flow).
    - «سررسیدهای تمدید» — every open deal in/past the decision window.
    - «پیام‌های ارسالی» — the sent-reminder log with per-deal detail drawer
      (demo's drawer + message history).
    - Filters + full paginated deals list with status chips (demo's chips).

    Reuses the settings/blog visual language (.settings-card, .blog-status,
    .empty-state, .blog-pager) plus the deal-specific styles in app.css.
--}}
@extends('layouts.consultant')

@section('content')
@php
    $today = \Illuminate\Support\Carbon::today();
    $statusLabels = [
        'all' => 'همه', 'due' => 'سررسید/معوق', 'soon' => 'نزدیک به پایان',
        'continue' => 'ادامه داده', 'withdraw' => 'منصرف شده',
        'pending' => 'پرداخت در انتظار', 'renewed' => 'تمدید شده',
    ];
    $hueClasses = ['deal-hue-1','deal-hue-2','deal-hue-3','deal-hue-4','deal-hue-5','deal-hue-6'];
@endphp

<div class="settings-cards">

    {{-- Summary ------------------------------------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-sack-dollar" aria-hidden="true"></i> تمدید و پرداخت</h3>
        <p class="settings-card-text">وضعیت دوره‌های دانش‌آموزان، تصمیم آن‌ها (ادامه یا انصراف) و رسیدهای در انتظار تأیید.</p>

        <div class="deal-stats">
            <div class="deal-stat deal-stat--active">
                <span class="deal-stat-lbl"><i class="fas fa-users" aria-hidden="true"></i> دوره‌های فعال</span>
                <span class="deal-stat-num">{{ persian_digits($summary['total']) }}</span>
            </div>
            <div class="deal-stat deal-stat--due">
                <span class="deal-stat-lbl"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> سررسید گذشته</span>
                <span class="deal-stat-num">{{ persian_digits($summary['due']) }}</span>
            </div>
            <div class="deal-stat deal-stat--pending">
                <span class="deal-stat-lbl"><i class="fas fa-hourglass-half" aria-hidden="true"></i> پرداخت در انتظار تأیید</span>
                <span class="deal-stat-num">{{ persian_digits($summary['pending']) }}</span>
            </div>
            <div class="deal-stat deal-stat--money">
                <span class="deal-stat-lbl"><i class="fas fa-coins" aria-hidden="true"></i> مجموع دریافت‌شده</span>
                <span class="deal-stat-num">{{ persian_digits(number_format($summary['collected'])) }} <small>تومان</small></span>
            </div>
        </div>

        @if(session('status'))
            <div class="mt-4 rounded-xl border border-[var(--c-border)] p-3 text-sm" style="color:var(--c-success,#34D399)">
                {{ session('status') }}
            </div>
        @endif

        @if($canManage)
        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-bold" style="color:var(--c-primary)">+ ثبت دورهٔ جدید</summary>
            <form method="POST" action="{{ route('consultant.deals.store') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-5" data-router="off">
                @csrf
                <label class="text-xs text-[var(--c-muted)]">
                    دانش‌آموز
                    <select name="student_id" required class="mt-1 w-full rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                        @foreach($students as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}@if($s->grade) — {{ $s->grade }}@endif</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-[var(--c-muted)]">
                    مشاور
                    <select name="consultant_id" class="mt-1 w-full rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                        <option value="">—</option>
                        @foreach($consultants as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs text-[var(--c-muted)]">
                    شروع
                    <input type="date" name="starts_on" required value="{{ now()->toDateString() }}"
                           class="mt-1 w-full rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                </label>
                <label class="text-xs text-[var(--c-muted)]">
                    پایان
                    <input type="date" name="ends_on" required min="{{ now()->toDateString() }}"
                           class="mt-1 w-full rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                </label>
                <label class="text-xs text-[var(--c-muted)]">
                    مبلغ (تومان)
                    <input type="number" name="amount" required min="0" step="1000" placeholder="مثلاً 5000000"
                           class="mt-1 w-full rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                </label>
                <div class="md:col-span-5">
                    <button type="submit" class="blog-status-toggle rounded-xl px-4 py-2 text-sm font-bold"
                            style="background:var(--c-primary);color:var(--c-on-primary,#fff)">ثبت دوره</button>
                </div>
            </form>
        </details>
        @endif
    </section>

    {{-- Consultant cards (demo's «مشاوران») ----------------------------- --}}
    @if($consultantGroups->isNotEmpty())
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-user-tie" aria-hidden="true"></i> مشاوران</h3>
        <p class="settings-card-text">وضعیت تمدید دوره‌های هر مشاور؛ برای مشاهدهٔ فهرست دانش‌آموزان روی کارت بزنید.</p>

        <div class="deal-consultant-grid">
            @foreach($consultantGroups as $i => $group)
                @php
                    $c = $group['consultant'];
                    $hue = $hueClasses[$i % count($hueClasses)];
                    $active = $group['total'] - $group['withdrawn'];
                    $pOk = $active > 0 ? round($group['ok'] / $active * 100) : 0;
                    $pSoon = $active > 0 ? round($group['soon'] / $active * 100) : 0;
                @endphp
                <a href="{{ route('consultant.deals.index', ['consultant_id' => $c->id]) }}" class="deal-con-card" data-router="off">
                    <span class="deal-ava {{ $hue }}" aria-hidden="true">{{ mb_substr($c->name, 0, 1) }}</span>
                    <span class="deal-con-info">
                        <b>{{ $c->name }}</b>
                        <small>{{ $c->role === \App\Models\User::ROLE_TENANT_ADMIN ? 'مدیر مجموعه' : 'مشاور' }}</small>
                    </span>
                    <span class="deal-metric"><i>{{ persian_digits($group['total']) }}</i><small>دانش‌آموز</small></span>
                    <span class="deal-metric {{ $group['due'] ? 'deal-metric--warn' : 'deal-metric--ok' }}">
                        <i>{{ persian_digits($group['due']) }}</i><small>سررسید فوری</small>
                    </span>
                    <span class="deal-metric"><i>{{ persian_digits(number_format($group['revenue'])) }}</i><small>تومان / دوره</small></span>
                    <span class="deal-mix">
                        <span class="deal-mixbar">
                            <i class="a" style="width:{{ $pOk }}%"></i>
                            <i class="b" style="width:{{ $pSoon }}%"></i>
                            <i class="c" style="width:{{ max(100 - $pOk - $pSoon, 0) }}%"></i>
                        </span>
                        <span class="deal-mix-lbl">وضعیت تمدیدها</span>
                    </span>
                    <span class="deal-go" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Payments awaiting verification (owner only) -------------------- --}}
    @if($canManage && $pendingPayments->isNotEmpty())
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-hourglass-half" aria-hidden="true"></i> رسیدهای در انتظار تأیید</h3>
        <p class="settings-card-text">واریز را با حساب مجموعه تطبیق دهید؛ تأیید، دوره را می‌بندد و دورهٔ جدید را با همان شرایط باز می‌کند.</p>
        <div class="flex flex-col gap-3">
            @foreach($pendingPayments as $payment)
                @php $deal = $payment->deal; @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--c-border)] p-4">
                    <div>
                        <b class="block">{{ $deal->student->name }}</b>
                        <span class="text-xs text-[var(--c-muted)]">
                            رسید: {{ $payment->reference }} ·
                            {{ persian_digits(number_format($payment->amount)) }} تومان ·
                            ارسال: {{ persian_digits($payment->created_at->format('Y/m/d')) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('consultant.deals.payments.review', $payment) }}" data-router="off"
                              onsubmit="return confirm('واریز تأیید و دوره تمدید شود؟');">
                            @csrf
                            <input type="hidden" name="status" value="paid">
                            <button type="submit" class="icon-action icon-action--confirm" title="تأیید و تمدید">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('consultant.deals.payments.review', $payment) }}" data-router="off"
                              onsubmit="return confirm('این رسید رد شود؟');">
                            @csrf
                            <input type="hidden" name="status" value="rejected">
                            <input type="hidden" name="review_note" value="واریزی یافت نشد">
                            <button type="submit" class="icon-action icon-action--danger" title="رد رسید">
                                <i class="fas fa-undo"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Due renewals (demo's «سررسیدهای تمدید») -------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-bell" aria-hidden="true"></i> سررسیدهای تمدید</h3>
        <p class="settings-card-text">دوره‌هایی که به {{ persian_digits($windowDays) }} روز پایانی رسیده یا از سررسید گذشته‌اند — از همهٔ مشاوران.</p>

        @if($dueDeals->count())
            <div class="deal-due-list">
                @foreach($dueDeals as $i => $deal)
                    @php
                        $left = $deal->daysLeft($today);
                        $hue = $hueClasses[$i % count($hueClasses)];
                    @endphp
                    <button type="button" class="deal-due-row" data-deal-drawer="{{ $deal->id }}">
                        <span class="deal-ava deal-ava--sm {{ $hue }}" aria-hidden="true">{{ mb_substr($deal->student->name, 0, 1) }}</span>
                        <span class="deal-due-name">
                            <b>{{ $deal->student->name }}</b>
                            <small>@if($deal->student->major){{ $deal->student->major }}@endif @if($deal->student->grade)· {{ $deal->student->grade }}@endif</small>
                        </span>
                        @if($deal->consultant)
                            <span class="deal-con-tag"><i class="fas fa-user"></i> {{ $deal->consultant->name }}</span>
                        @endif
                        <span class="deal-due-date">
                            <i class="fas fa-calendar-day" aria-hidden="true"></i>
                            {{ persian_digits($deal->ends_on->format('Y/m/d')) }}
                        </span>
                        <span class="blog-status"
                              @if($left < 0) style="color:var(--c-danger,#F87171);background:color-mix(in srgb,var(--c-danger,#F87171) 14%,transparent)"
                              @elseif($left === 0) style="color:var(--c-warning,#FBBF24);background:color-mix(in srgb,var(--c-warning,#FBBF24) 14%,transparent)"
                              @else style="color:var(--c-primary);background:color-mix(in srgb,var(--c-primary) 12%,transparent)" @endif>
                            @if($left < 0) {{ persian_digits(abs($left)) }} روز تأخیر
                            @elseif($left === 0) سررسید امروز
                            @else {{ persian_digits($left) }} روز مانده
                            @endif
                        </span>
                        <span class="deal-go" aria-hidden="true"><i class="fas fa-circle-info"></i></span>
                    </button>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>سررسید فوری‌ای نیست</h3>
                <p>هیچ دوره‌ای در پنجرهٔ {{ persian_digits($windowDays) }} روزهٔ پایانی یا معوق نیست.</p>
            </div>
        @endif
    </section>

    {{-- Sent reminders (demo's «پیام‌های ارسالی») ------------------------ --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-paper-plane" aria-hidden="true"></i> پیام‌های ارسالی</h3>
        <p class="settings-card-text">یادآوری‌هایی که برای دانش‌آموزان ثبت شده‌اند و وضعیت خوانده‌شدن آن‌ها.</p>

        @if($reminders->count())
            <div class="deal-msg-list">
                @foreach($reminders as $reminder)
                    <div class="deal-msg">
                        <span class="deal-ch-ic deal-ch-ic--sms" aria-hidden="true"><i class="fas fa-bell"></i></span>
                        <span class="deal-msg-body">
                            <span class="deal-msg-to">
                                <b>{{ $reminder->student->name ?? '—' }}</b>
                                <span class="deal-who">دانش‌آموز</span>
                                @if($reminder->deal)
                                    <span class="deal-msg-cnt">سررسید {{ persian_digits($reminder->deal->ends_on->format('Y/m/d')) }}</span>
                                @endif
                            </span>
                            <span class="deal-msg-text">{{ $reminder->message }}</span>
                            <span class="deal-msg-meta">
                                <span><i class="fas fa-clock" aria-hidden="true"></i> {{ persian_digits($reminder->created_at->format('Y/m/d H:i')) }}</span>
                                <span class="deal-msg-tag">تمدید اشتراک</span>
                                @if($reminder->read_at)
                                    <span class="deal-msg-ok"><i class="fas fa-check-double"></i> خوانده شد</span>
                                @else
                                    <span class="deal-msg-queue"><i class="fas fa-hourglass-half"></i> خوانده نشده</span>
                                @endif
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-paper-plane"></i>
                <h3>یادآوری‌ای ثبت نشده</h3>
                <p>از فهرست دوره‌ها یا سررسیدها، برای دانش‌آموز یادآوری بفرستید.</p>
            </div>
        @endif
    </section>

    {{-- Filters + deals list ------------------------------------------ --}}
    <section class="settings-card">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="settings-card-title !mb-0">دوره‌ها</h3>
            <form method="GET" action="{{ route('consultant.deals.index') }}" class="flex flex-wrap items-center gap-2" data-router="off">
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="نام دانش‌آموز"
                       class="rounded-xl border border-[var(--c-border)] bg-transparent px-3 py-2 text-sm text-[var(--c-text)]">
                <select name="consultant_id"
                        class="rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                    <option value="">همهٔ مشاوران</option>
                    @foreach($consultants as $c)
                        <option value="{{ $c->id }}" @if($filters['consultant_id'] === (int) $c->id) selected @endif>{{ $c->name }}</option>
                    @endforeach
                </select>
                <select name="status"
                        class="rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}" @if($filters['status'] === $key) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-xl border border-[var(--c-border)] px-3 py-2 text-sm">اعمال</button>
            </form>
        </div>

        {{-- Demo's status chips — quick filters with live counts. --}}
        <div class="deal-chips">
            @foreach($statusLabels as $key => $label)
                @php
                    $n = match($key) {
                        'all' => $summary['total'],
                        'due' => $summary['due'],
                        'pending' => $summary['pending'],
                        default => null,
                    };
                @endphp
                <a href="{{ route('consultant.deals.index', ['status' => $key, 'q' => $filters['q'], 'consultant_id' => $filters['consultant_id']]) }}"
                   class="deal-chip {{ $filters['status'] === $key ? 'on' : '' }}" data-router="off">
                    {{ $label }}
                    @if($n !== null)<i>{{ persian_digits($n) }}</i>@endif
                </a>
            @endforeach
        </div>

        @if($deals->count())
            <div class="mt-4 flex flex-col gap-3">
                @foreach($deals as $deal)
                    @php
                        $days = $deal->daysLeft();
                        $payment = $deal->payments->first();
                        $withdrawn = $deal->decision === App\Models\StudentDeal::DECISION_WITHDRAW;
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--c-border)] p-4 {{ $deal->renewed_at ? 'opacity-60' : '' }}">
                        <div class="min-w-40">
                            <b class="block">{{ $deal->student->name }}</b>
                            <span class="text-xs text-[var(--c-muted)]">
                                @if($deal->consultant){{ $deal->consultant->name }} · @endif
                                {{ persian_digits($deal->starts_on->format('Y/m/d')) }} تا {{ persian_digits($deal->ends_on->format('Y/m/d')) }} ·
                                {{ persian_digits(number_format($deal->amount)) }} تومان
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @if($deal->renewed_at)
                                <span class="blog-status blog-status--published"><i class="fas fa-redo"></i> تمدید شد</span>
                            @elseif($deal->decision === App\Models\StudentDeal::DECISION_WITHDRAW)
                                <span class="blog-status" style="color:var(--c-danger,#F87171);background:color-mix(in srgb,var(--c-danger,#F87171) 14%,transparent)"><i class="fas fa-undo"></i> انصراف</span>
                            @elseif($deal->decision === App\Models\StudentDeal::DECISION_CONTINUE)
                                <span class="blog-status blog-status--published"><i class="fas fa-check-circle"></i> ادامه</span>
                            @else
                                <span class="blog-status blog-status--draft"><i class="fas fa-hourglass-half"></i> بی‌تصمیم</span>
                            @endif

                            @php $left = $deal->daysLeft($today); @endphp
                            @if(! $deal->renewed_at && $deal->decision !== App\Models\StudentDeal::DECISION_WITHDRAW)
                                <span class="blog-status" @if($left < 0) style="color:var(--c-danger,#F87171);background:color-mix(in srgb,var(--c-danger,#F87171) 14%,transparent)"
                                      @elseif($left <= $windowDays) style="color:var(--c-warning,#FBBF24);background:color-mix(in srgb,var(--c-warning,#FBBF24) 14%,transparent)" @endif>
                                    @if($left < 0) {{ persian_digits(abs($left)) }} روز تأخیر
                                    @elseif($left === 0) سررسید امروز
                                    @else {{ persian_digits($left) }} روز مانده
                                    @endif
                                </span>
                            @endif

                            @if($payment)
                                <span class="blog-status blog-status--{{ $payment->status === 'paid' ? 'published' : 'draft' }}"
                                      title="وضعیت رسید">
                                    @if($payment->status === 'paid') رسید تأیید شد
                                    @elseif($payment->status === 'pending') رسید در انتظار
                                    @elseif($payment->status === 'rejected') رسید رد شد
                                    @else رسید لغو شد
                                    @endif
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1">
                            @if(!$deal->renewed_at && $deal->decision !== App\Models\StudentDeal::DECISION_WITHDRAW)
                                <form method="POST" action="{{ route('consultant.deals.remind', $deal) }}" data-router="off">
                                    @csrf
                                    <button type="submit" class="icon-action" title="یادآوری به دانش‌آموز">
                                        <i class="fas fa-comment"></i>
                                    </button>
                                </form>
                            @endif
                            @if($deal->next)
                                <span class="text-xs text-[var(--c-muted)]" title="دورهٔ بعدی">
                                    → {{ persian_digits($deal->next->starts_on->format('Y/m/d')) }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($deals->hasPages())
                <div class="blog-pager">{{ $deals->links() }}</div>
            @endif
        @else
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <h3>دوره‌ای ثبت نشده</h3>
                <p>با «ثبت دورهٔ جدید» نخستین دورهٔ مشاوره را برای یک دانش‌آموز باز کنید؛ در پایان هر دوره، تصمیم او را همین‌جا می‌بینید.</p>
            </div>
        @endif
    </section>
</div>

{{-- Per-deal detail drawer (demo's drawer): opened from the due rows. --}}
@foreach($dueDeals as $deal)
    @php
        $left = $deal->daysLeft($today);
        $total = max(1, (int) $deal->period_days);
        $elapsed = max(0, min($total, $total - max($left, 0)));
        $pct = (int) round($elapsed / $total * 100);
        $dealNotifications = $deal->notifications ?? collect();
    @endphp
    <template id="deal-drawer-{{ $deal->id }}">
        <div class="deal-drawer-head">
            <span class="deal-ava deal-hue-1" aria-hidden="true">{{ mb_substr($deal->student->name, 0, 1) }}</span>
            <div>
                <h3>{{ $deal->student->name }}</h3>
                <p>
                    @if($deal->student->major){{ $deal->student->major }}@endif
                    @if($deal->student->grade) · {{ $deal->student->grade }}@endif
                    @if($deal->consultant) — مشاور: {{ $deal->consultant->name }}@endif
                </p>
            </div>
        </div>
        <div class="deal-drawer-sec">
            <div class="deal-drawer-status">
                <span class="blog-status"
                      @if($left < 0) style="color:var(--c-danger,#F87171);background:color-mix(in srgb,var(--c-danger,#F87171) 14%,transparent)"
                      @elseif($left === 0) style="color:var(--c-warning,#FBBF24);background:color-mix(in srgb,var(--c-warning,#FBBF24) 14%,transparent)"
                      @else style="color:var(--c-primary);background:color-mix(in srgb,var(--c-primary) 12%,transparent)" @endif>
                    @if($left < 0) {{ persian_digits(abs($left)) }} روز تأخیر
                    @elseif($left === 0) سررسید امروز
                    @else {{ persian_digits($left) }} روز مانده
                    @endif
                </span>
                <span class="deal-drawer-due">سررسید: <b>{{ persian_digits($deal->ends_on->format('Y/m/d')) }}</b></span>
            </div>
            <div class="deal-prog-wrap">
                <div class="deal-prog-lbl"><span>پیشرفت دوره</span><span>{{ $deal->renewed_at ? 'دوره تمدید شد' : persian_digits($pct).'٪' }}</span></div>
                <div class="deal-prog"><i style="width:{{ $pct }}%"></i></div>
            </div>
        </div>
        <div class="deal-drawer-sec deal-drawer-grid">
            <div class="deal-drawer-item"><small>مبلغ دوره</small><b class="num">{{ persian_digits(number_format($deal->amount)) }} تومان</b></div>
            <div class="deal-drawer-item"><small>شروع</small><b>{{ persian_digits($deal->starts_on->format('Y/m/d')) }}</b></div>
            <div class="deal-drawer-item"><small>پایان</small><b>{{ persian_digits($deal->ends_on->format('Y/m/d')) }}</b></div>
            <div class="deal-drawer-item"><small>تصمیم دانش‌آموز</small><b>
                @if($deal->decision === 'continue') ادامه
                @elseif($deal->decision === 'withdraw') انصراف
                @else بی‌تصمیم
                @endif
            </b></div>
        </div>
        @if(!$deal->renewed_at && $deal->decision !== 'withdraw')
        <div class="deal-drawer-sec deal-drawer-acts">
            <form method="POST" action="{{ route('consultant.deals.remind', $deal) }}" data-router="off">
                @csrf
                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-bold"
                        style="background:var(--c-primary);color:var(--c-on-primary,#fff)">
                    <i class="fas fa-paper-plane"></i> ارسال یادآوری
                </button>
            </form>
        </div>
        @endif
        <div class="deal-drawer-sec" style="border-bottom:0">
            <div class="deal-drawer-due" style="margin-bottom:8px">تاریخچهٔ پیام‌های این دوره</div>
            @forelse($dealNotifications as $n)
                <div class="deal-hist-item">
                    <span class="deal-ch-ic deal-ch-ic--sms"><i class="fas fa-bell"></i></span>
                    <div style="min-width:0">
                        <p>{{ $n->message }}</p>
                        <small>{{ persian_digits($n->created_at->format('Y/m/d H:i')) }} — {{ $n->read_at ? 'خوانده شد' : 'خوانده نشده' }}</small>
                    </div>
                </div>
            @empty
                <div style="color:var(--c-muted);font-size:12px;padding:8px 0">هنوز پیامی برای این دوره ثبت نشده است.</div>
            @endforelse
        </div>
    </template>
@endforeach

{{-- Drawer shell --}}
<div class="deal-drawer-back" id="dealDrawerBack" hidden></div>
<aside class="deal-drawer" id="dealDrawer" hidden aria-label="جزئیات دوره">
    <button type="button" class="deal-drawer-close" id="dealDrawerClose" aria-label="بستن">
        <i class="fas fa-xmark"></i>
    </button>
    <div id="dealDrawerBody"></div>
</aside>

<script>
(function () {
    var drawer = document.getElementById('dealDrawer');
    var back = document.getElementById('dealDrawerBack');
    var body = document.getElementById('dealDrawerBody');
    var close = document.getElementById('dealDrawerClose');
    if (!drawer) return;

    function openDrawer(id) {
        var tpl = document.getElementById('deal-drawer-' + id);
        if (!tpl) return;
        body.innerHTML = '';
        body.appendChild(tpl.content.cloneNode(true));
        drawer.hidden = false;
        back.hidden = false;
        requestAnimationFrame(function () {
            drawer.classList.add('open');
            back.classList.add('show');
        });
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        back.classList.remove('show');
        setTimeout(function () { drawer.hidden = true; back.hidden = true; }, 260);
    }

    document.querySelectorAll('[data-deal-drawer]').forEach(function (el) {
        el.addEventListener('click', function () { openDrawer(el.dataset.dealDrawer); });
    });
    close.addEventListener('click', closeDrawer);
    back.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDrawer(); });
})();
</script>
@endsection
