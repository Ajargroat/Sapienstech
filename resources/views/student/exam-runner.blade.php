{{--
    The student's exam runner: questions reveal as you scroll, sticky countdown,
    side navigator, flag-for-review. Submitting posts to student.exams.attempt,
    where the server grades and redirects to the result.

    The countdown is driven by data-remaining (seconds left, computed server-
    side from the attempt's own started_at) — never by a client-stored start —
    so refreshing or reopening resumes the same clock. Behavior:
    resources/js/features/student-exam-runner.js.

    Rendered by App\Http\Controllers\Student\StudentExamController::show().
--}}
@extends('layouts.student')

@section('content')
@php
    $letters = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
    $diffLabels = ['Easy' => 'آسان', 'Medium' => 'متوسط', 'Hard' => 'سخت'];
@endphp
<div id="exam-runner"
     data-assignment="{{ $assignment->id }}"
     data-remaining="{{ $remaining }}">

    <header class="runner-bar">
        <div class="runner-bar-info">
            <div>
                <h2>{{ $test->test_title }}</h2>
                <p>{{ $test->lesson }} · {{ persian_digits($questions->count()) }} سوال</p>
            </div>
        </div>
        <div class="runner-bar-meta">
            <span class="runner-timer" id="runner-timer" @if(!$test->time_limit_minutes) hidden @endif>
                <i class="far fa-clock"></i> <bdi id="timer-text">—</bdi>
            </span>
            <div class="runner-progress">
                <span id="progress-text">۰/{{ persian_digits($questions->count()) }}</span>
                <div class="progress-track"><div class="progress-fill" id="progress-fill"></div></div>
            </div>
        </div>
    </header>

    <div class="runner-layout">
        <main class="runner-stream">
            @foreach($questions as $i => $q)
                <article class="quiz-card" data-index="{{ $i }}" data-question-id="{{ $q->id }}">
                    <div class="quiz-card-head">
                        <span class="quiz-num">{{ persian_digits($i + 1) }}</span>
                        @if($q->difficulty)
                            <span class="diff-chip diff--{{ strtolower($q->difficulty) }}">{{ $diffLabels[$q->difficulty] ?? $q->difficulty }}</span>
                        @endif
                        <button type="button" class="quiz-flag" aria-label="علامت‌گذاری" title="برای بررسی بعدی">
                            <i class="far fa-flag"></i>
                        </button>
                    </div>

                    <div class="quiz-body @if(!$q->has_croppable_image) quiz-body--text-only @endif">
                        @if($q->has_croppable_image)
                            <div class="quiz-figure">
                                <x-cropped-image :path="$q->question_image_path" :bbox="$q->question_image_bbox" :max-width="640"/>
                            </div>
                        @endif
                        <div class="quiz-body-text">
                            <p class="quiz-text">{{ $q->question_text }}</p>
                        </div>
                    </div>

                    <ul class="quiz-options">
                        @foreach($q->answers as $a)
                            <li>
                                <label class="quiz-option">
                                    <input type="radio" form="attempt-form"
                                           name="answers[{{ $q->id }}]" value="{{ $a->id }}">
                                    <span class="opt-key">{{ $letters[$loop->index] ?? $loop->index + 1 }}</span>
                                    <span class="opt-text">{{ $a->answer_text }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </main>

        <aside class="runner-nav">
            <p class="runner-nav-title">پیمایش سوالات</p>
            <div class="nav-grid" id="nav-grid">
                @foreach($questions as $i => $q)
                    <button type="button" class="nav-chip" data-index="{{ $i }}">{{ persian_digits($i + 1) }}</button>
                @endforeach
            </div>
            <div class="nav-legend">
                <span><i class="dot dot--answered"></i> پاسخ‌داده</span>
                <span><i class="dot dot--flagged"></i> علامت‌دار</span>
            </div>
            <button type="button" id="finish-exam" class="primary-button runner-finish">پایان آزمون و مشاهده نتیجه</button>
        </aside>
    </div>

    <form method="POST" id="attempt-form" action="{{ route('student.exams.attempt', $assignment) }}">
        @csrf
    </form>
</div>
@vite(['resources/js/features/student-exam-runner.js'])
@endsection
