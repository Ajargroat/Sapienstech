{{--
    Deal renewal manager — the panel side of تمدید و پرداخت.

    Rendered by App\Http\Controllers\Consultant\DealController::index()
    through `consultant.deals.index`. Shows every student period (deal) with
    its decision state and days-left chip, the payments awaiting the owner's
    verification, filters, and the create form for opening a first period.
    Reuses the settings/blog visual language (.settings-card, .blog-status,
    .empty-state, .blog-pager); no dedicated CSS is required.

    Payment flow mirrors the real state machine: students submit transfer
    references in their portal, the owner verifies the deposit against the
    merchant account here — approving closes the period and opens the next
    one with the same terms (DealService::reviewPayment).
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
@endphp

<div class="settings-cards">

    {{-- Summary ------------------------------------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-sack-dollar" aria-hidden="true"></i> تمدید و پرداخت</h3>
        <p class="settings-card-text">وضعیت دوره‌های دانش‌آموزان، تصمیم آن‌ها (ادامه یا انصراف) و رسیدهای در انتظار تأیید.</p>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-2xl border border-[var(--c-border)] p-4">
                <span class="block text-xs text-[var(--c-muted)]">دوره‌های فعال</span>
                <span class="text-xl font-extrabold">{{ persian_digits($summary['total']) }}</span>
            </div>
            <div class="rounded-2xl border border-[var(--c-border)] p-4">
                <span class="block text-xs text-[var(--c-muted)]">سررسید گذشته</span>
                <span class="text-xl font-extrabold" style="color:var(--c-danger,#F87171)">{{ persian_digits($summary['due']) }}</span>
            </div>
            <div class="rounded-2xl border border-[var(--c-border)] p-4">
                <span class="block text-xs text-[var(--c-muted)]">پرداخت در انتظار تأیید</span>
                <span class="text-xl font-extrabold" style="color:var(--c-warning,#FBBF24)">{{ persian_digits($summary['pending']) }}</span>
            </div>
            <div class="rounded-2xl border border-[var(--c-border)] p-4">
                <span class="block text-xs text-[var(--c-muted)]">مجموع دریافت‌شده</span>
                <span class="text-xl font-extrabold">{{ persian_digits(number_format($summary['collected'])) }} تومان</span>
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
    </section>
    @endif

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
                                <span class="blog-status blog-status--{{ $payment->status === 'paid' ? 'published' : ($payment->status === 'pending' ? 'draft' : 'draft') }}"
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
@endsection
