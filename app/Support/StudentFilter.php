<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The tenant's student filter vocabulary in one place.
 *
 * Extracted from ConsultantDashboardController so the dashboard, the bulk
 * actions picker, and the bulk "apply to all matching" server-side re-query
 * all interpret search/grade/gender/major/sort identically. The bulk flows
 * in particular must re-apply filters server-side — a client-supplied
 * "select all" can never be trusted to enumerate ids.
 *
 * On top of the student's own columns, a filter set may narrow students by
 * what exists in their related workspaces (the dashboard filter carousel
 * pages): exam assignments, report-card rows, and schedule blocks. Those
 * domain filters ride along on bulk assignment requests so "assign to
 * everyone matching" always means the exact set the UI displayed.
 */
class StudentFilter
{
    public const SORTS = ['name_asc', 'name_desc', 'newest', 'oldest'];

    /** Keys match the student_assigned_quizzes.status enum. */
    public const EXAM_STATUSES = [
        'completed' => 'انجام‌شده',
        'grading' => 'در حال تصحیح',
        'in_progress' => 'در حال برگزاری',
        'scheduled' => 'برگزارنشده',
        'missed' => 'غیبت',
    ];

    /** exam_type values that get the small-quiz badge; everything else is comprehensive. */
    public const QUIZ_TYPE_VALUES = ['quiz', 'online_quiz', 'single_lesson'];

    /** Dashboard-level exam type filter (mirrors the exam creator's choice). */
    public const EXAM_TYPES = [
        'quiz' => 'آزمونک',
        'comprehensive' => 'فراگیر',
    ];

    /** The only lessons a consultant may build an exam from. */
    public const EXAM_LESSONS = ['زیست‌شناسی', 'شیمی', 'فیزیک', 'ریاضی', 'زمین‌شناسی'];

    /** Report-card (کارنامه) status vocabulary; internal 'missed' maps onto 'absent'. */
    public const REPORT_STATUSES = [
        'completed' => 'انجام‌شده',
        'grading' => 'در حال تصحیح',
        'pending' => 'در انتظار نتیجه',
        'absent' => 'غیبت',
    ];

    /** Source key/label for the internal (consultant-made) report-card bucket. */
    public const REPORT_INTERNAL = 'internal';

    public const REPORT_INTERNAL_LABEL = 'درون‌ساز';

    /** Assignment statuses whose exams appear on a report card. */
    public const REPORT_FINISHED_STATUSES = ['completed', 'grading', 'missed'];

    public const SCHEDULE_DONE_OPTIONS = [
        'done' => 'انجام‌شده',
        'todo' => 'انجام‌نشده',
    ];

    /** Persian week order; index === schedule day_index used by the bulk flows. */
    public const SCHEDULE_DAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /**
     * @return array{search:string,grade:string,gender:string,major:string,sort:string,
     *               exam_status:string,exam_lesson:string,exam_type:string,
     *               report_source:string,report_status:string,
     *               schedule_day:string,schedule_done:string}
     */
    public static function fromRequest(Request $request): array
    {
        // input() spans query string, POST body and JSON alike, so the same
        // reader serves the dashboard's GET filters and the bulk form's
        // hidden POST fields.
        $sort = (string) $request->input('sort', '');

        if (! in_array($sort, self::SORTS, true)) {
            $sort = '';
        }

        $enum = function (string $key, array $allowed) use ($request): string {
            $value = trim((string) $request->input($key, ''));

            return array_key_exists($value, $allowed) ? $value : '';
        };

        $examLesson = trim((string) $request->input('exam_lesson', ''));

        if (! in_array($examLesson, self::EXAM_LESSONS, true)) {
            $examLesson = '';
        }

        // Company slugs are tenant data, so the whitelist check effectively
        // lives in apply() (a stale slug simply matches nothing); here we
        // only keep the value shaped like a slug.
        $reportSource = mb_strtolower(trim((string) $request->input('report_source', '')));

        if ($reportSource !== ''
            && $reportSource !== self::REPORT_INTERNAL
            && ! preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $reportSource)) {
            $reportSource = '';
        }

        $scheduleDay = trim((string) $request->input('schedule_day', ''));

        if ($scheduleDay !== '' && (! ctype_digit($scheduleDay) || (int) $scheduleDay > 6)) {
            $scheduleDay = '';
        }

        return [
            'search' => trim((string) $request->input('search', '')),
            'grade' => trim((string) $request->input('grade', '')),
            'gender' => trim((string) $request->input('gender', '')),
            'major' => trim((string) $request->input('major', '')),
            'sort' => $sort,
            'exam_status' => $enum('exam_status', self::EXAM_STATUSES),
            'exam_lesson' => $examLesson,
            'exam_type' => $enum('exam_type', self::EXAM_TYPES),
            'report_source' => $reportSource,
            'report_status' => $enum('report_status', self::REPORT_STATUSES),
            'schedule_day' => $scheduleDay,
            'schedule_done' => $enum('schedule_done', self::SCHEDULE_DONE_OPTIONS),
        ];
    }

    /**
     * Apply a normalized filter set to a Student query.
     *
     * @param  array<string, string>  $filters
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(trim((string) ($filters['grade'] ?? '')) !== '', fn ($q) => $q->where('grade', trim((string) $filters['grade'])))
            ->when(trim((string) ($filters['gender'] ?? '')) !== '', fn ($q) => $q->where('gender', trim((string) $filters['gender'])))
            ->when(trim((string) ($filters['major'] ?? '')) !== '', fn ($q) => $q->where('major', trim((string) $filters['major'])));

        self::applyDomain($query, $filters);

        $sort = $filters['sort'] ?? '';

        return match ($sort) {
            'name_desc' => $query->orderByDesc('name'),
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            default => $query->orderBy('name'),
        };
    }

    /**
     * The relation-based narrowing (dashboard filter pages ۲–۴): each domain
     * filter keeps students who have at least one related row matching it.
     *
     * @param  array<string, string>  $filters
     */
    private static function applyDomain(Builder $query, array $filters): void
    {
        $examStatus = trim((string) ($filters['exam_status'] ?? ''));
        $examLesson = trim((string) ($filters['exam_lesson'] ?? ''));
        $examType = trim((string) ($filters['exam_type'] ?? ''));

        $query
            ->when($examStatus !== '', fn ($q) => $q->whereHas(
                'assignedQuizzes',
                fn ($a) => $a->where('student_assigned_quizzes.status', $examStatus)
            ))
            // tests.lesson stores several joined lessons as one string
            // ("زیست‌شناسی، شیمی"), so lesson membership is a LIKE match.
            ->when($examLesson !== '', fn ($q) => $q->whereHas(
                'assignedQuizzes.test',
                fn ($t) => $t->where('lesson', 'like', "%{$examLesson}%")
            ))
            ->when($examType !== '', fn ($q) => $q->whereHas(
                'assignedQuizzes.test',
                function ($t) use ($examType) {
                    $examType === 'quiz'
                        ? $t->whereIn('exam_type', self::QUIZ_TYPE_VALUES)
                        : $t->whereNotIn('exam_type', self::QUIZ_TYPE_VALUES);
                }
            ));

        $reportSource = trim((string) ($filters['report_source'] ?? ''));
        $reportStatus = trim((string) ($filters['report_status'] ?? ''));

        $query
            ->when($reportSource !== '', function ($q) use ($reportSource) {
                $reportSource === self::REPORT_INTERNAL
                    ? $q->whereHas('assignedQuizzes', fn ($a) => $a->whereIn('status', self::REPORT_FINISHED_STATUSES))
                    : $q->whereHas('companyExamResults.company', fn ($c) => $c->where('slug', $reportSource));
            })
            ->when($reportStatus !== '', function ($q) use ($reportStatus) {
                $q->where(function ($w) use ($reportStatus) {
                    $w->whereHas('companyExamResults', fn ($c) => $c->where('status', $reportStatus));

                    // Internal exams surface on report cards under the same
                    // vocabulary; 'missed' is the internal 'absent', and
                    // 'pending' has no internal equivalent by design.
                    $internal = ['completed' => 'completed', 'grading' => 'grading', 'absent' => 'missed'][$reportStatus] ?? null;

                    if ($internal !== null) {
                        $w->orWhereHas('assignedQuizzes', fn ($a) => $a->where('status', $internal));
                    }
                });
            });

        $scheduleDay = trim((string) ($filters['schedule_day'] ?? ''));
        $scheduleDone = trim((string) ($filters['schedule_done'] ?? ''));

        $query
            // MySQL DAYOFWEEK: 1=Sunday..7=Saturday; day_index is the Persian
            // week order (0=شنبه..6=جمعه) used across the schedule features.
            ->when($scheduleDay !== '', function ($q) use ($scheduleDay) {
                $dayOfWeek = (int) $scheduleDay === 0 ? 7 : (int) $scheduleDay + 1;

                $q->whereHas('scheduleItems', fn ($s) => $s->whereRaw('DAYOFWEEK(start_datetime) = ?', [$dayOfWeek]));
            })
            ->when($scheduleDone !== '', fn ($q) => $q->whereHas(
                'scheduleItems',
                fn ($s) => $s->where('is_completed', $scheduleDone === 'done' ? 1 : 0)
            ));
    }

    /**
     * The student ids a filter set selects, resolved server-side.
     *
     * @param  array<string, string>  $filters
     * @return list<int>
     */
    public static function ids(array $filters): array
    {
        $query = Student::query();

        self::apply($query, $filters);

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Count of active (non-empty) filters, for the dashboard badge. */
    public static function activeCount(array $filters): int
    {
        return count(array_filter([
            $filters['grade'] ?? '',
            $filters['gender'] ?? '',
            $filters['major'] ?? '',
            $filters['sort'] ?? '',
            $filters['exam_status'] ?? '',
            $filters['exam_lesson'] ?? '',
            $filters['exam_type'] ?? '',
            $filters['report_source'] ?? '',
            $filters['report_status'] ?? '',
            $filters['schedule_day'] ?? '',
            $filters['schedule_done'] ?? '',
        ], static fn ($v) => $v !== '' && $v !== null));
    }

    /**
     * Distinct values that exist among the current tenant's students, for
     * filter chips (shared by the dashboard and the bulk picker).
     */
    public static function distinctOptions(string $column): \Illuminate\Support\Collection
    {
        return Student::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }
}
