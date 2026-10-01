{{--
    Exam preview fragment, fetched into #preview-exam-modal by
    consultant-exams.js with the current selection in its own order.

    The preview owns the order: these ↑/↓ buttons are the only reordering
    control left (the chooser tray keeps selection only), and the moved order
    is what store() persists as test_questions.position.
--}}
@php
    $letters = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
@endphp
<div class="preview-list">
    @forelse($questions as $i => $q)
        <article class="quiz-card" data-preview-id="{{ $q->id }}">
            <div class="quiz-card-head">
                <span class="quiz-num">{{ persian_digits($i + 1) }}</span>
                <span class="preview-moves">
                    <button type="button" data-move-preview="up" @disabled($loop->first) aria-label="بالا">↑</button>
                    <button type="button" data-move-preview="down" @disabled($loop->last) aria-label="پایین">↓</button>
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
            @if($q->answers->isNotEmpty())
                <ul class="quiz-options">
                    @foreach($q->answers as $a)
                        <li>
                            <span class="quiz-option">
                                <span class="opt-key">{{ $letters[$loop->index] ?? $loop->index + 1 }}</span>
                                <span class="opt-text">{{ $a->answer_text }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>
    @empty
        <p class="picker-empty">هنوز سوالی انتخاب نشده است.</p>
    @endforelse
</div>
