<?php

namespace App\Support;

use App\Models\CompanyExamResult;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
use Illuminate\Support\Collection;

/**
 * The student's own report card (کارنامه) — one computed contract, two readers.
 *
 * The report page and the dashboard's analytics strip both read this class, so
 * the two surfaces can never disagree about a trend, a weak subject or an
 * absence. It is a plain array (like ExamReport) — there is no JSON consumer.
 *
 * What it merges
 * --------------
 *  - in-system exams: finished `student_assigned_quizzes` (+ their newest
 *    completed attempt). A finished exam with no attempt is an ABSENCE.
 *  - imported corporation results: `company_exam_results`, entered by the
 *    consultant (import or form). No endpoint, no scraping — institutes do not
 *    expose one.
 *
 * Both sources are reduced to ONE shape (`below`), timestamped by exam date, so
 * the trend and the per-subject deltas treat them the same way.
 *
 * Score polarity
 * --------------
 * Every percentage here is "percent correct". A corporation report can also
 * carry a negative-marked percent (3 wrong − 1); that is kept as
 * `negative_percent` only, never used for judgment. A consultant exam's own
 * attempt carries BOTH, and the per-subject split is derived from the attempt's
 * four-state rows (`ExamReport::fromAttempt`), not from a per-test percent.
 *
 * Kept deliberately out
 * ---------------------
 *  - corrected answers / "repeated mistakes": `attempt_answers` stores only
 *    the chosen id and a boolean, never a corrected one, so a "wrong → should
 *    be X" card would be invented. Left for the lesson-reader's per-question
 *    solution text in a later pass.
 *  - ranking: no rank column exists for in-system exams, and showing a rank
 *    only for corporation rows would compare two different populations.
 */
class ReportCard
{
    public const SOURCE_EXAM = 'exam';

    public const SOURCE_COMPANY = 'company';

    /** The corporation-result statuses that mean "did not sit it". */
    public const COMPANY_ABSENT_STATUSES = ['absent', 'missed'];

    /**
     * The configured pass line, floored to 0..100.
     *
     * Written by the consultant (Settings → کارنامه) through ConfigWriter at
     * `report.pass_line`; the baseline lives in config/theme.php.
     */
    public static function passLine(): float
    {
        $value = site('report.pass_line', 50);

        if (! is_numeric($value)) {
            $value = 50;
        }

        return max(0.0, min(100.0, (float) $value));
    }

    /** Whether the class/participants average may be shown at all. */
    public static function showsClassAverage(): bool
    {
        return (bool) site('report.report_show_class_average', false);
    }

    /**
     * Build the whole contract for one student.
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     subjects: list<array<string, mixed>>,
     *     weak: list<array<string, mixed>>,
     *     strong: list<array<string, mixed>>,
     *     absences: list<array<string, mixed>>,
     *     history: list<array<string, mixed>>,
     *     pass_line: float,
     *     show_class_average: bool,
     *     has_data: bool
     * }
     */
    public static function forStudent(Student $student): array
    {
        $passLine = self::passLine();

        $rows = self::examRows($student)
            ->concat(self::companyRows($student))
            ->sortBy('date_sort')
            ->values();

        $scored = $rows->where('percent', '!=', null)->values();

        $summary = self::summary($rows, $scored, $passLine);
        $subjects = self::subjects($scored, $passLine);
        $weak = array_values(array_filter($subjects, fn (array $s) => $s['below']));
        $strong = array_values(array_filter($subjects, fn (array $s) => $s['above']));

        // Weakest (lowest percent) first; strongest (highest) first.
        usort($weak, fn ($a, $b) => $a['percent'] <=> $b['percent']);
        usort($strong, fn ($a, $b) => $b['percent'] <=> $a['percent']);

        $absences = $rows
            ->where('absent', true)
            ->map(fn (array $row) => [
                'title' => $row['title'],
                'source' => $row['source'],
                'source_label' => $row['source_label'],
                'date_sort' => $row['date_sort'],
                'date_iso' => $row['date_iso'],
                'date_text' => $row['date_text'],
            ])
            ->sortByDesc('date_sort')
            ->values()
            ->all();

        return [
            'rows' => $rows->all(),
            'summary' => $summary,
            'subjects' => $subjects,
            'weak' => $weak,
            'strong' => $strong,
            'absences' => $absences,
            'history' => array_slice(array_reverse($scored->all()), 0, 12),
            'pass_line' => $passLine,
            'show_class_average' => self::showsClassAverage(),
            'has_data' => $rows->isNotEmpty(),
        ];
    }

    /* ------------------------------------------------------------------ rows */

    /** Finished consultant exams, reduced to one row each. */
    private static function examRows(Student $student): Collection
    {
        return StudentAssignedQuiz::query()
            ->where('student_assigned_quizzes.student_id', $student->id)
            ->whereIn('student_assigned_quizzes.status', ['completed', 'grading', 'missed'])
            ->with([
                'test' => fn ($query) => $query->withCount(['questionRows as questions_count']),
                'attempts' => fn ($query) => $query->where('status', 'completed')->latest('id')->limit(1),
            ])
            ->get()
            ->filter(fn (StudentAssignedQuiz $a) => $a->test !== null)
            ->map(function (StudentAssignedQuiz $assignment): array {
                /** @var StudentTestAttempt|null $attempt */
                $attempt = $assignment->attempts->first();

                $report = $attempt ? ExamReport::fromAttempt($attempt) : null;
                $date = $attempt?->completed_at ?? $assignment->scheduled_at ?? $assignment->created_at;

                $subjects = [];
                if ($report !== null) {
                    foreach ($report['per_lesson'] as $name => $bucket) {
                        if ($bucket['percent'] !== null) {
                            $subjects[$name] = round((float) $bucket['percent'], 1);
                        }
                    }
                }

                // A finished exam nobody sat is an absence; one that is still
                // being graded (or has no attempt recorded yet) is pending.
                $absent = $attempt === null && $assignment->status === 'missed';

                return [
                    'key' => 'exam-'.$assignment->id,
                    'source' => self::SOURCE_EXAM,
                    'source_label' => 'آزمون مشاور',
                    'title' => $assignment->test->test_title,
                    'lesson' => $assignment->test->lesson,
                    'date_sort' => $date?->timestamp ?? 0,
                    'date_iso' => $date?->toIso8601String(),
                    'date_text' => $date?->format('Y-m-d'),
                    'absent' => $absent,
                    'pending' => $attempt === null && ! $absent,
                    'percent' => $report !== null ? round((float) $report['percent'], 1) : null,
                    'negative_percent' => $report !== null ? round((float) $report['negative_percent'], 1) : null,
                    'questions' => (int) ($assignment->test->questions_count ?? 0),
                    'correct' => $report['correct'] ?? null,
                    'wrong' => $report['wrong'] ?? null,
                    'blank' => $report['unanswered'] ?? null,
                    'rank' => null,
                    'participants' => null,
                    'subjects' => $subjects,
                    'class_subjects' => [],
                    'url' => $attempt !== null ? route('student.exams.result', $assignment) : null,
                    'attempt_id' => $attempt?->id,
                    'minutes' => $attempt?->time_taken_seconds ? (int) round($attempt->time_taken_seconds / 60) : null,
                ];
            });
    }

    /** Imported corporation results, reduced to the same row shape. */
    private static function companyRows(Student $student): Collection
    {
        return CompanyExamResult::query()
            ->where('company_exam_results.student_id', $student->id)
            ->with('company')
            ->get()
            ->map(function (CompanyExamResult $result): array {
                $date = $result->exam_date;
                $subjects = [];
                foreach ((array) $result->lesson_percents as $name => $value) {
                    if (is_numeric($value)) {
                        $subjects[(string) $name] = round((float) $value, 1);
                    }
                }

                $absent = in_array((string) $result->status, self::COMPANY_ABSENT_STATUSES, true);

                return [
                    'key' => 'company-'.$result->id,
                    'source' => self::SOURCE_COMPANY,
                    'source_label' => $result->company?->name ?? 'آزمون شرکتی',
                    'title' => $result->title,
                    'lesson' => null,
                    'date_sort' => $date?->timestamp ?? 0,
                    'date_iso' => $date?->toIso8601String(),
                    'date_text' => $date?->format('Y-m-d'),
                    'absent' => $absent,
                    'pending' => ! $absent && $result->percent === null,
                    'percent' => $result->percent !== null ? round((float) $result->percent, 1) : null,
                    'negative_percent' => null,
                    'questions' => $result->total_questions !== null ? (int) $result->total_questions : null,
                    'correct' => $result->correct_count !== null ? (int) $result->correct_count : null,
                    'wrong' => $result->wrong_count !== null ? (int) $result->wrong_count : null,
                    'blank' => $result->blank_count !== null ? (int) $result->blank_count : null,
                    'rank' => $result->exam_rank,
                    'participants' => $result->participants,
                    'subjects' => $subjects,
                    // The participants' per-subject average is not part of the
                    // import (institutes publish it inconsistently), so the
                    // class line only ever carries the row overall percent.
                    'class_subjects' => [],
                    'url' => null,
                    'brand' => $result->company?->color,
                    'attempt_id' => null,
                    'minutes' => null,
                ];
            });
    }

    /* --------------------------------------------------------------- summary */

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, array<string, mixed>>  $scored
     * @return array<string, mixed>
     */
    private static function summary(Collection $rows, Collection $scored, float $passLine): array
    {
        $values = $scored->pluck('percent')->map(fn ($v) => (float) $v)->values();

        $latest = $scored->last();
        $previous = $scored->count() > 1 ? $scored[$scored->count() - 2] : null;

        // Improvement is measured against the student's own mean before the
        // latest exam, never against a class or a neighbor: the delta must
        // stay honest even when the exam list is short.
        $delta = null;
        if ($latest && $previous) {
            $delta = round((float) $latest['percent'] - (float) $previous['percent'], 1);
        }

        $attended = $rows->where('absent', false)->count();
        $absences = $rows->where('absent', true)->count();

        // Direction of the delta, so the view never recomputes the verdict.
        $direction = match (true) {
            $delta === null => 'flat',
            $delta > 0 => 'up',
            $delta < 0 => 'down',
            default => 'flat',
        };

        return [
            'count' => $rows->count(),
            'scored' => $scored->count(),
            'latest' => $values->last(),
            'latest_title' => $latest['title'] ?? null,
            'latest_date_iso' => $latest['date_iso'] ?? null,
            'latest_source' => $latest['source_label'] ?? null,
            'average' => $values->isEmpty() ? null : round($values->avg(), 1),
            'best' => $values->isEmpty() ? null : round($values->max(), 1),
            'worst' => $values->isEmpty() ? null : round($values->min(), 1),
            'delta' => $delta,
            'delta_direction' => $direction,
            'absences' => $absences,
            'attended' => $attended,
            'attendance_percent' => $rows->count() > 0
                ? (int) round($attended / $rows->count() * 100)
                : null,
            'pass_line' => $passLine,
            'above_line' => $values->filter(fn (float $v) => $v >= $passLine)->count(),
        ];
    }

    /* -------------------------------------------------------------- subjects */

    /**
     * Per-subject standing, newest observation first.
     *
     * A subject is a weak point when its NEWEST value is below the pass line —
     * not its historical average: a student who fixed ریاضی last month should
     * not keep seeing it in the "needs attention" list.
     *
     * @param  Collection<int, array<string, mixed>>  $scored  newest last
     * @return list<array<string, mixed>>
     */
    private static function subjects(Collection $scored, float $passLine): array
    {
        $observations = [];

        foreach ($scored as $row) {
            foreach ($row['subjects'] as $name => $value) {
                $observations[$name][] = ['value' => (float) $value, 'row' => $row];
            }
        }

        $subjects = [];

        foreach ($observations as $name => $list) {
            $values = array_map(fn (array $o) => $o['value'], $list);
            $current = end($values);
            $first = $values[0];
            $previous = count($values) > 1 ? $values[count($values) - 2] : null;

            $subjects[] = [
                'name' => (string) $name,
                'percent' => round($current, 1),
                'average' => round(array_sum($values) / count($values), 1),
                'first' => round($first, 1),
                'delta' => $previous !== null ? round($current - $previous, 1) : null,
                'overall_delta' => count($values) > 1 ? round($current - $first, 1) : null,
                'samples' => count($values),
                'trend' => self::trend($values),
                'below' => $current < $passLine,
                'above' => $current >= $passLine,
                'gap' => round($current - $passLine, 1),
                'last_title' => $list[count($list) - 1]['row']['title'],
                'last_iso' => $list[count($list) - 1]['row']['date_iso'],
            ];
        }

        usort($subjects, fn ($a, $b) => $a['percent'] <=> $b['percent']);

        return $subjects;
    }

    /**
     * A short trend verdict for one subject, from its own value series.
     *
     * @param  list<float>  $values  oldest first
     */
    private static function trend(array $values): string
    {
        if (count($values) < 2) {
            return 'flat';
        }

        $last = $values[count($values) - 1];
        $best = max($values);
        $worst = min($values);

        // Within 3 points of the subject's own best, and not at its worst.
        if ($last >= $best - 3 && $best !== $worst) {
            return 'up';
        }

        if ($last <= $worst + 3 && $best !== $worst) {
            return 'down';
        }

        return 'flat';
    }
}
