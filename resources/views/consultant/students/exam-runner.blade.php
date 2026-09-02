{{--
    Full-screen exam runner: questions reveal as you scroll, sticky timer,
    side navigator, flag-for-review. Submit -> storeAttempt -> result page.
    Styling: "EXAM RUNNER" in resources/css/app.css; behavior: consultant-exam-runner.js.
--}}
@extends('layouts.consultant')

@section('content')
@php
    $letters = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
    $diffLabels = ['Easy' => 'آسان', 'Medium' => 'متوسط', 'Hard' => 'سخت'];
@endphp
<div id="exam-runner"
     data-assignment="{{ $assignment->id }}"
     data-duration="{{ $test->time_limit_minutes ? (int) $test->time_limit_minutes * 60 : 0 }}">

    <header class="runner-bar">
        <div class="runner-bar-info">
            <a href="{{ route('consultant.student.exams', $student) }}" class="runner-exit" aria-label="خروج">
                <i class="fas fa-arrow-right"></i>
            </a>
            <div>
                <h2>{{ $test->test_title }}</h2>
                <p>{{ $test->lesson }} · {{ persian_digits($questions->count()) }} سوال · نمره از {{ persian_digits($test->total_marks) }}</p>
            </div>
        </div>
        <div class="runner-bar-meta">
            <span class="runner-timer" id="runner-timer" hidden>
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
                            <span class="diff-chip diff--{{ strtolower($q->difficulty) }}">{{ $diffLabels[$q->difficulty] }}</span>
                        @endif
                        <button type="button" class="quiz-flag" aria-label="علامت‌گذاری" title="برای بررسی بعدی">
                            <i class="far fa-flag"></i>
                        </button>
                    </div>

                    @if($q->question_image_path)
                        <div class="quiz-figure">
                            <x-cropped-image :path="$q->question_image_path" :bbox="$q->question_image_bbox" :max-width="640"/>
                        </div>
                    @endif

                    <p class="quiz-text">{{ $q->question_text }}</p>

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

    <form method="POST" id="attempt-form" action="{{ route('consultant.student.exams.attempt', [$student, $assignment]) }}">
        @csrf
        <input type="hidden" name="time_taken_seconds" id="time-taken" value="0">
    </form>
</div>
@vite(['resources/js/features/consultant-exam-runner.js'])
@endsection
