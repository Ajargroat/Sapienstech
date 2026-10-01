<?php

namespace App\Support;

use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\StudentTestAttempt;
use Illuminate\Support\Collection;

/**
 * The exam-report data contract.
 *
 * The report card itself is not built yet; this is the shape it will consume
 * unchanged. It is a plain array (no JSON resource — there is no JSON consumer
 * yet) and the two result pages already read it, so the contract is proven
 * usable rather than merely specified.
 */
class ExamReport
{
    /**
     * @return array{
     *     attempt_id: int, test_id: ?int, student_id: int,
     *     percent: float, negative_percent: float,
     *     correct: int, wrong: int, unanswered: int, ungraded: int, graded: int, total: int,
     *     per_lesson: array<string, array{correct: int, wrong: int, unanswered: int, ungraded: int, total: int, percent: ?float, graded: int}>,
     *     date: ?string, completed_at: ?string, time_taken_seconds: int
     * }
     */
    public static function fromAttempt(StudentTestAttempt $attempt): array
    {
        $questions = $attempt->test?->questions()->with('answers')->get() ?? collect();
        $rows = $attempt->answers()
            ->whereIn('question_id', $questions->pluck('id')->all())
            ->get()
            ->keyBy('question_id');

        $buckets = [];
        $totals = ['correct' => 0, 'wrong' => 0, 'unanswered' => 0, 'ungraded' => 0];

        foreach ($questions as $question) {
            /** @var AttemptAnswer|null $row */
            $row = $rows[$question->id] ?? null;
            $lesson = $question->subject ?: '—';
            $buckets[$lesson] ??= ['correct' => 0, 'wrong' => 0, 'unanswered' => 0, 'ungraded' => 0, 'total' => 0, 'percent' => null];
            $buckets[$lesson]['total']++;

            $state = self::classify($row);
            $buckets[$lesson][$state]++;
            $totals[$state]++;
        }

        foreach ($buckets as $lesson => $bucket) {
            $graded = $bucket['correct'] + $bucket['wrong'] + $bucket['unanswered'];
            $buckets[$lesson]['graded'] = $graded;
            $buckets[$lesson]['percent'] = $graded > 0
                ? round($bucket['correct'] / $graded * 100, 2)
                : null;
        }

        return [
            'attempt_id' => (int) $attempt->id,
            'test_id' => $attempt->test_id === null ? null : (int) $attempt->test_id,
            'student_id' => (int) $attempt->student_id,
            'percent' => round((float) ($attempt->score_simple_percent ?? 0), 2),
            'negative_percent' => round((float) ($attempt->score_negative_percent ?? 0), 2),
            'correct' => $totals['correct'],
            'wrong' => $totals['wrong'],
            'unanswered' => $totals['unanswered'],
            'ungraded' => $totals['ungraded'],
            // The graded denominator of the whole attempt, derived from the same
            // four states — $totals is only ever incremented by classify(), so
            // it never fills this key on its own.
            'graded' => $totals['correct'] + $totals['wrong'] + $totals['unanswered'],
            'total' => $questions->count(),
            'per_lesson' => $buckets,
            'date' => $attempt->completed_at?->format('Y-m-d'),
            'completed_at' => $attempt->completed_at?->toIso8601String(),
            'time_taken_seconds' => (int) $attempt->time_taken_seconds,
        ];
    }

    /**
     * The four-state truth table the redesigned بررسی renders.
     *
     * An ungraded row is never right or wrong, whatever the student chose.
     *
     * @return 'correct'|'wrong'|'unanswered'|'ungraded'
     */
    public static function classify(?AttemptAnswer $row): string
    {
        if (! $row || ! $row->is_graded) {
            return 'ungraded';
        }

        if ($row->is_correct) {
            return 'correct';
        }

        return $row->chosen_answer_id ? 'wrong' : 'unanswered';
    }

    /** The lessons an attempt actually covers, in the exam's own order. */
    public static function lessonsOf(Collection $questions): array
    {
        return $questions
            ->map(fn (Question $q) => $q->subject ?: '—')
            ->unique()
            ->values()
            ->all();
    }
}
