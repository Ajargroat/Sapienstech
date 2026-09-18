{{--
    Student portal — one assignment's detail: description, deadline, the
    student's own status and the submit action. Submitting moves the status
    pending → submitted; the teacher's acknowledgement (completed) is final
    on this side.
--}}
@extends('layouts.student')

@section('content')
<a href="{{ route('student.assignments.index') }}" class="teacher-backlink">
    <i class="fas fa-arrow-right"></i> بازگشت به تکالیف
</a>

<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-clipboard-list"></i> {{ $assignment->title }}</h2>
        <span class="status-pill status-pill--{{ $assignment->statusFor($student->id) }}">
            {{ $statuses[$assignment->statusFor($student->id)] }}
        </span>
    </header>

    @include('consultant.blog.flash')

    <ul class="exam-card-facts teacher-assignment-facts">
        @if($assignment->subject)
            <li><i class="fas fa-book"></i>{{ $assignment->subject }}</li>
        @endif
        <li><i class="fas fa-user-tie"></i>{{ $assignment->teacher?->name ?? '—' }}</li>
        @if($assignment->due_at)
            <li><i class="fas fa-calendar-day"></i>مهلت:
                <time class="fa-date" datetime="{{ $assignment->due_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($assignment->due_at->format('Y/m/d')) }}</time>
            </li>
        @endif
        @if($assignment->max_score)
            <li><i class="fas fa-star-half-stroke"></i>نمره بیشینه: {{ persian_digits($assignment->max_score) }}</li>
        @endif
    </ul>

    @if($assignment->description)
        <p class="teacher-material-desc">{{ $assignment->description }}</p>
    @endif

    <div class="student-side">
        @if($submission)
            <section class="panel student-panel">
                <header class="student-panel-head">
                    <h2><i class="fas fa-paper-plane"></i> وضعیت تحویل</h2>
                </header>
                <ul class="exam-card-facts teacher-assignment-facts">
                    <li>
                        <i class="far fa-clock"></i>
                        @if($submission->submitted_at)
                            تحویل در
                            <time class="fa-date" datetime="{{ $submission->submitted_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($submission->submitted_at->format('Y/m/d')) }}</time>
                        @else
                            هنوز تحویل نشده
                        @endif
                    </li>
                    @if($submission->score !== null)
                        <li><i class="fas fa-star"></i>نمره: {{ persian_digits($submission->score) }}</li>
                    @endif
                    @if($submission->note)
                        <li><i class="fas fa-note-sticky"></i>{{ $submission->note }}</li>
                    @endif
                </ul>

                @if($submission->status === 'submitted')
                    <form method="POST" action="{{ route('student.assignments.submit', $assignment) }}" class="teacher-form-actions">
                        @csrf
                        <label class="settings-field teacher-form-grow">
                            <span class="settings-field-label">یادداشت جدید (اختیاری)</span>
                            <input type="text" name="note" class="settings-input" maxlength="2000">
                        </label>
                        <button type="submit" class="secondary-button"><i class="fas fa-rotate"></i> به‌روزرسانی یادداشت</button>
                    </form>
                @endif
            </section>
        @endif

        @if(! $submission)
            <section class="panel student-panel">
                <header class="student-panel-head">
                    <h2><i class="fas fa-paper-plane"></i> تحویل تکلیف</h2>
                </header>

                <form method="POST" action="{{ route('student.assignments.submit', $assignment) }}" class="teacher-form">
                    @csrf
                    <label class="settings-field">
                        <span class="settings-field-label">یادداشت تحویل (اختیاری)</span>
                        <textarea name="note" rows="3" class="settings-input" maxlength="2000"
                                  placeholder="مثلاً: در دفتر کار نوشته‌ام / فایل را به معلم دادم…"></textarea>
                    </label>
                    <div class="teacher-form-actions">
                        <span class="student-email">پس از تحویل، وضعیت «تحویل‌شده» و پس از تأیید معلم «تکمیل‌شده» می‌شود.</span>
                        <button type="submit" class="primary-button"><i class="fas fa-paper-plane"></i> تحویل دادم</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</div>

@vite(['resources/js/features/student-dashboard.js'])
@endsection
