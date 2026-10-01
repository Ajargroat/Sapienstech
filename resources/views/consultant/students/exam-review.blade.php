{{--
    Consultant review of an exam: the questions and their correct answers.

    The consultant never sits the exam, so there is deliberately no radio, no
    submit form, no timer and no flag — this page cannot create an attempt
    (TASK AC3). Behaviour lives in the shared result partials; see
    resources/css/features/exams.css for the review styling.

    Rendered by App\Http\Controllers\Consultant\StudentExamController::run().
--}}
@extends('layouts.consultant')

@section('content')
@php
    $letters = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
@endphp
<p class="result-back">
    <a href="{{ route('consultant.student.exams', $student) }}"><i class="fas fa-arrow-right"></i> بازگشت به فهرست آزمون‌ها</a>
</p>

<div id="exam-review" class="result-wrap">
    <header class="runner-bar">
        <div class="runner-bar-info">
            <div>
                <h2>{{ $test->test_title }}</h2>
                <p>{{ $test->lesson }} · {{ persian_digits($questions->count()) }} سوال</p>
            </div>
        </div>
        <div class="runner-bar-meta">
            <span class="review-badge"><i class="fas fa-eye"></i> مشاهدهٔ پاسخ‌های صحیح</span>
        </div>
    </header>

    <section class="result-review">
        @foreach($questions as $i => $q)
            <article class="quiz-card is-in">
                <div class="quiz-card-head">
                    <span class="quiz-num">{{ persian_digits($i + 1) }}</span>
                    @if($q->difficulty)
                        <span class="diff-chip diff--{{ strtolower($q->difficulty) }}">{{ $difficultyLabels[$q->difficulty] ?? $q->difficulty }}</span>
                    @endif
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
                            <span class="quiz-option @if($a->is_correct) opt--correct @endif">
                                <span class="opt-key">{{ $letters[$loop->index] ?? $loop->index + 1 }}</span>
                                <span class="opt-text">{{ $a->answer_text }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </section>
</div>
@endsection
