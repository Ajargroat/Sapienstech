{{--
    Student notification center — the bell's landing page.

    Rendered by App\Http\Controllers\Student\DealController::notifications()
    through `student.notifications`. The deal feed (reminders, decision
    confirmations, payment outcomes) moved here from the deals page; each item
    can be marked read inline, or all at once.
--}}
@extends('layouts.student')

@section('content')
<div class="student-welcome">
    <section class="student-profile-head">
        <span class="student-avatar-lg">{{ mb_substr($student->name, 0, 1) }}</span>
        <div>
            <h2>اطلاعیه‌ها</h2>
            <span class="student-email">یادآوری‌های پایان دوره و نتیجهٔ بررسی پرداخت‌ها اینجا نمایش داده می‌شود.</span>
        </div>
    </section>
</div>

@if(session('status'))
    <div class="mt-4 rounded-2xl border border-[var(--c-border)] p-3 text-sm" style="color:var(--c-success,#34D399)">
        {{ session('status') }}
    </div>
@endif

<div class="mt-4 flex flex-col gap-5">
    <section class="panel student-panel">
        <header class="student-panel-head">
            <h2><i class="fas fa-bell"></i> اطلاعیه‌های من</h2>
            <span class="count-badge">
                @if($unread)
                    {{ persian_digits($unread) }} خوانده‌نشده
                    <form method="POST" action="{{ route('student.notifications.readAll') }}" class="inline" data-router="off">
                        @csrf
                        <button type="submit" class="count-badge count-badge--link">خواندن همه</button>
                    </form>
                @else
                    همه خوانده شده
                @endif
            </span>
        </header>

        @forelse($notifications as $notification)
            <div class="flex items-start justify-between gap-3 border-b border-[var(--c-border)] py-3 text-sm last:border-0 {{ $notification->read_at ? 'opacity-60' : 'font-bold' }}">
                <span>
                    @if($notification->kind === 'reminder')<i class="fas fa-bell"></i>
                    @elseif($notification->kind === 'payment') <i class="fas fa-sack-dollar"></i>
                    @else <i class="fas fa-comment"></i> @endif
                    {{ $notification->message }}
                    <span class="block text-xs font-normal text-[var(--c-muted)]">
                        {{ persian_digits($notification->created_at->format('Y/m/d H:i')) }}
                    </span>
                </span>
                @if($notification->isUnread())
                    <form method="POST" action="{{ route('student.notifications.read', $notification) }}" data-router="off">
                        @csrf
                        <button type="submit" class="icon-action" title="خوانده شد">
                            <i class="fas fa-check-circle"></i>
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="empty-state empty-state--compact">
                <i class="fas fa-bell"></i>
                <h3>اطلاعیه‌ای نیست</h3>
                <p>یادآوری‌های پایان دوره و نتیجهٔ بررسی پرداخت‌ها اینجا نمایش داده می‌شود.</p>
            </div>
        @endforelse

        @if($notifications->hasPages())
            <div class="blog-pager">{{ $notifications->links() }}</div>
        @endif
    </section>
</div>
@endsection
