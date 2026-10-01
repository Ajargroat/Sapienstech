{{--
    The question-by-question بررسی, shared by the student and consultant result
    pages.

    Four verdict states, and the fourth is the new one: a question whose bank
    row carries no correct answer is «بدون امتیاز» — it highlights no correct
    option (there is none) and is never marked right or wrong, but the student's
    own choice is still shown so the review stays honest.

    Each card is one row: picture left, question text right, options full width
    beneath (TASK §5/AC6).

    @param \Illuminate\Support\Collection $questions ordered, answers loaded
    @param \Illuminate\Support\Collection $answers   attempt answers keyed by question_id
--}}
@php
    $letters = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
@endphp
<section class="result-review">
    @foreach($questions as $i => $q)
        @php
            $ans = $answers[$q->id] ?? null;
            $verdict = \App\Support\ExamReport::classify($ans);
        @endphp
        <article class="quiz-card is-in is-{{ $verdict === 'correct' ? 'right' : ($verdict === 'wrong' ? 'wrong' : ($verdict === 'ungraded' ? 'ungraded' : 'blank')) }}">
            <div class="quiz-card-head">
                <span class="quiz-num">{{ persian_digits($i + 1) }}</span>
                <span class="result-verdict">
                    @switch($verdict)
                        @case('correct')<i class="fas fa-check"></i> صحیح @break
                        @case('wrong')<i class="fas fa-times"></i> غلط @break
                        @case('ungraded')<i class="fas fa-circle-info"></i> بدون امتیاز @break
                        @default <i class="far fa-circle"></i> بی‌پاسخ
                    @endswitch
                </span>
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
                        <span class="quiz-option
                            @if($verdict !== 'ungraded' && $a->is_correct) opt--correct @endif
                            @if($ans && $ans->chosen_answer_id === $a->id && !($verdict === 'correct' && $a->is_correct)) opt--chosen @endif
                            @if($ans && $ans->chosen_answer_id === $a->id && $verdict === 'wrong') opt--chosen-wrong @endif">
                            <span class="opt-key">{{ $letters[$loop->index] ?? $loop->index + 1 }}</span>
                            <span class="opt-text">{{ $a->answer_text }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </article>
    @endforeach
</section>
