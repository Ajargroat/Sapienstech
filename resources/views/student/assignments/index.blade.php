{{--
    Student portal — assignment list with status pills:
    pending (در انتظار انجام) → submitted (تحویل‌شده) → completed (تکمیل‌شده).
    Same card anatomy as the student dashboard's exam cards.
--}}
@extends('layouts.student')

@section('content')
<div class="student-welcome">
    <section class="student-profile-head">
        <span class="student-avatar-lg"><i class="fas fa-tasks"></i></span>
        <div>
            <h2>تکالیف من</h2>
            <span class="student-email">تکالیف کلاس و وضعیت هر کدام</span>
        </div>
    </section>
</div>

@if($assignments->isNotEmpty())
    <div class="exam-grid" data-stagger>
        @foreach($assignments as $assignment)
            @php
                $status = $assignment->statusFor($student->id);
                $isOverdue = $assignment->due_at && $assignment->due_at->isPast() && $status === 'pending';
            @endphp
            <article class="exam-card">
                <div class="exam-card-icon exam-card-icon--comprehensive">
                    <span class="status-pill status-pill--{{ $status }}">{{ $statuses[$status] }}</span>
                    <i class="fas fa-clipboard-list teacher-material-icon"></i>
                    <span class="exam-card-icon-label">تکلیف</span>
                </div>
                <div class="exam-card-body">
                    <h3 class="exam-card-title">{{ $assignment->title }}</h3>
                    <ul class="exam-card-facts">
                        @if($assignment->subject)
                            <li><i class="fas fa-book"></i>{{ $assignment->subject }}</li>
                        @endif
                        <li><i class="fas fa-user-tie"></i>{{ $assignment->teacher?->name ?? '—' }}</li>
                        @if($assignment->due_at)
                            <li><i class="fas fa-calendar-day"></i>مهلت:
                                <time class="fa-date" datetime="{{ $assignment->due_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($assignment->due_at->format('Y/m/d')) }}</time>
                            </li>
                        @endif
                    </ul>
                    @if($isOverdue)
                        <p class="teacher-material-desc teacher-material-desc--warn">مهلت تحویل گذشته است.</p>
                    @endif
                    <div class="teacher-material-actions">
                        <a href="{{ route('student.assignments.show', $assignment) }}" class="primary-button">
                            @if($status === 'pending')
                                <i class="fas fa-paper-plane"></i> مشاهده و تحویل
                            @else
                                <i class="fas fa-eye"></i> مشاهده
                            @endif
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@else
    <div class="panel student-panel">
        <div class="empty-state">
            <i class="far fa-tasks"></i>
            <h3>تکلیفی وجود ندارد</h3>
            <p>به‌محض اینکه معلم تکلیفی برای کلاس شما بگذارد، اینجا می‌بینید.</p>
        </div>
    </div>
@endif

@vite(['resources/js/features/student-dashboard.js'])
@endsection
