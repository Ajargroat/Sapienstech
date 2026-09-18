{{--
    Teacher panel — the per-student status board of one assignment:
    pending (no row yet) → submitted (awaiting review) → completed
    (acknowledged). The teacher flips statuses here; scores/notes ride
    along.
--}}
@extends('layouts.teacher')

@section('content')
<a href="{{ route('teacher.assignments.index') }}" class="teacher-backlink">
    <i class="fas fa-arrow-right"></i> بازگشت به تکالیف
</a>

<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-clipboard-list"></i> {{ $assignment->title }}</h2>
        <span class="count-badge">{{ $assignment->grade ?: 'همه پایه‌ها' }}</span>
    </header>

    @include('consultant.blog.flash')

    <ul class="exam-card-facts teacher-assignment-facts">
        @if($assignment->subject)
            <li><i class="fas fa-book"></i>{{ $assignment->subject }}</li>
        @endif
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

    @if($students->isNotEmpty())
        <div class="teacher-table" role="table">
            <div class="teacher-table-head" role="row">
                <span>دانش‌آموز</span>
                <span>وضعیت</span>
                <span>تحویل</span>
                <span>نمره / یادداشت</span>
                <span></span>
            </div>

            @foreach($students as $student)
                @php
                    $submission = $submissions->get($student->id);
                    $status = $submission?->status ?? 'pending';
                @endphp
                <div class="teacher-table-row" role="row">
                    <a href="{{ route('teacher.students.show', $student) }}" class="teacher-table-student">
                        <span class="student-avatar-lg student-avatar-sm">{{ mb_substr($student->name, 0, 1) }}</span>
                        {{ $student->name }}
                    </a>

                    <span>
                        <span class="status-pill status-pill--{{ $status }}">{{ $statuses[$status] }}</span>
                    </span>

                    <span class="teacher-table-time">
                        @if($submission?->submitted_at)
                            <time class="fa-date" datetime="{{ $submission->submitted_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($submission->submitted_at->format('Y/m/d')) }}</time>
                        @else
                            —
                        @endif
                    </span>

                    <span class="teacher-table-note">
                        @if($submission?->score !== null){{ persian_digits($submission->score) }}@endif
                        @if($submission?->note)<em>{{ $submission->note }}</em>@endif
                        @if(! $submission && ($assignment->max_score || $assignment->due_at))—@endif
                    </span>

                    <form
                        method="POST"
                        action="{{ route('teacher.assignments.submissions.update', [$assignment, $student]) }}"
                        class="teacher-table-actions"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $status === 'completed' ? 'submitted' : 'completed' }}">
                        <input type="hidden" name="score" value="{{ $submission?->score ?? '' }}">
                        <button type="submit" class="secondary-button">
                            @if($status === 'completed')
                                <i class="fas fa-rotate-left"></i> بازگشت به بررسی
                            @else
                                <i class="fas fa-check"></i> تکمیل
                            @endif
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <h3>دانش‌آموزی در این کلاس نیست</h3>
            <p>هیچ دانش‌آموزی با پایه «{{ $assignment->grade }}» ثبت نشده است.</p>
        </div>
    @endif
</div>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
