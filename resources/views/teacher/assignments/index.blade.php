{{--
    Teacher panel — assignment list with per-assignment status counts.
--}}
@extends('layouts.teacher')

@section('content')
<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-tasks"></i> تکالیف من</h2>
        <a href="{{ route('teacher.assignments.create') }}" class="primary-button">
            <i class="fas fa-plus"></i> تکلیف جدید
        </a>
    </header>

    @include('consultant.blog.flash')

    @if($assignments->isNotEmpty())
        <div class="exam-grid" data-stagger>
            @foreach($assignments as $assignment)
                <article class="exam-card">
                    <div class="exam-card-icon exam-card-icon--comprehensive">
                        @if(! $assignment->is_published)
                            <span class="exam-status exam-status--missed">پیش‌نویس</span>
                        @endif
                        <i class="fas fa-clipboard-list teacher-material-icon"></i>
                        <span class="exam-card-icon-label">تکلیف</span>
                    </div>
                    <div class="exam-card-body">
                        <h3 class="exam-card-title">{{ $assignment->title }}</h3>
                        <ul class="exam-card-facts">
                            <li><i class="fas fa-layer-group"></i>{{ $assignment->grade ?: 'همه پایه‌ها' }}</li>
                            @if($assignment->subject)
                                <li><i class="fas fa-book"></i>{{ $assignment->subject }}</li>
                            @endif
                            <li><i class="fas fa-users"></i>{{ persian_digits($assignment->submissions_count) }} پاسخ ثبت‌شده</li>
                            @if($assignment->due_at)
                                <li><i class="fas fa-calendar-day"></i>
                                    <time class="fa-date" datetime="{{ $assignment->due_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($assignment->due_at->format('Y/m/d')) }}</time>
                                </li>
                            @endif
                        </ul>

                        <div class="teacher-status-strip">
                            <span class="status-pill status-pill--submitted">
                                {{ persian_digits((int) ($submittedCounts[$assignment->id] ?? 0)) }} در انتظار بررسی
                            </span>
                        </div>

                        <div class="teacher-material-actions">
                            <a href="{{ route('teacher.assignments.show', $assignment) }}" class="primary-button">
                                <i class="fas fa-list-check"></i> وضعیت دانش‌آموزان
                            </a>
                            <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}" data-confirm="این تکلیف و همه وضعیت‌های آن حذف شود؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger-button"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="far fa-tasks"></i>
            <h3>هنوز تکلیفی نساخته‌اید</h3>
            <p>با دکمه «تکلیف جدید» اولین تکلیف کلاس را بسازید.</p>
        </div>
    @endif
</div>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
