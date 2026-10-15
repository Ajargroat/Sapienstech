{{--
    «درخواست‌ها» — the queue of visitors waiting to become students.

    A visitor imports their data on the tenant's public website and declares the
    payment they made (see public/sections/join). This page is where the tenant
    confirms it: approving turns the imported data into a real student.

    The admin can also add a student by hand from here (requests.create), for a
    person assigned outside this flow — same destination, no declared payment.

    Reuses the deals page's visual language (.settings-card, .deal-stats,
    .deal-chip, .deal-due-row, .blog-status, .empty-state, .blog-pager) so both
    surfaces stay one system.
--}}
@extends('layouts.consultant')

@section('content')
@php
    $statusLabels = [
        'pending'  => 'در انتظار تأیید',
        'approved' => 'تأییدشده',
        'rejected' => 'ردشده',
        'all'      => 'همه',
    ];
    // .blog-status ships published/draft variants; map the request's three
    // states onto them rather than inventing a parallel set.
    $statusClass = [
        'pending'  => 'blog-status--draft',
        'approved' => 'blog-status--published',
        'rejected' => 'blog-status--draft',
    ];
@endphp

<div class="settings-cards">

    {{-- Summary ---------------------------------------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title"><i class="fas fa-inbox" aria-hidden="true"></i> درخواست‌های عضویت</h3>
        <p class="settings-card-text">
            افرادی که از سایت شما درخواست عضویت فرستاده‌اند. با تأیید هر درخواست، اطلاعات وارد‌شده
            به‌صورت خودکار به فهرست دانش‌آموزان اضافه می‌شود و نیازی به ثبت دستی نیست.
        </p>

        @if(request()->user()?->isTenantAdmin())
            {{-- The other way onto the roster: hand-entry, for a student
                 assigned outside the request flow. --}}
            <p class="settings-card-text">
                <a href="{{ route('consultant.requests.create') }}" class="btn">
                    <i class="fas fa-user-plus" aria-hidden="true"></i>
                    افزودن دانش‌آموز به‌صورت دستی
                </a>
                <span style="margin-inline-start:8px">
                    دانش‌آموزی که درخواستی نفرستاده است را خودتان ثبت کنید.
                </span>
            </p>
        @endif

        <div class="deal-stats">
            <div class="deal-stat deal-stat--pending">
                <span class="deal-stat-lbl"><i class="fas fa-clock" aria-hidden="true"></i> در انتظار تأیید</span>
                <span class="deal-stat-num">{{ persian_digits($counts['pending']) }}</span>
            </div>
            <div class="deal-stat">
                <span class="deal-stat-lbl"><i class="fas fa-circle-check" aria-hidden="true"></i> تأییدشده</span>
                <span class="deal-stat-num">{{ persian_digits($counts['approved']) }}</span>
            </div>
            <div class="deal-stat">
                <span class="deal-stat-lbl"><i class="fas fa-circle-xmark" aria-hidden="true"></i> ردشده</span>
                <span class="deal-stat-num">{{ persian_digits($counts['rejected']) }}</span>
            </div>
        </div>

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

        @unless($canManage)
            <p class="settings-card-text" style="margin-top:12px">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                تأیید یا رد درخواست‌ها تنها توسط مدیر مجموعه انجام می‌شود.
            </p>
        @endunless

        {{-- Status tabs -------------------------------------------------- --}}
        <div class="deal-chips">
            @foreach ($statusLabels as $key => $label)
                <a href="{{ route('consultant.requests.index', ['status' => $key]) }}"
                   class="deal-chip {{ $filters['status'] === $key ? 'on' : '' }}">
                    {{ $label }}
                    <i aria-hidden="true">{{ persian_digits($counts[$key] ?? 0) }}</i>
                </a>
            @endforeach
        </div>

        {{-- Search ------------------------------------------------------ --}}
        <form method="GET" action="{{ route('consultant.requests.index') }}" style="margin-top:14px">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <div class="search-reveal-form" style="display:flex;gap:8px">
                <input type="search" name="q" value="{{ $filters['q'] }}"
                       placeholder="جستجو بر اساس نام، ایمیل یا کد پیگیری..."
                       class="settings-input" style="flex:1">
                <button type="submit" class="btn">
                    <i class="fas fa-search" aria-hidden="true"></i> جستجو
                </button>
            </div>
        </form>
    </section>

    {{-- The queue -------------------------------------------------------- --}}
    <section class="settings-card">
        <h3 class="settings-card-title">
            <i class="fas fa-list-check" aria-hidden="true"></i>
            {{ $statusLabels[$filters['status']] ?? 'درخواست‌ها' }}
        </h3>

        <div class="bulk-history-list">
            @forelse($requests as $joinRequest)
                <a href="{{ route('consultant.requests.show', $joinRequest) }}" class="blog-row">
                    <span class="deal-ava deal-ava--sm deal-hue-{{ ($joinRequest->id % 6) + 1 }}" aria-hidden="true">
                        {{ mb_substr($joinRequest->name, 0, 1) }}
                    </span>

                    <span class="blog-row-main">
                        <b class="blog-row-title">{{ $joinRequest->name }}</b>
                        <span class="blog-row-meta">
                            <span dir="ltr">{{ $joinRequest->email }}</span>
                            @if($joinRequest->plan_name)
                                <span>طرح: {{ $joinRequest->plan_name }}</span>
                            @endif
                            @if($joinRequest->reference)
                                <span dir="ltr">کد: {{ $joinRequest->reference }}</span>
                            @endif
                            @if($joinRequest->amount)
                                <span>{{ persian_digits(number_format($joinRequest->amount)) }} تومان</span>
                            @endif
                        </span>
                    </span>

                    <span class="blog-status {{ $statusClass[$joinRequest->status] ?? 'blog-status--draft' }}">
                        {{ $statusLabels[$joinRequest->status] ?? $joinRequest->status }}
                    </span>

                    <span class="deal-due-date">
                        <i class="fas fa-calendar-day" aria-hidden="true"></i>
                        {{ persian_digits($joinRequest->created_at->format('Y/m/d')) }}
                    </span>

                    <i class="fas fa-chevron-left deal-go" aria-hidden="true"></i>
                </a>
            @empty
                <div class="empty-state empty-state--compact">
                    <i class="fas fa-inbox" aria-hidden="true"></i>
                    <p>
                        @if($filters['q'] !== '')
                            درخواستی مطابق جستجوی شما پیدا نشد.
                        @elseif($filters['status'] === 'pending')
                            درخواست جدیدی در انتظار تأیید نیست.
                        @else
                            درخواستی در این وضعیت وجود ندارد.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        <div class="blog-pager">{{ $requests->links() }}</div>
    </section>
</div>
@endsection
