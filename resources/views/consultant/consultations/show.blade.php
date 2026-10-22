{{--
    One consultation request in full — the reviewer's decision screen.

    Reached from «درخواست مشاوره». There is no payment to verify and nothing
    to approve into: the decision only fixes the row's status, and the row
    stays as the record of truth afterwards.
--}}
@extends('layouts.consultant')

@section('content')
<div class="page-surface">
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
@endphp

<div class="settings-cards">

    <section class="settings-card">
        <h3 class="settings-card-title">
            <i class="fas fa-comments" aria-hidden="true"></i>
            درخواست مشاوره «{{ $request->name }}»
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

        {{-- The visitor's data -------------------------------------------- --}}
        <div class="profile-info-list">
            <div class="profile-info-row">
                <span class="profile-info-label">ایمیل</span>
                <span class="profile-info-value" dir="ltr">{{ $request->email }}</span>
            </div>
            @if($request->phone)
                <div class="profile-info-row">
                    <span class="profile-info-label">شماره تماس</span>
                    <span class="profile-info-value" dir="ltr">{{ $request->phone }}</span>
                </div>
            @endif
            @if($request->message)
                <div class="profile-info-row">
                    <span class="profile-info-label">متن پیام</span>
                    <span class="profile-info-value">{{ $request->message }}</span>
                </div>
            @endif
        </div>
    </section>

    {{-- Decision -------------------------------------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-clipboard-list" aria-hidden="true"></i> بررسی</h3>

        @if($request->status === \App\Models\ConsultationRequest::STATUS_PENDING)
            @if($canManage)
                <form method="POST" action="{{ route('consultant.consultations.approve', $request) }}" class="settings-form">
                    @csrf
                    <button type="submit" class="btn">
                        <i class="fas fa-circle-check" aria-hidden="true"></i> تأیید درخواست
                    </button>
                </form>

                <form method="POST" action="{{ route('consultant.consultations.reject', $request) }}"
                      class="settings-form" style="margin-top:20px">
                    @csrf
                    <button type="submit" class="btn">
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
            <p class="settings-card-text">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                این درخواست {{ $statusLabels[$request->status] ?? $request->status }} است؛ ردیف به‌عنوان سابقه حفظ
                می‌شود و تصمیمی برای بازگشت به حالت اول وجود ندارد.
            </p>
        @endif
    </section>

    <section class="settings-card">
        <a href="{{ route('consultant.consultations.index') }}" class="btn">
            <i class="fas fa-arrow-right" aria-hidden="true"></i> بازگشت به درخواست‌های مشاوره
        </a>
    </section>
</div>
</div>
@endsection
