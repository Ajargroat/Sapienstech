<?php

namespace App\Support;

use App\Models\ExamCompany;
use App\Models\Student;
use App\Models\Test;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    /** Filters a request may supply several values of; each becomes "field[]". */
    public const MULTI_FIELDS = [
        'grade', 'gender', 'major', 'exam_status', 'exam_lesson',
        'report_source', 'report_status', 'schedule_day',
    ];

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

    /** Report-card status → internal assigned-quiz status equivalent. */
    private const REPORT_INTERNAL_STATUS_MAP = [
        'completed' => 'completed',
        'grading' => 'grading',
        'absent' => 'missed',
    ];

    public const SCHEDULE_DONE_OPTIONS = [
        'done' => 'انجام‌شده',
        'todo' => 'انجام‌نشده',
    ];

    /** Persian week order; index === schedule day_index used by the bulk flows. */
    public const SCHEDULE_DAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /**
     * @return array{search:string,grade:list<string>,gender:list<string>,major:list<string>,sort:string,
     *               exam_status:list<string>,exam_lesson:list<string>,exam_type:string,
     *               report_source:list<string>,report_status:list<string>,
     *               schedule_day:list<string>,schedule_done:string}
     */
    public static function fromRequest(Request $request): array
    {
        // input() spans query string, POST body and JSON alike, so the same
        // reader serves the dashboard's GET filters and the bulk form's
        // hidden POST fields. Multi-value fields (MULTI_FIELDS) read both
        // scalar and "field[]" arrays; every value is trimmed and whitelisted.
        $sort = (string) $request->input('sort', '');

        if (! in_array($sort, self::SORTS, true)) {
            $sort = '';
        }

        $enum = function (string $key, array $allowed) use ($request): string {
            $value = trim((string) $request->input($key, ''));

            return array_key_exists($value, $allowed) ? $value : '';
        };

        $multi = function (string $key, ?array $allowed = null, int $maxLength = 100, int $maxValues = 12) use ($request): array {
            $values = collect((array) $request->input($key, []))
                ->filter(fn ($v) => is_scalar($v))
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '');

            if ($allowed !== null) {
                $values = $values->filter(fn ($v) => array_key_exists($v, $allowed));
            }

            return $values
                ->unique()
                ->take($maxValues)
                ->map(fn ($v) => mb_substr($v, 0, $maxLength))
                ->values()
                ->all();
        };

        // Company slugs are tenant data, so the whitelist check effectively
        // lives in apply() (a stale slug simply matches nothing); here we
        // only keep values shaped like a slug.
        $reportSources = collect((array) $request->input('report_source', []))
            ->filter(fn ($v) => is_scalar($v))
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))
            ->filter(fn ($v) => $v !== '' && ($v === self::REPORT_INTERNAL || preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $v) === 1))
            ->unique()
            ->take(12)
            ->values()
            ->all();

        $scheduleDays = collect((array) $request->input('schedule_day', []))
            ->filter(fn ($v) => is_scalar($v))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '' && ctype_digit($v) && (int) $v <= 6)
            ->unique()
            ->values()
            ->all();

        return [
            'search' => trim((string) $request->input('search', '')),
            'grade' => $multi('grade', null, 50),
            'gender' => $multi('gender', null, 50),
            'major' => $multi('major', null, 100),
            'sort' => $sort,
            'exam_status' => $multi('exam_status', self::EXAM_STATUSES),
            'exam_lesson' => $multi('exam_lesson'),
            'exam_type' => $enum('exam_type', self::EXAM_TYPES),
            'report_source' => $reportSources,
            'report_status' => $multi('report_status', self::REPORT_STATUSES),
            'schedule_day' => $scheduleDays,
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
        $grades = self::values($filters['grade'] ?? []);
        $genders = self::values($filters['gender'] ?? []);
        $majors = self::values($filters['major'] ?? []);

        $query
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($grades !== [], fn ($q) => $q->whereIn('grade', $grades))
            ->when($genders !== [], fn ($q) => $q->whereIn('gender', $genders))
            ->when($majors !== [], fn ($q) => $q->whereIn('major', $majors));

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
        $examStatuses = self::values($filters['exam_status'] ?? []);
        $examLessons = self::values($filters['exam_lesson'] ?? []);
        $examType = trim((string) ($filters['exam_type'] ?? ''));

        $query
            ->when($examStatuses !== [], fn ($q) => $q->whereHas(
                'assignedQuizzes',
                fn ($a) => $a->whereIn('student_assigned_quizzes.status', $examStatuses)
            ))
            // tests.lesson stores several joined lessons as one string
            // ("زیست‌شناسی، شیمی"), so lesson membership is a LIKE match.
            ->when($examLessons !== [], fn ($q) => $q->whereHas(
                'assignedQuizzes.test',
                function ($t) use ($examLessons) {
                    $t->where(function ($w) use ($examLessons) {
                        foreach ($examLessons as $lesson) {
                            $w->orWhere('lesson', 'like', '%'.self::escapeLike($lesson).'%');
                        }
                    });
                }
            ))
            ->when($examType !== '', fn ($q) => $q->whereHas(
                'assignedQuizzes.test',
                function ($t) use ($examType) {
                    $examType === 'quiz'
                        ? $t->whereIn('exam_type', self::QUIZ_TYPE_VALUES)
                        : $t->whereNotIn('exam_type', self::QUIZ_TYPE_VALUES);
                }
            ));

        $reportSources = self::values($filters['report_source'] ?? []);
        $reportStatuses = self::values($filters['report_status'] ?? []);

        $query
            ->when($reportSources !== [], function ($q) use ($reportSources) {
                $q->where(function ($w) use ($reportSources) {
                    foreach ($reportSources as $source) {
                        $source === self::REPORT_INTERNAL
                            ? $w->orWhereHas('assignedQuizzes', fn ($a) => $a->whereIn('status', self::REPORT_FINISHED_STATUSES))
                            : $w->orWhereHas('companyExamResults.company', fn ($c) => $c->where('slug', $source));
                    }
                });
            })
            ->when($reportStatuses !== [], function ($q) use ($reportStatuses) {
                $q->where(function ($outer) use ($reportStatuses) {
                    foreach ($reportStatuses as $reportStatus) {
                        $internal = self::REPORT_INTERNAL_STATUS_MAP[$reportStatus] ?? null;

                        $outer->orWhere(function ($w) use ($reportStatus, $internal) {
                            $w->whereHas('companyExamResults', fn ($c) => $c->where('status', $reportStatus));

                            // Internal exams surface on report cards under the same
                            // vocabulary; 'missed' is the internal 'absent', and
                            // 'pending' has no internal equivalent by design.
                            if ($internal !== null) {
                                $w->orWhereHas('assignedQuizzes', fn ($a) => $a->where('status', $internal));
                            }
                        });
                    }
                });
            });

        $scheduleDays = self::values($filters['schedule_day'] ?? []);
        $scheduleDone = trim((string) ($filters['schedule_done'] ?? ''));

        $query
            // MySQL DAYOFWEEK: 1=Sunday..7=Saturday; day_index is the Persian
            // week order (0=شنبه..6=جمعه) used across the schedule features.
            ->when($scheduleDays !== [], function ($q) use ($scheduleDays) {
                $q->where(function ($w) use ($scheduleDays) {
                    foreach ($scheduleDays as $scheduleDay) {
                        $dayOfWeek = (int) $scheduleDay === 0 ? 7 : (int) $scheduleDay + 1;

                        $w->orWhereHas('scheduleItems', fn ($s) => $s->whereRaw('DAYOFWEEK(start_datetime) = ?', [$dayOfWeek]));
                    }
                });
            })
            ->when($scheduleDone !== '', fn ($q) => $q->whereHas(
                'scheduleItems',
                fn ($s) => $s->where('is_completed', $scheduleDone === 'done' ? 1 : 0)
            ));
    }

    /** Trimmed list of strings from a scalar-or-array filter input. */
    private static function values(mixed $raw): array
    {
        return array_values(array_filter(
            array_map(static fn ($v) => trim((string) $v), (array) $raw),
            static fn ($v) => $v !== '',
        ));
    }

    /** Neutralize LIKE wildcards in a client-supplied value. */
    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
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
        $active = 0;

        foreach (self::MULTI_FIELDS as $field) {
            if (! empty($filters[$field])) {
                $active++;
            }
        }

        foreach (['sort', 'exam_type', 'schedule_done'] as $field) {
            if (trim((string) ($filters[$field] ?? '')) !== '') {
                $active++;
            }
        }

        return $active;
    }

    /**
     * Distinct values that exist among the current tenant's students, for
     * filter chips (shared by the dashboard and the bulk picker).
     */
    public static function distinctOptions(string $column): Collection
    {
        return Student::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    /**
     * The lesson vocabulary the tenant's own tests actually cover: tests
     * store joined lessons as one string ("زیست‌شناسی، شیمی"), so the
     * constant whitelist alone under-reports what's filterable. Falls back
     * to the built-in science list when the tenant has no tests yet.
     */
    public static function examLessons(): Collection
    {
        $lessons = Test::query()
            ->whereNotNull('lesson')
            ->pluck('lesson')
            ->flatMap(fn ($lesson) => explode('،', (string) $lesson))
            ->map(fn ($lesson) => trim($lesson))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $lessons->isEmpty() ? collect(self::EXAM_LESSONS) : $lessons;
    }

    /**
     * Per-option student counts for the filter menus, mirroring the
     * report-card menu's label + count pill. Every count is tenant-scoped
     * and independent of the other active filters (same simplification as
     * StudentReportCardController's per-source counts).
     *
     * @return array<string, array<int|string, int>>
     */
    public static function optionCounts(): array
    {
        $counts = ['total' => Student::query()->count()];

        foreach (['grade', 'gender', 'major'] as $column) {
            $counts[$column] = Student::query()
                ->selectRaw("{$column} as value, count(*) as total")
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->pluck('total', 'value')
                ->mapWithKeys(fn ($n, $v) => [(string) $v => (int) $n])
                ->all();
        }

        $counts['exam_status'] = [];
        foreach (self::EXAM_STATUSES as $key => $label) {
            $counts['exam_status'][$key] = Student::query()
                ->whereHas('assignedQuizzes', fn ($a) => $a->where('student_assigned_quizzes.status', $key))
                ->count();
        }

        $counts['exam_lesson'] = [];
        foreach (self::examLessons() as $lesson) {
            $counts['exam_lesson'][$lesson] = Student::query()
                ->whereHas('assignedQuizzes.test', fn ($t) => $t->where('lesson', 'like', '%'.self::escapeLike($lesson).'%'))
                ->count();
        }

        $counts['exam_type'] = [];
        foreach (self::EXAM_TYPES as $key => $label) {
            $counts['exam_type'][$key] = Student::query()
                ->whereHas('assignedQuizzes.test', function ($t) use ($key) {
                    $key === 'quiz'
                        ? $t->whereIn('exam_type', self::QUIZ_TYPE_VALUES)
                        : $t->whereNotIn('exam_type', self::QUIZ_TYPE_VALUES);
                })
                ->count();
        }

        $counts['report_source'] = [];
        foreach (ExamCompany::query()->orderBy('name')->get(['slug']) as $company) {
            $counts['report_source'][$company->slug] = Student::query()
                ->whereHas('companyExamResults.company', fn ($c) => $c->where('slug', $company->slug))
                ->count();
        }

        $counts['report_source'][self::REPORT_INTERNAL] = Student::query()
            ->whereHas('assignedQuizzes', fn ($a) => $a->whereIn('status', self::REPORT_FINISHED_STATUSES))
            ->count();

        $counts['report_status'] = [];
        foreach (self::REPORT_STATUSES as $key => $label) {
            $internal = self::REPORT_INTERNAL_STATUS_MAP[$key] ?? null;

            $counts['report_status'][$key] = Student::query()
                ->where(function ($w) use ($key, $internal) {
                    $w->whereHas('companyExamResults', fn ($c) => $c->where('status', $key));

                    if ($internal !== null) {
                        $w->orWhereHas('assignedQuizzes', fn ($a) => $a->where('status', $internal));
                    }
                })
                ->count();
        }

        $counts['schedule_day'] = [];
        foreach (self::SCHEDULE_DAYS as $index => $day) {
            $dayOfWeek = $index === 0 ? 7 : $index + 1;

            $counts['schedule_day'][$index] = Student::query()
                ->whereHas('scheduleItems', fn ($s) => $s->whereRaw('DAYOFWEEK(start_datetime) = ?', [$dayOfWeek]))
                ->count();
        }

        $counts['schedule_done'] = [];
        foreach (self::SCHEDULE_DONE_OPTIONS as $key => $label) {
            $counts['schedule_done'][$key] = Student::query()
                ->whereHas('scheduleItems', fn ($s) => $s->where('is_completed', $key === 'done' ? 1 : 0))
                ->count();
        }

        return $counts;
    }
}
