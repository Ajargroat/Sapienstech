{{--
    One join request in full — the reviewer's decision screen.

    Reached from «درخواست‌ها»; shows the imported data next to the payment the
    visitor declared, so the tenant can check the transfer reference against
    their bank account before approving. Approving turns this data into a real
    student and reveals the generated password exactly once (it is never stored
    in plain text, so there is no later chance to read it).
--}}
@extends('layouts.consultant')

@section('content')
@php
    $statusLabels = [
        'pending'  => 'در انتظار تأیید',
        'approved' => 'تأییدشده',
        'rejected' => 'ردشده',
    ];
    $statusClass = [
        'pending'  => 'blog-status--draft',
        'approved' => 'blog-status--published',
        'rejected' => 'blog-status--draft',
    ];
    $rows = [
        'ایمیل'         => $request->email,
        'شماره تماس'    => $request->phone,
        'پایه تحصیلی'   => $request->grade,
        'جنسیت'         => $request->gender,
        'رشته تحصیلی'   => $request->major,
        'توضیح کوتاه'   => $request->bio,
    ];
@endphp

<div class="settings-cards">

    <section class="settings-card">
        <h3 class="settings-card-title">
            <i class="fas fa-id-card" aria-hidden="true"></i>
            درخواست «{{ $request->name }}»
            <span class="blog-status {{ $statusClass[$request->status] ?? 'blog-status--draft' }}">
                {{ $statusLabels[$request->status] ?? $request->status }}
            </span>
        </h3>

        @if(session('status'))
            <div class="settings-flash settings-flash--success">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="settings-flash settings-flash--error">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{-- The generated password is revealed by the shared pop-up this page
             includes at the end — the same reveal the manual-create flow uses,
             so both entry points behave identically. --}}

        {{-- Imported data ------------------------------------------------ --}}
        <div class="profile-info-list">
            @foreach($rows as $label => $value)
                @if($value)
                    <div class="profile-info-row">
                        <span class="profile-info-label">{{ $label }}</span>
                        <span class="profile-info-value" @if($label === 'ایمیل') dir="ltr" @endif>{{ $value }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    {{-- Declared payment ------------------------------------------------ --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-file-invoice" aria-hidden="true"></i> پرداخت اعلام‌شده</h3>

        @if($request->reference || $request->amount || $request->plan_name)
            <div class="profile-info-list">
                @if($request->plan_name)
                    <div class="profile-info-row">
                        <span class="profile-info-label">طرح انتخابی</span>
                        <span class="profile-info-value">{{ $request->plan_name }}</span>
                    </div>
                @endif
                <div class="profile-info-row">
                    <span class="profile-info-label">مبلغ</span>
                    <span class="profile-info-value">
                        {{ $request->amount ? persian_digits(number_format($request->amount)).' تومان' : '—' }}
                    </span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">کد پیگیری</span>
                    <span class="profile-info-value" dir="ltr">{{ $request->reference ?: '—' }}</span>
                </div>
            </div>
            <p class="settings-card-text" style="margin-top:12px">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                پیش از تأیید، این کد را با حساب بانکی خود تطبیق دهید.
            </p>
        @else
            <p class="settings-card-text">این درخواست کد پیگیری یا مبلغی همراه نداشته است.</p>
        @endif
    </section>

    {{-- Decision -------------------------------------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-clipboard-list" aria-hidden="true"></i> بررسی</h3>

        @if($request->isPending())
            @if($canManage)
                <form method="POST" action="{{ route('consultant.requests.approve', $request) }}" class="settings-form">
                    @csrf
                    <label class="settings-field-label" for="approve-note">یادداشت (اختیاری)</label>
                    <textarea id="approve-note" name="review_note" rows="2" maxlength="1000"
                              class="settings-input"></textarea>
                    <button type="submit" class="btn" style="margin-top:12px">
                        <i class="fas fa-circle-check" aria-hidden="true"></i> تأیید و افزودن به فهرست
                    </button>
                </form>

                <form method="POST" action="{{ route('consultant.requests.reject', $request) }}"
                      class="settings-form" style="margin-top:20px">
                    @csrf
                    <label class="settings-field-label" for="reject-note">دلیل رد (اختیاری)</label>
                    <textarea id="reject-note" name="review_note" rows="2" maxlength="1000"
                              class="settings-input"></textarea>
                    <button type="submit" class="btn" style="margin-top:12px">
                        <i class="fas fa-circle-xmark" aria-hidden="true"></i> رد درخواست
                    </button>
                </form>
            @else
                <p class="settings-card-text">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                    تأیید یا رد این درخواست تنها توسط مدیر مجموعه انجام می‌شود.
                </p>
            @endif
        @else
            <div class="profile-info-list">
                <div class="profile-info-row">
                    <span class="profile-info-label">بررسی‌کننده</span>
                    <span class="profile-info-value">{{ $request->reviewer?->name ?? '—' }}</span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">تاریخ بررسی</span>
                    <span class="profile-info-value">
                        {{ $request->reviewed_at ? persian_digits($request->reviewed_at->format('Y/m/d H:i')) : '—' }}
                    </span>
                </div>
                @if($request->review_note)
                    <div class="profile-info-row">
                        <span class="profile-info-label">یادداشت</span>
                        <span class="profile-info-value">{{ $request->review_note }}</span>
                    </div>
                @endif
                @if($request->student)
                    <div class="profile-info-row">
                        <span class="profile-info-label">دانش‌آموز</span>
                        <span class="profile-info-value">
                            <a href="{{ route('consultant.student.profile', $request->student) }}">{{ $request->student->name }}</a>
                        </span>
                    </div>
                @endif
            </div>
        @endif
    </section>

    <section class="settings-card">
        <a href="{{ route('consultant.requests.index') }}" class="btn">
            <i class="fas fa-arrow-right" aria-hidden="true"></i> بازگشت به درخواست‌ها
        </a>
    </section>
</div>

{{-- After approving, this page is the redirect target — the pop-up shows the
     generated password exactly once. --}}
@include('consultant.students.partials.generated-password-dialog')
@endsection
