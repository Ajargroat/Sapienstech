<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\Test;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\StudentTestAttempt;


/**
 * Consultant-facing exams workspace for a single student.
 *
 * Cards are real rows: student_assigned_quizzes (what the consultant assigned,
 * when and in which state) joined to tests (title, lesson, description, type,
 * question count, duration, total marks) and the student's latest attempt
 * (score). Tenant isolation follows the schedule controller: {student} is bound
 * through BelongsToTenant and re-asserted here so a missing tenant context
 * fails closed with a 404.
 */
class StudentExamController extends Controller
{
    /** Keys match the student_assigned_quizzes.status enum. */
    private const STATUSES = [
        'completed' => 'انجام‌شده',
        'grading' => 'در حال تصحیح',
        'in_progress' => 'در حال برگزاری',
        'scheduled' => 'برگزارنشده',
        'missed' => 'غیبت',
    ];

    /** exam_type values that get the small-quiz badge; everything else is comprehensive. */
    private const QUIZ_TYPES = ['quiz', 'online_quiz', 'single_lesson'];

    public function index(Request $request, Student $student): View
    {
        $this->assertStudentBelongsToTenant($student);

        $status = (string) $request->query('status', '');
        if ($status !== '' && !array_key_exists($status, self::STATUSES)) {
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
            ->selectRaw('status, COUNT(*) as aggregate')
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
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->assertStudentBelongsToTenant($student);
        abort_unless($request->user(), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'exam_type' => ['required', 'in:quiz,comprehensive'],
            'lesson' => ['required', 'string', 'max:100'],
            'question_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'total_marks' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'date' => ['required', 'string', 'max:32'],
            'date_jalali' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:2000'],
            'questions' => ['nullable', 'array', 'max:500'],
            'questions.*' => ['integer'],
        ]);

        $scheduled = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $data['date']);

        if ($scheduled === false) {
            return back()->withInput()
                ->withErrors(['date' => 'تاریخ آزمون خوانا نیست؛ لطفاً دوباره وارد کنید.']);
        }

        // Keep only ids that exist in THIS tenant's bank, in the submitted order.
        $submitted = collect($data['questions'] ?? [])->map('intval')->filter()->unique()->values();
        $allowed = Question::whereIn('id', $submitted)->pluck('id')->all();
        $ordered = $submitted->intersect($allowed)->values();

        if ($ordered->count() !== $submitted->count()) {
            return back()->withInput()->withErrors(['questions' => 'برخی سوالات انتخابی معتبر نیستند.']);
        }

        $scheduledAt = $scheduled->format('Y-m-d H:i:s');
        $userId = (int) $request->user()->id;
        $totalMarks = (float) ($data['total_marks'] ?? 20);

        DB::transaction(function () use ($student, $data, $scheduledAt, $userId, $ordered, $totalMarks) {
            $test = new Test();
            $test->test_title = $data['title'];
            $test->lesson = $data['lesson'];
            $test->exam_type = $data['exam_type'];
            $test->description = $data['description'] ?? null;
            $test->question_count = $ordered->isNotEmpty() ? $ordered->count() : ($data['question_count'] ?? null);
            $test->time_limit_minutes = $data['time_limit_minutes'] ?? null;
            $test->total_marks = $totalMarks ?: 20;
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
        $difficulty = (string) $request->query('difficulty', '');
        if (! in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)) {
            $difficulty = '';
        }

        $bank = Question::query()
            ->with('answers')
            ->when($search !== '', fn ($q) => $q->where('question_text', 'like', "%{$search}%"))
            ->when($difficulty !== '', fn ($q) => $q->where('difficulty', $difficulty))
            ->orderByDesc('id')
            ->paginate(6)
            ->withQueryString();

        return view('consultant.students.partials.question-picker', [
            'bank' => $bank,
            'search' => $search,
            'difficulty' => $difficulty,
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

        return view('consultant.students.exam-runner', [
            'student' => $student,
            'assignment' => $assignment,
            'test' => $test,
            'questions' => $questions,
        ]);
    }

    public function storeAttempt(Request $request, Student $student, StudentAssignedQuiz $assignment): RedirectResponse
    {
        $this->assertStudentBelongsToTenant($student);
        abort_unless((int) $assignment->student_id === (int) $student->id, 404);

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer'],
            'time_taken_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $test = $assignment->test;
        abort_unless($test, 404);

        $questions = $test->questions()->with('answers')->get()->keyBy('id');
        $input = collect($data['answers'] ?? []);

        // A chosen option is only valid if it belongs to its own question.
        $chosen = [];
        foreach ($questions as $id => $question) {
            $answerId = (int) ($input[$id] ?? 0);
            if ($answerId && $question->answers->contains('id', $answerId)) {
                $chosen[$id] = $answerId;
            }
        }

        $count = max(1, $questions->count());
        $per = (float) $test->total_marks / $count;
        $correct = $wrong = 0;
        $raw = 0.0;

        foreach ($questions as $id => $question) {
            $answerId = $chosen[$id] ?? null;
            $isCorrect = $answerId && $question->answers->firstWhere('id', $answerId)->is_correct;

            if ($isCorrect) {
                $correct++;
                $raw += (float) ($question->pivot->points ?: $per);
            } elseif ($answerId) {
                $wrong++;
            }
        }

        $simple = round($correct / $count * 100, 2);
        $negative = round(max(0, ($correct - $wrong / 4) / $count * 100), 2);

        DB::transaction(function () use ($assignment, $student, $test, $questions, $chosen, $data, $raw, $simple, $negative) {
            $attempt = $assignment->attempts()->create([
                'tenant_id' => $assignment->tenant_id,
                'student_id' => $student->id,
                'test_id' => $test->id,
                'status' => 'completed',
                'started_at' => now()->subSeconds((int) ($data['time_taken_seconds'] ?? 0)),
                'completed_at' => now(),
                'score_raw' => round($raw, 2),
                'score_simple_percent' => $simple,
                'score_negative_percent' => $negative,
                'time_taken_seconds' => (int) ($data['time_taken_seconds'] ?? 0),
            ]);

            foreach ($questions as $id => $question) {
                $answerId = $chosen[$id] ?? null;

                AttemptAnswer::create([
                    'tenant_id' => $attempt->tenant_id,
                    'attempt_id' => $attempt->id,
                    'student_id' => $student->id,
                    'question_id' => $id,
                    'chosen_answer_id' => $answerId,
                    'is_correct' => (bool) ($answerId && $question->answers->firstWhere('id', $answerId)->is_correct),
                ]);
            }

            $assignment->update(['status' => 'completed', 'is_completed' => true]);
        });

        return redirect()->route('consultant.student.exams.result', [$student, $assignment]);
    }

    public function result(Student $student, StudentAssignedQuiz $assignment): View
    {
        $this->assertStudentBelongsToTenant($student);
        abort_unless((int) $assignment->student_id === (int) $student->id, 404);

        $attempt = $assignment->attempts()->where('status', 'completed')->latest('id')->first();
        abort_unless($attempt, 404);

        return view('consultant.students.exam-result', [
            'student' => $student,
            'assignment' => $assignment,
            'test' => $assignment->test,
            'attempt' => $attempt,
            'answers' => $attempt->answers()->get()->keyBy('question_id'),
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
                'test' => fn ($query) => $query->withCount('questions'),
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

        return [
            'can_run' => (int) ($test->questions_count ?: 0) > 0,
            'id' => $assignment->id,
            'type' => in_array($test->exam_type, self::QUIZ_TYPES, true) ? 'quiz' : 'comprehensive',
            'title' => $test->test_title,
            'lesson' => $test->lesson ?: '—',
            'description' => $test->description ?: 'بدون توضیح مشاور',
            'questions' => (int) ($test->questions_count ?: $test->question_count),
            'duration' => (int) $test->time_limit_minutes,
            // The browser renders $date_iso as a Jalali date; $date_text is the
            // Gregorian fallback shown before/if JavaScript does not run.
            'date_iso' => $scheduled ? $scheduled->format('Y-m-d\TH:i') . 'Z' : '',
            'date_text' => $scheduled ? persian_digits($scheduled->format('Y/m/d')) : '',
            'time' => $scheduled ? persian_digits($scheduled->format('H:i')) : '',
            'status' => $assignment->status ?? 'scheduled',
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

        if (!str_contains($string, '.')) {
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
