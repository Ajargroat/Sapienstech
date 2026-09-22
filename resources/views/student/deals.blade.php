{{--
    Student deal renewal — the student side of تمدید و پرداخت.

    Rendered by App\Http\Controllers\Student\DealController::index() through
    `student.deals.index`. For every period (deal) the student sees the
    days-left chip and, once the decision window opens (windowDays before the
    end or overdue), the two prompts from the renewal flow:

    - «ادامهٔ دوره» / «انصراف» — the decision, changeable until the period is
      renewed; withdrawing cancels a pending receipt but keeps access until
      ends_on (DealService::decide).
    - Payment: either the transfer-reference form (bank transfer, verified by
      the owner) or the built-in simulated gateway (auto-approves; stand-in
      until the server-side PSP lands).

    The notification feed lives on its own page now (student.notifications),
    reached from the topnav bell.
--}}
@extends('layouts.student')

@section('content')
@if(session('status'))
    <div class="rounded-2xl border border-[var(--c-border)] p-3 text-sm" style="color:var(--c-success,#34D399)">
        {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div class="mt-3 rounded-2xl border border-[var(--c-border)] p-3 text-sm" style="color:var(--c-danger,#F87171)">
        <i class="fas fa-exclamation-circle" aria-hidden="true"></i> {{ $errors->first() }}
    </div>
@endif

@php use App\Models\DealPayment; use App\Support\DealService; @endphp
@php $activeDeals = $deals->getCollection()->whereNull('renewed_at'); @endphp
@php $renewedDeals = $deals->getCollection()->whereNotNull('renewed_at'); @endphp

<div class="mt-4 flex flex-col gap-5">

    {{-- Active periods ------------------------------------------------- --}}
    <section class="panel student-panel">
        <header class="student-panel-head">
            <h2><i class="fas fa-file-invoice"></i> دوره‌های فعال</h2>
        </header>

        @forelse($activeDeals as $deal)
            @php
                $left = $deal->daysLeft();
                $open = DealService::windowOpen($deal);
                $pending = $deal->payments->firstWhere('status', DealPayment::STATUS_PENDING);
                $rejected = $deal->payments->firstWhere('status', DealPayment::STATUS_REJECTED);
            @endphp
            <div class="my-3 rounded-2xl border border-[var(--c-border)] p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <b>
                        {{ persian_digits($deal->starts_on->format('Y/m/d')) }} تا {{ persian_digits($deal->ends_on->format('Y/m/d')) }}
                        · {{ persian_digits(number_format($deal->amount)) }} تومان
                        @if($deal->consultant) · مشاور: {{ $deal->consultant->name }} @endif
                    </b>
                    <span class="blog-status"
                          @if($left < 0) style="color:var(--c-danger,#F87171);background:color-mix(in srgb,var(--c-danger,#F87171) 14%,transparent)"
                          @elseif($left <= $windowDays) style="color:var(--c-warning,#FBBF24);background:color-mix(in srgb,var(--c-warning,#FBBF24) 14%,transparent)" @endif>
                        @if($left < 0) {{ persian_digits(abs($left)) }} روز تأخیر
                        @elseif($left === 0) سررسید امروز
                        @else {{ persian_digits($left) }} روز مانده
                        @endif
                    </span>
                </div>

                @if($deal->decision === 'withdraw')
                    <div class="mt-3 rounded-xl border border-[var(--c-border)] p-3 text-sm">
                        <i class="fas fa-undo"></i> تصمیم شما: <b>انصراف از ادامهٔ دوره</b>.
                        دسترسی شما تا پایان دورهٔ فعلی برقرار می‌ماند.
                        @if($open)
                            <form method="POST" action="{{ route('student.deals.decide', $deal) }}" class="mt-2" data-router="off">
                                @csrf
                                <input type="hidden" name="decision" value="continue">
                                <button type="submit" class="text-sm font-bold underline" style="color:var(--c-primary)">
                                    تغییر تصمیم به «ادامه»
                                </button>
                            </form>
                        @endif
                    </div>
                @elseif($open)
                    {{-- The decision prompt — the heart of the feature. --}}
                    <div class="mt-3 rounded-xl border border-[var(--c-border)] p-3 text-sm">
                        دورهٔ شما در حال پایان است؛ تکلیف ادامهٔ مسیر را مشخص کنید:
                        <form method="POST" action="{{ route('student.deals.decide', $deal) }}" class="mt-2" data-router="off">
                            @csrf
                            <input type="hidden" name="decision" value="continue">
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-bold"
                                    style="background:var(--c-primary);color:var(--c-on-primary,#fff)">
                                <i class="fas fa-redo"></i> ادامهٔ دوره
                            </button>
                        </form>
                        <details class="mt-2">
                            <summary class="cursor-pointer text-sm font-bold" style="color:var(--c-danger,#F87171)">انصراف از ادامهٔ دوره</summary>
                            <form method="POST" action="{{ route('student.deals.decide', $deal) }}" class="mt-2" data-router="off"
                                  onsubmit="return confirm('از ادامهٔ دوره منصرف می‌شوید؟ رسیدهای در انتظار تأیید لغو می‌شود؛ دسترسی تا پایان دورهٔ فعلی برقرار می‌ماند.');">
                                @csrf
                                <textarea name="decision_note" rows="2" maxlength="1000" placeholder="دلیل انصراف (اختیاری)"
                                          class="w-full rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]"></textarea>
                                <button type="submit" class="mt-2 rounded-xl border border-[var(--c-border)] px-4 py-2 text-sm font-bold">
                                    ثبت انصراف
                                </button>
                            </form>
                        </details>
                    </div>

                    @if($deal->decision === 'continue')
                        @if($pending)
                            <div class="mt-3 rounded-xl p-3 text-sm" style="background:color-mix(in srgb,var(--c-warning,#FBBF24) 12%,transparent)">
                                <i class="fas fa-hourglass-half"></i> رسید شما در انتظار تأیید است:
                                «{{ $pending->reference }}» — {{ persian_digits(number_format($pending->amount)) }} تومان
                            </div>
                        @else
                            @if($rejected)
                                <div class="mt-3 rounded-xl p-3 text-sm" style="background:color-mix(in srgb,var(--c-danger,#F87171) 12%,transparent)">
                                    <i class="fas fa-circle-info"></i>
                                    رسید قبلی تأیید نشد@if($rejected->review_note): {{ $rejected->review_note }}@endif. می‌توانید رسید دیگری ثبت کنید.
                                </div>
                            @endif

                            {{-- Simulated gateway: auto-approves, renews immediately. --}}
                            <form method="POST" action="{{ route('student.deals.payments.online', $deal) }}" class="mt-3" data-router="off"
                                  onsubmit="return confirm('پرداخت آزمایشی انجام و دوره بلافاصله تمدید شود؟');">
                                @csrf
                                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-bold"
                                        style="background:var(--c-primary);color:var(--c-on-primary,#fff)">
                                    <i class="fas fa-bolt"></i> پرداخت آنلاین (آزمایشی)
                                </button>
                            </form>
                            <p class="mt-1 text-xs text-[var(--c-muted)]">درگاه شبیه‌سازی‌شدهٔ مجموعه است؛ رسید صادر و بلافاصله تأیید می‌شود و دوره تمدید می‌گردد.</p>

                            <div class="mt-4 border-t border-[var(--c-border)] pt-3 text-xs text-[var(--c-muted)]">
                                یا با کارت‌به‌کارت واریز کنید و شمارهٔ پیگیری را ثبت کنید:
                            </div>
                            <form method="POST" action="{{ route('student.deals.payments.store', $deal) }}" class="mt-2 flex flex-wrap items-center gap-2" data-router="off">
                                @csrf
                                <input type="text" name="reference" required minlength="4" maxlength="120"
                                       placeholder="شماره پیگیری واریز"
                                       class="flex-1 rounded-xl border border-[var(--c-border)] bg-transparent p-2 text-sm text-[var(--c-text)]">
                                <button type="submit" class="rounded-xl px-4 py-2 text-sm font-bold"
                                        style="background:var(--c-primary);color:var(--c-on-primary,#fff)">
                                    <i class="fas fa-sack-dollar"></i> ثبت رسید پرداخت
                                </button>
                            </form>
                            <p class="mt-1 text-xs text-[var(--c-muted)]">پرداخت کارت‌به‌کارت پس از بررسی مجموعه تأیید می‌شود.</p>
                        @endif
                    @endif
                @else
                    <p class="mt-3 text-xs text-[var(--c-muted)]">
                        <i class="fas fa-circle-info"></i>
                        تصمیم‌گیری حدود {{ persian_digits($windowDays) }} روز مانده به پایان دوره فعال می‌شود.
                    </p>
                @endif
            </div>
        @empty
            <div class="empty-state empty-state--compact">
                <i class="fas fa-file-invoice"></i>
                <h3>دوره‌ای برای شما ثبت نشده</h3>
                <p>پس از ثبت دورهٔ مشاوره، وضعیت و فرصت تمدید آن در همین صفحه نمایش داده می‌شود.</p>
            </div>
        @endforelse

        @if($deals->hasPages())
            <div class="blog-pager">{{ $deals->links() }}</div>
        @endif
    </section>

    {{-- Renewed history ------------------------------------------------ --}}
    @if($renewedDeals->isNotEmpty())
        <section class="panel student-panel">
            <header class="student-panel-head">
                <h2><i class="fas fa-redo"></i> دوره‌های تمدیدشده</h2>
            </header>
            @foreach($renewedDeals as $deal)
                <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                    <span>
                        <i class="fas fa-check-circle" style="color:var(--c-success,#34D399)"></i>
                        {{ persian_digits($deal->starts_on->format('Y/m/d')) }} تا {{ persian_digits($deal->ends_on->format('Y/m/d')) }}
                        · {{ persian_digits(number_format($deal->amount)) }} تومان
                    </span>
                    <span class="blog-status blog-status--published">تمدید شد</span>
                </div>
            @endforeach
        </section>
    @endif
</div>
@endsection
