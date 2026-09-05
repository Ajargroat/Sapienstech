<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\CompanyExamResult;
use App\Models\ExamCompany;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Consultant-facing report-card (کارنامه) workspace for a single student.
 *
 * The grid merges two live sources into one normalized card shape:
 *
 *  - company_exam_results: mock exams from corporations (قلم‌چی، ماز، …) with
 *    percent, rank and per-lesson breakdowns;
 *  - student_assigned_quizzes + tests + student_test_attempts: the consultant's
 *    own exams, but only the ones that have finished (completed / grading /
 *    missed) — a report card records outcomes, not upcoming dates.
 *
 * Internal exams are never duplicated into company_exam_results: the exams
 * workspace stays the single source of truth and each internal card links to
 * its existing result page.
 *
 * Tenant isolation follows StudentExamController: {student} is bound through
 * BelongsToTenant and the tenant match is re-asserted here so a missing tenant
 * context fails closed with a 404.
 */
class StudentReportCardController extends Controller
{
    /** Shared status vocabulary; the internal 'missed' maps onto 'absent'. */
    private const STATUSES = [
        'completed' => 'انجام‌شده',
        'grading' => 'در حال تصحیح',
        'pending' => 'در انتظار نتیجه',
        'absent' => 'غیبت',
    ];

    /** Assignment statuses that count as a finished exam. */
    private const FINISHED_STATUSES = ['completed', 'grading', 'missed'];

    private const INTERNAL_KEY = 'internal';

    private const INTERNAL_LABEL = 'درون‌ساز';

    public function index(Request $request, Student $student): View
    {
        $this->assertStudentBelongsToTenant($student);

        $search = trim((string) $request->query('search', ''));

        $companies = ExamCompany::query()->orderBy('name')->get();

        $cards = $this->internalCards($student, $search)
            ->concat($this->companyCards($student, $search))
            ->sortByDesc('date_sort')
            ->values();

        $counts = $cards->groupBy('source_key')->map(fn (Collection $group) => $group->count());

        // Filter vocabulary: every company the tenant knows (even with zero
        // rows, so the list is stable) plus the internal bucket.
        $sources = $companies
            ->mapWithKeys(fn (ExamCompany $company) => [$company->slug => $company->name])
            ->put(self::INTERNAL_KEY, self::INTERNAL_LABEL);

        $source = (string) $request->query('source', '');
        if ($source !== '' && ! $sources->has($source)) {
            $source = '';
        }

        $visible = $source === ''
            ? $cards
            : $cards->where('source_key', $source)->values();

        return view('consultant.students.report-card', [
            'student' => $student,
            'cards' => $visible,
            'statuses' => self::STATUSES,
            'sources' => $sources,
            'source' => $source,
            'counts' => $counts,
            'total' => $cards->count(),
            'search' => $search,
        ]);
    }

    /**
     * Consultant-made exams that have an outcome, as report cards.
     */
    private function internalCards(Student $student, string $search): Collection
    {
        return StudentAssignedQuiz::query()
            ->where('student_assigned_quizzes.student_id', $student->id)
            ->whereIn('student_assigned_quizzes.status', self::FINISHED_STATUSES)
            ->with(['test' => fn ($query) => $query->withCount('questions'), 'latestAttempt'])
            ->when($search !== '', fn (Builder $q) => $q->whereHas(
                'test',
                fn ($test) => $test->where('test_title', 'like', "%{$search}%")
                    ->orWhere('lesson', 'like', "%{$search}%")
            ))
            ->get()
            ->map(fn (StudentAssignedQuiz $assignment) => $this->internalCard($student, $assignment))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>|null Null when the parent test is gone.
     */
    private function internalCard(Student $student, StudentAssignedQuiz $assignment): ?array
    {
        $test = $assignment->test;

        if (! $test) {
            return null;
        }

        $attempt = $assignment->latestAttempt;
        $score = $attempt?->score_raw;
        $total = $this->trimDecimal($test->total_marks) ?? '20';

        $percent = null;
        if ($attempt && $score !== null && (float) $total > 0) {
            $percent = (int) round((float) $score / (float) $total * 100);
        }

        $date = $assignment->scheduled_at ?? $attempt?->completed_at;

        return [
            'id' => 'internal-' . $assignment->id,
            'kind' => 'internal',
            'source_key' => self::INTERNAL_KEY,
            'source_label' => self::INTERNAL_LABEL,
            'brand' => null,
            'status' => $assignment->status === 'missed' ? 'absent' : ($assignment->status ?? 'completed'),
            'title' => $test->test_title,
            'lesson' => $test->lesson ?: '—',
            'description' => $test->description ?: 'آزمون ساخته‌شده توسط مشاور',
            'questions' => (int) ($test->questions_count ?: $test->question_count) ?: null,
            'correct' => null,
            'wrong' => null,
            'blank' => null,
            'participants' => null,
            'rank' => null,
            'percent' => $percent,
            'score' => $this->trimDecimal($score),
            'total' => $total,
            // The browser renders $date_iso as a Jalali date; $date_text is the
            // Gregorian fallback shown before/if JavaScript does not run.
            'date_iso' => $date ? $date->format('Y-m-d\TH:i') . 'Z' : '',
            'date_text' => $date ? persian_digits($date->format('Y/m/d')) : '',
            'date_sort' => $date?->timestamp ?? 0,
            'result_url' => $assignment->status === 'completed' && $attempt
                ? route('consultant.student.exams.result', [$student, $assignment])
                : null,
        ];
    }

    /**
     * Company-run mock exams, as report cards.
     */
    private function companyCards(Student $student, string $search): Collection
    {
        return CompanyExamResult::query()
            ->where('company_exam_results.student_id', $student->id)
            ->with('company')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('company_exam_results.title', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"));
                });
            })
            ->get()
            ->map(fn (CompanyExamResult $result) => [
                'id' => 'company-' . $result->id,
                'kind' => 'company',
                'source_key' => $result->company?->slug ?? 'unknown',
                'source_label' => $result->company?->name ?? '—',
                'brand' => $result->company?->color,
                'status' => $result->status,
                'title' => $result->title,
                'lesson' => $result->lesson_percents
                    ? implode('، ', array_keys($result->lesson_percents))
                    : 'چنددرس',
                'description' => $this->companyDescription($result),
                'questions' => $result->total_questions,
                'correct' => $result->correct_count,
                'wrong' => $result->wrong_count,
                'blank' => $result->blank_count,
                'participants' => $result->participants,
                'rank' => $result->exam_rank,
                'percent' => $result->percent !== null ? (int) round((float) $result->percent) : null,
                'score' => null,
                'total' => null,
                'date_iso' => $result->exam_date ? $result->exam_date->format('Y-m-d') . 'T00:00Z' : '',
                'date_text' => $result->exam_date ? persian_digits($result->exam_date->format('Y/m/d')) : '',
                'date_sort' => $result->exam_date?->timestamp ?? 0,
                'result_url' => null,
            ])
            ->values();
    }

    /**
     * One-line summary under the title: per-lesson percentages when present,
     * otherwise a plain outcome sentence.
     */
    private function companyDescription(CompanyExamResult $result): string
    {
        if ($result->status === 'absent') {
            return 'دانش‌آموز در این آزمون شرکت نکرد.';
        }

        if ($result->status === 'pending') {
            return 'نتیجه این آزمون هنوز توسط شرکت منتشر نشده است.';
        }

        if ($result->lesson_percents) {
            return collect($result->lesson_percents)
                ->map(fn ($value, $lesson) => $lesson . ' ' . persian_digits($value) . '%')
                ->implode(' · ');
        }

        return 'بدون تفکیک درس';
    }

    /** "17.50" → "17.5", "20.00" → "20"; keeps integers untouched. */
    private function trimDecimal(string|float|int|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;

        if (! str_contains($string, '.')) {
            return $string;
        }

        return rtrim(rtrim($string, '0'), '.') ?: '0';
    }

    private function assertStudentBelongsToTenant(Student $student): void
    {
        $tenant = tenant();

        abort_unless(
            $tenant && (int) $student->tenant_id === (int) $tenant->id,
            404
        );
    }
}
