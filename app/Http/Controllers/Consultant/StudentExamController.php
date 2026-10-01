<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\Test;
use App\Support\ExamReport;
use App\Support\QuestionBankMeta;
use App\Support\StudentFilter;
use App\Support\TenantUploads;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Consultant-facing exams workspace for a single student.
 *
 * Cards are real rows: student_assigned_quizzes (what the consultant assigned,
 * when and in which state) joined to tests (title, lesson, description, type,
 * question count, duration, total marks) and the student's latest attempt
 * (score). Tenant isolation follows the schedule controller: {student} is bound
 * through BelongsToTenant and re-asserted here so a missing tenant context
 * fails closed with a 404.
 *
 * The consultant never sits the exam. This controller builds and reviews it;
 * only the student portal (App\Http\Controllers\Student\StudentExamController)
 * creates attempts and grades them, through App\Support\ExamGrader.
 */
class StudentExamController extends Controller
{
    /** Keys match the student_assigned_quizzes.status enum. */
    private const STATUSES = StudentFilter::EXAM_STATUSES;

    /** exam_type values that get the small-quiz badge; everything else is comprehensive. */
    private const QUIZ_TYPES = StudentFilter::QUIZ_TYPE_VALUES;

    /** The candidate lessons/difficulties the picker may ever filter on. */
    private const DIFFICULTY_LABELS = ['Easy' => 'آسان', 'Medium' => 'متوسط', 'Hard' => 'سخت'];

    public function index(Request $request, Student $student): View
    {
        $this->assertStudentBelongsToTenant($student);

        $status = (string) $request->query('status', '');
        if ($status !== '' && ! array_key_exists($status, self::STATUSES)) {
            $status = '';
        }

        $search = trim((string) $request->query('search', ''));

        $exams = $this->baseQuery($student, $search)
            ->when($status !== '', fn (Builder $q) => $q->where('student_assigned_quizzes.status', $status))
            ->orderByRaw('COALESCE(student_assigned_quizzes.scheduled_at, student_assigned_quizzes.assigned_at) DESC')
            ->get()
            ->map(fn (StudentAssignedQuiz $assignment) => $this->toCard($assignment))
            ->filter()
            ->values();

        $statusCounts = $this->baseQuery($student, $search)
            ->selectRaw('status, COUNT(*) aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('consultant.students.exams', [
            'student' => $student,
            'exams' => $exams,
            'statuses' => self::STATUSES,
            'statusCounts' => $statusCounts,
            'total' => (int) $statusCounts->sum(),
            'status' => $status,
            'search' => $search,
            // Every picker filter is read off the bank itself — no hardcoded
            // option list may feed the exam picker (TASK AC7).
            'pickerLessons' => $this->subjectOptions(),
            'pickerChapters' => $this->chapterOptions(),
            'pickerDifficulties' => $this->difficultyOptions(),
            'pickerCorps' => $this->corpOptions($student),
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->assertStudentBelongsToTenant($student);
        abort_unless($request->user(), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'exam_type' => ['required', 'in:quiz,comprehensive'],
            'lesson' => ['required', 'array', 'min:1', 'max:5'],
            'lesson.*' => ['required', 'string', Rule::in($this->subjectOptions())],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'date' => ['required', 'string', 'max:32'],
            'date_jalali' => ['nullable', 'string', 'max:16'],
            'end' => ['required', 'string', 'max:32'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'description' => ['nullable', 'string', 'max:2000'],
            'questions' => ['nullable', 'array', 'max:500'],
            'questions.*' => ['integer'],
        ]);

        $scheduled = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $data['date']);

        if ($scheduled === false) {
            return back()->withInput()
                ->withErrors(['date' => 'تاریخ آزمون خوانا نیست؛ لطفاً دوباره وارد کنید.']);
        }

        // The window is explicit: an end date/time is required and must fall
        // after the start, however many days it spans (a 2-day window can hold
        // a 30-minute exam).
        $endsAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $data['end']);

        if ($endsAt === false) {
            return back()->withInput()
                ->withErrors(['end' => 'تاریخ پایان آزمون خوانا نیست؛ لطفاً دوباره وارد کنید.']);
        }

        if ($endsAt <= $scheduled) {
            return back()->withInput()
                ->withErrors(['end' => 'زمان پایان باید بعد از زمان شروع آزمون باشد.']);
        }

        // Keep only ids that exist in the shared bank, in the submitted order.
        $submitted = collect($data['questions'] ?? [])->map('intval')->filter()->unique()->values();
        $allowed = Question::whereIn('id', $submitted)->pluck('id')->all();
        $ordered = $submitted->intersect($allowed)->values();

        if ($ordered->count() !== $submitted->count()) {
            return back()->withInput()->withErrors(['questions' => 'برخی سوالات انتخابی معتبر نیستند.']);
        }

        $scheduledAt = $scheduled->format('Y-m-d H:i:s');
        $endsAtString = $endsAt->format('Y-m-d H:i:s');
        $userId = (int) $request->user()->id;
        // Scores are shown as percentages, so total_marks is no longer a form
        // field; the fixed scale only keeps the legacy raw-score math working.
        $totalMarks = 20.0;
        $lesson = implode('، ', $data['lesson']);
        $cover = $request->hasFile('cover')
            ? TenantUploads::store($request->file('cover'), 'exam-covers')
            : null;

        DB::transaction(function () use ($student, $data, $scheduledAt, $endsAtString, $userId, $ordered, $totalMarks, $lesson, $cover) {
            $test = new Test;
            $test->test_title = $data['title'];
            $test->lesson = $lesson;
            $test->exam_type = $data['exam_type'];
            $test->description = $data['description'] ?? null;
            $test->cover_image_path = $cover;
            // The count is always derived from the selection, never typed in.
            $test->question_count = $ordered->count() ?: null;
            $test->time_limit_minutes = $data['time_limit_minutes'] ?? null;
            $test->total_marks = $totalMarks;
            $test->created_by_user_id = $userId;
            $test->save();

            if ($ordered->isNotEmpty()) {
                $points = round($test->total_marks / $ordered->count(), 2);
                foreach ($ordered->values() as $i => $questionId) {
                    $test->questions()->attach($questionId, [
                        'tenant_id' => $test->tenant_id,
                        'position' => $i + 1,
                        'points' => $points,
                    ]);
                }
            }

            $student->assignedQuizzes()->create([
                'test_id' => $test->id,
                'assigned_by_user_id' => $userId,
                'assigned_at' => now(),
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAtString,
                'status' => 'scheduled',
                'is_completed' => false,
            ]);
        });

        return redirect()
            ->route('consultant.student.exams', $student)
            ->with('success', 'آزمون با '.persian_digits($ordered->count()).' سوال ساخته شد و برای این دانش‌آموز اختصاص داده شد.');
    }

    public function questions(Request $request, Student $student): View
    {
        $this->assertStudentBelongsToTenant($student);

        $search = trim((string) $request->query('search', ''));

        // The picker popup's chip rows; only values present in the bank may
        // reach the query, so chips and results can never disagree.
        $difficulties = $this->difficultyOptions();
        $difficulty = (string) $request->query('difficulty', '');
        if (! in_array($difficulty, $difficulties, true)) {
            $difficulty = '';
        }

        // Keep the raw request list as the filter's on/off switch: a lesson the
        // bank no longer knows must narrow to *nothing*, not silently drop the
        // filter (the chips are checkboxes, so a stale value can survive a
        // back-navigation).
        $requestedLessons = array_map('strval', (array) $request->query('lessons', []));
        $lessons = array_values(array_intersect($this->subjectOptions(), $requestedLessons));

        $chapters = $this->chapterOptions();
        $chapter = (string) $request->query('chapter', '');
        if (! in_array($chapter, $chapters, true)) {
            $chapter = '';
        }

        $corp = (string) $request->query('corp', '');
        $corp = $corp !== '' ? (QuestionBankMeta::corp($corp) ?? '') : '';

        $bank = Question::query()
            ->with('answers')
            ->when($search !== '', fn ($q) => $q->where('question_text', 'like', "%{$search}%"))
            ->when($difficulty !== '', fn ($q) => $q->where('difficulty', $difficulty))
            ->when($requestedLessons !== [], fn ($q) => $q->whereIn('subject', $lessons))
            ->when($chapter !== '', fn ($q) => $q->where('chapter_label', $chapter))
            ->when($corp !== '', fn ($q) => $q->where('corp', $corp))
            ->orderByDesc('id')
            ->paginate(6)
            ->withQueryString();

        return view('consultant.students.partials.question-picker', [
            'bank' => $bank,
            'search' => $search,
            'difficulty' => $difficulty,
            'corpColors' => $this->corpColors($student),
        ]);
    }

    /**
     * The preview: the selected questions as the consultant ordered them.
     *
     * Order is owned here — the submitted `questions[]` list is re-emitted in
     * its own order, and the preview's up/down controls rewrite that list, so
     * what the consultant sees is what store() persists as position.
     */
    public function preview(Request $request, Student $student): View
    {
        $this->assertStudentBelongsToTenant($student);

        $data = $request->validate([
            'questions' => ['nullable', 'array', 'max:500'],
            'questions.*' => ['integer'],
        ]);

        $ids = collect($data['questions'] ?? [])->map('intval')->filter()->unique()->values();

        $questions = Question::query()
            ->with('answers')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return view('consultant.students.partials.exam-preview', [
            'questions' => $ids->map(fn (int $id) => $questions->get($id))->filter()->values(),
        ]);
    }

    public function run(Student $student, StudentAssignedQuiz $assignment): View|RedirectResponse
    {
        $this->assertStudentBelongsToTenant($student);
        abort_unless((int) $assignment->student_id === (int) $student->id, 404);

        $test = $assignment->test;
        abort_unless($test, 404);

        if ($assignment->attempts()->where('status', 'completed')->exists()) {
            return redirect()->route('consultant.student.exams.result', [$student, $assignment]);
        }

        $questions = $test->questions()->with('answers')->get();

        if ($questions->isEmpty()) {
            return redirect()
                ->route('consultant.student.exams', $student)
                ->withErrors(['exam' => 'این آزمون هنوز سوالی از بانک انتخاب نشده است.']);
        }

        // Read-only review: questions and their correct answers. The consultant
        // never sits the exam, so there is no form, no timer and no submit.
        return view('consultant.students.exam-review', [
            'student' => $student,
            'assignment' => $assignment,
            'test' => $test,
            'questions' => $questions,
            'difficultyLabels' => self::DIFFICULTY_LABELS,
        ]);
    }

    public function result(Student $student, StudentAssignedQuiz $assignment): View
    {
        $this->assertStudentBelongsToTenant($student);
        abort_unless((int) $assignment->student_id === (int) $student->id, 404);

        $attempt = $assignment->attempts()->where('status', 'completed')->latest('id')->first();
        abort_unless($attempt, 404);

        $attempt->load('answers');

        return view('consultant.students.exam-result', [
            'student' => $student,
            'assignment' => $assignment,
            'test' => $assignment->test,
            'attempt' => $attempt,
            'report' => ExamReport::fromAttempt($attempt),
            'answers' => $attempt->answers->keyBy('question_id'),
            'questions' => $assignment->test->questions()->with('answers')->get(),
        ]);
    }

    /**
     * Assignments for this student, optionally narrowed by the search box.
     * Deliberately does NOT order or filter by status so the same builder can
     * serve both the grid and the per-status counts.
     */
    private function baseQuery(Student $student, string $search): Builder
    {
        return StudentAssignedQuiz::query()
            ->where('student_assigned_quizzes.student_id', $student->id)
            ->with([
                // Counted off the pivot relation: withCount('questions') loses
                // its aggregate to orderByPivot inside a MySQL subquery.
                'test' => fn ($query) => $query->withCount(['questionRows as questions_count']),
                'latestAttempt',
            ])
            ->when($search !== '', fn (Builder $q) => $q->whereHas(
                'test',
                fn ($test) => $test->where('test_title', 'like', "%{$search}%")
                    ->orWhere('lesson', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ));
    }

    /**
     * @return array<string, mixed>|null Null when the parent test is gone.
     */
    private function toCard(StudentAssignedQuiz $assignment): ?array
    {
        $test = $assignment->test;

        if (! $test) {
            return null;
        }

        $score = $assignment->latestAttempt?->score_raw;
        $scheduled = $assignment->scheduled_at;

        $percent = ($score !== null && (float) $test->total_marks > 0)
            ? (int) round((float) $score / (float) $test->total_marks * 100)
            : null;

        return [
            'can_run' => (int) ($test->questions_count ?: 0) > 0,
            'id' => $assignment->id,
            'type' => in_array($test->exam_type, self::QUIZ_TYPES, true) ? 'quiz' : 'comprehensive',
            'title' => $test->test_title,
            'lesson' => $test->lesson ?: '—',
            'description' => $test->description ?: 'بدون توضیح مشاور',
            'questions' => (int) ($test->questions_count ?: $test->question_count),
            'duration' => (int) $test->time_limit_minutes,
            'percent' => $percent,
            // The browser renders $date_iso as a Jalali date; $date_text is the
            // Gregorian fallback shown before/if JavaScript does not run.
            'date_iso' => $scheduled ? $scheduled->format('Y-m-d\TH:i').'Z' : '',
            'date_text' => $scheduled ? persian_digits($scheduled->format('Y/m/d')) : '',
            'time' => $scheduled ? persian_digits($scheduled->format('H:i')) : '',
            // Derived from the clock, never stored: an exam inside its window
            // reads «در حال برگزاری» without any cron or status write.
            'status' => $assignment->effectiveStatus(),
            'cover' => $test->cover_image_path ? tenant_asset($test->cover_image_path) : null,
            'score' => $this->trimDecimal($score),
            'total' => $this->trimDecimal($test->total_marks) ?? '20',
        ];
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

    /**
     * Lessons present in the shared bank, most-used first — the picker's own
     * option source and the store() validation whitelist.
     *
     * @return list<string>
     */
    private function subjectOptions(): array
    {
        return $this->distinctByCount('subject');
    }

    /**
     * Chapter labels present in the bank, naturally ordered.
     *
     * @return list<string>
     */
    private function chapterOptions(): array
    {
        $labels = Question::query()
            ->whereNotNull('chapter_label')
            ->where('chapter_label', '!=', '')
            ->distinct()
            ->pluck('chapter_label')
            ->all();

        usort($labels, 'strnatcasecmp');

        return array_values($labels);
    }

    /**
     * Difficulty values actually stored in the bank, as raw enum values.

     *
     * @return list<string>
     */
    private function difficultyOptions(): array
    {
        return $this->distinctByCount('difficulty');
    }

    /**
     * Distinct non-empty values of $column, most-used first. Merges nothing —
     * the caller decides what a canonical value looks like.
     *
     * @return list<string>
     */
    private function distinctByCount(string $column): array
    {
        return Question::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->selectRaw("{$column} as value, COUNT(*) aggregate")
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->pluck('value')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    /**
     * Companies present in the bank, spelling-variants merged, ordered by how
     * many questions carry them — drives the picker filter.
     *
     * @return list<array{name: string, color: ?string}>
     */
    private function corpOptions(Student $student): array
    {
        $colors = $this->corpColors($student);
        $merged = [];

        Question::query()
            ->whereNotNull('corp')
            ->where('corp', '!=', '')
            ->selectRaw('corp, COUNT(*) aggregate')
            ->groupBy('corp')
            ->pluck('aggregate', 'corp')
            ->each(function (int $count, string $raw) use (&$merged) {
                $name = QuestionBankMeta::corp($raw) ?? $raw;
                $merged[$name] = ($merged[$name] ?? 0) + $count;
            });

        arsort($merged);

        return array_map(
            fn (string $name) => ['name' => $name, 'color' => $colors[$name] ?? null],
            array_keys($merged)
        );
    }

    /** canonical company name → badge color, from tenant exam_companies */
    private function corpColors(Student $student): array
    {
        $colors = [];

        foreach (DB::table('exam_companies')->where('tenant_id', $student->tenant_id)->get(['name', 'color']) as $company) {
            $name = QuestionBankMeta::corp($company->name);
            if ($name !== null) {
                $colors[$name] = $company->color;
            }
        }

        return $colors;
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
