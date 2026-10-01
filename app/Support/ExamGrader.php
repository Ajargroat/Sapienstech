<?php

namespace App\Support;

use App\Models\Question;
use App\Models\Test;
use Illuminate\Support\Collection;

/**
 * The one implementation of exam تصحیح.
 *
 * Questions whose bank row carries no correct answer stay selectable and stay
 * shown, but they are "ungraded": excluded from the denominator and never
 * marked right or wrong. The denominator is therefore the number of *graded*
 * questions the student actually faced, and every score is a percentage.
 *
 * Pure computation — no persistence. The student submit endpoint stores what
 * this returns; nothing else grades.
 */
class ExamGrader
{
    /**
     * @param  Collection<int|string, Question>  $questions  ordered, answers loaded
     * @param  array<int|string, mixed>  $input  question id => chosen answer id
     * @return array{
     *     denominator: int, per: float, raw: float, simple: float, negative: float,
     *     correct: int, wrong: int, unanswered: int, ungraded: int, graded: int,
     *     verdicts: array<int, array{chosen: ?int, is_correct: bool, is_graded: bool}>
     * }
     */
    public static function grade(Test $test, Collection $questions, array $input): array
    {
        $gradedTotal = $questions->filter(fn (Question $q) => self::isGraded($q))->count();
        $denominator = max(1, $gradedTotal);
        $per = (float) $test->total_marks / $denominator;

        $correct = $wrong = $ungraded = 0;
        $raw = 0.0;
        $verdicts = [];

        foreach ($questions as $id => $question) {
            $id = (int) $id;
            [$chosen, $isCorrect] = self::judge($question, $input[$id] ?? null);

            if (! self::isGraded($question)) {
                $ungraded++;
                $verdicts[$id] = ['chosen' => $chosen, 'is_correct' => false, 'is_graded' => false];

                continue;
            }

            if ($isCorrect) {
                $correct++;
                // Per graded question, not the pivot's points: pivot points are
                // total_marks / *all* questions, so mixing them with a
                // graded-only denominator would print two different percentages
                // for one attempt (the result hero reads `simple`, the exam card
                // derives percent from raw / total_marks).
                $raw += $per;
            } elseif ($chosen) {
                $wrong++;
            }

            $verdicts[$id] = ['chosen' => $chosen, 'is_correct' => $isCorrect, 'is_graded' => true];
        }

        $answered = $correct + $wrong;

        return [
            'denominator' => $denominator,
            'per' => $per,
            'raw' => round($raw, 2),
            'simple' => round($correct / $denominator * 100, 2),
            'negative' => round(max(0, ($correct - $wrong / 4) / $denominator * 100), 2),
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $questions->count() - $answered - $ungraded,
            'ungraded' => $ungraded,
            'graded' => $gradedTotal,
            'verdicts' => $verdicts,
        ];
    }

    /** A question is gradeable when the bank stored a correct answer for it. */
    public static function isGraded(Question $question): bool
    {
        return $question->answers->contains('is_correct', true);
    }

    /**
     * A chosen option only counts if it belongs to its own question.
     *
     * @return array{0: ?int, 1: bool}
     */
    private static function judge(Question $question, mixed $raw): array
    {
        $answerId = (int) $raw;

        if (! $answerId || ! $question->answers->contains('id', $answerId)) {
            return [null, false];
        }

        return [$answerId, (bool) $question->answers->firstWhere('id', $answerId)->is_correct];
    }
}
