{{--
    Consultant notification center — the topnav bell's landing page.

    Rendered by App\Http\Controllers\Consultant\DealController::notifications()
    through `consultant.notifications`. Everything deal-related that needs the
    tenant's attention in one place: receipts awaiting the owner's verification,
    deals in/past the decision window, and the sent-reminder log.
--}}
@extends('layouts.consultant')

@section('content')
@php $today = \Illuminate\Support\Carbon::today(); @endphp

<div class="settings-cards">

    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-bell" aria-hidden="true"></i> اطلاعیه‌ها</h3>
        <p class="settings-card-text">رسیدهای در انتظار تأیید، دوره‌های نزدیک به سررسید و یادآوری‌های ارسال‌شده.</p>
    </section>

    {{-- Receipts awaiting verification (owner only) ---------------------- --}}
    @if($canManage && $pendingPayments->isNotEmpty())
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-hourglass-half" aria-hidden="true"></i> رسیدهای در انتظار تأیید</h3>
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

    {{-- Due renewals ------------------------------------------------------ --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> سررسیدهای تمدید</h3>

        @if($dueDeals->count())
            <div class="deal-due-list">
                @foreach($dueDeals as $deal)
                    @php $left = $deal->daysLeft($today); @endphp
                    <div class="deal-due-row">
                        <span class="deal-ava deal-ava--sm deal-hue-2" aria-hidden="true">{{ mb_substr($deal->student->name, 0, 1) }}</span>
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
                        @if(!$deal->renewed_at && $deal->decision !== 'withdraw')
                            <form method="POST" action="{{ route('consultant.deals.remind', $deal) }}" data-router="off">
                                @csrf
                                <button type="submit" class="icon-action" title="یادآوری به دانش‌آموز">
                                    <i class="fas fa-comment"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($dueDeals->hasPages())
                <div class="blog-pager">{{ $dueDeals->links() }}</div>
            @endif
        @else
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>سررسید فوری‌ای نیست</h3>
                <p>هیچ دوره‌ای در پنجرهٔ پایانی یا معوق نیست.</p>
            </div>
        @endif
    </section>

    {{-- Sent reminders ---------------------------------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-paper-plane" aria-hidden="true"></i> یادآوری‌های ارسال‌شده</h3>

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
</div>
@endsection
