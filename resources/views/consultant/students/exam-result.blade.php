@extends('layouts.consultant')

@section('content')
@php
    $letters = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
@endphp
<div class="result-wrap">
    <a href="{{ route('consultant.student.exams', $student) }}" class="runner-exit"><i class="fas fa-arrow-right"></i> بازگشت</a>

    <section class="result-hero">
        <div class="result-score">
            <strong>{{ persian_digits(rtrim(rtrim((string) $attempt->score_raw, '0'), '.')) }}</strong>
            <span>از {{ persian_digits($test->total_marks) }}</span>
        </div>
        <div class="result-stats">
            <h2>{{ $test->test_title }}</h2>
            <ul>
                <li><i class="fas fa-check-circle"></i> {{ persian_digits($attempt->answers->where('is_correct', true)->count()) }} صحیح</li>
                <li><i class="fas fa-times-circle"></i> {{ persian_digits($attempt->answers->filter(fn ($a) => $a->chosen_answer_id && !$a->is_correct)->count()) }} غلط</li>
                <li><i class="fas fa-forward"></i> {{ persian_digits((int) $attempt->answers->count() - $attempt->answers->whereNotNull('chosen_answer_id')->count()) }} بی‌پاسخ</li>
                <li><i class="fas fa-percent"></i> درصد: {{ persian_digits($attempt->score_simple_percent) }}</li>
            </ul>
        </div>
    </section>

    <section class="result-review">
        @foreach($questions as $i => $q)
            @php $ans = $answers[$q->id] ?? null; @endphp
            <article class="quiz-card is-in {{ $ans?->is_correct ? 'is-right' : ($ans && !$ans->is_correct ? 'is-wrong' : 'is-blank') }}">
                <div class="quiz-card-head">
                    <span class="quiz-num">{{ persian_digits($i + 1) }}</span>
                    <span class="result-verdict">
                        @if($ans?->is_correct)<i class="fas fa-check"></i> صحیح
                        @elseif($ans && $ans->chosen_answer_id)<i class="fas fa-times"></i> غلط
                        @else <i class="far fa-circle"></i> بی‌پاسخ @endif
                    </span>
                </div>
                @if($q->question_image_path)
                    <div class="quiz-figure"><x-cropped-image :path="$q->question_image_path" :bbox="$q->question_image_bbox" :max-width="640"/></div>
                @endif
                <p class="quiz-text">{{ $q->question_text }}</p>
                <ul class="quiz-options">
                    @foreach($q->answers as $a)
                        <li>
                            <span class="quiz-option
                                @if($a->is_correct) opt--correct @endif
                                @if($ans && $ans->chosen_answer_id === $a->id && !$a->is_correct) opt--chosen-wrong @endif">
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
