{{--
    The exam result hero, shared by the student and consultant result pages.

    Percent-first on purpose: the score is always a percentage (TASK §7), so it
    is read from the report contract's `percent`, never from score_raw /
    total_marks. The verdict chips follow the same four states the review below
    uses — ungraded questions get their own chip instead of being folded into
    «غلط».

    @param array $report  App\Support\ExamReport::fromAttempt()
    @param \App\Models\Test $test
--}}
@php
    $percent = $report['percent'];
    $lessons = $report['per_lesson'];

    // Two decimals, then drop the insignificant ones: 100 → «۱۰۰», 87.5 → «۸۷٫۵».
    // rtrim() alone would eat the whole integer part of 100 and print «۱».
    $faPercent = fn ($value) => persian_digits(
        rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.')
    );
@endphp
<section class="result-hero">
    <div class="result-score">
        <strong>{{ $faPercent($percent) }}<small>٪</small></strong>
        <span>{{ $test->test_title }}</span>
    </div>
    <div class="result-stats">
        <h2>{{ $test->lesson ?: '—' }}</h2>
        <ul>
            <li><i class="fas fa-check-circle"></i> {{ persian_digits($report['correct']) }} صحیح</li>
            <li><i class="fas fa-times-circle"></i> {{ persian_digits($report['wrong']) }} غلط</li>
            <li><i class="fas fa-forward"></i> {{ persian_digits($report['unanswered']) }} بی‌پاسخ</li>
            @if($report['ungraded'] > 0)
                <li class="is-ungraded"><i class="fas fa-circle-info"></i> {{ persian_digits($report['ungraded']) }} بدون امتیاز</li>
            @endif
            @if($report['completed_at'])
                <li>
                    <i class="far fa-calendar-check"></i>
                    <time class="fa-date" datetime="{{ $report['completed_at'] }}">{{ $report['date'] }}</time>
                </li>
            @endif
        </ul>
    </div>
</section>

@if(count($lessons) > 1)
    <section class="result-lessons">
        <h3>به تفکیک درس</h3>
        <ul>
            @foreach($lessons as $lesson => $bucket)
                <li>
                    <span class="lesson-name">{{ $lesson }}</span>
                    <span class="lesson-percent">
                        @if($bucket['percent'] !== null)
                            {{ $faPercent($bucket['percent']) }}٪
                        @else
                            —
                        @endif
                    </span>
                    <span class="lesson-breakdown">
                        {{ persian_digits($bucket['correct']) }} صحیح ·
                        {{ persian_digits($bucket['wrong']) }} غلط ·
                        {{ persian_digits($bucket['unanswered']) }} بی‌پاسخ
                        @if($bucket['ungraded'] > 0) · {{ persian_digits($bucket['ungraded']) }} بدون امتیاز @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
