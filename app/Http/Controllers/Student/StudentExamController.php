<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttemptAnswer;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
use App\Support\ExamGrader;
use App\Support\ExamReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The student's own exams: sitting one inside its window, and reviewing the
 * finished attempt afterwards.
 *
 * The clock is the server's. `time_limit_minutes` is counted from the
 * student's own `started_at`, so a window spanning several days still gives
 * each student the full limit; the browser only draws the countdown and keeps
 * an answer draft in localStorage.
 *
 * Like every other student route, the record is keyed off the authenticated
 * student and the BelongsToTenant global scope keeps it inside the tenant.
 * Grading lives in App\Support\ExamGrader — the single implementation, used
 * only by storeAttempt() below.
 */
class StudentExamController extends Controller
{
    /** Open the runner (or resume it) when the exam is inside its window. */
    public function show(Request $request, StudentAssignedQuiz $assignment): View|RedirectResponse
    {
        $student = $this->guard($request, $assignment);
        $test = $assignment->test;
        abort_unless($test, 404);

        // Already finished (or the window has closed on a finished attempt):
        // the result page is the only place left to go.
        if ($assignment->attempts()->where('status', 'completed')->exists()) {
            return redirect()->route('student.exams.result', $assignment);
        }

        abort_unless($assignment->windowOpen(), 404);

        $questions = $test->questions()->with('answers')->get();

        if ($questions->isEmpty()) {
            return redirect()
                ->route('student.dashboard')
                ->withErrors(['exam' => 'این آزمون هنوز سوالی ندارد.']);
        }

        // One in-progress attempt per assignment: opening the runner starts the
        // clock, refreshing it resumes the same attempt with the same start.
        $attempt = $assignment->attempts()->where('status', 'in_progress')->latest('id')->first();

        if (! $attempt) {
            $attempt = $assignment->attempts()->create([
                'tenant_id' => $assignment->tenant_id,
                'student_id' => $student->id,
                'test_id' => $test->id,
                'status' => 'in_progress',
                'started_at' => now(),
                // Not finished yet: no duration and no completion stamp. The
                // base schema defaulted both from the submit-time-only era.
                'time_taken_seconds' => 0,
                'completed_at' => null,
            ]);

            // Records that the attempt was opened — a history marker only.
            // Dashboard reachability derives from windowOpen(), never from
            // this stored literal, so starting an exam cannot hide the card.
            $assignment->update(['status' => 'in_progress']);
        }

        return view('student.exam-runner', [
            'assignment' => $assignment,
            'test' => $test,
            'attempt' => $attempt,
            'questions' => $questions,
            'remaining' => $this->remainingSeconds($assignment, $attempt),
        ]);
    }

    /** Submit the attempt and grade it. */
    public function storeAttempt(Request $request, StudentAssignedQuiz $assignment): RedirectResponse
    {
        $student = $this->guard($request, $assignment);
        $test = $assignment->test;
        abort_unless($test, 404);

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'integer'],
        ]);

        $attempt = $assignment->attempts()->where('status', 'in_progress')->latest('id')->first();
        abort_unless($attempt, 404);

        $questions = $test->questions()->with('answers')->get()->keyBy('id');
        $result = ExamGrader::grade($test, $questions, $data['answers'] ?? []);

        // Server-authoritative: elapsed time from the stored start, never the
        // client's stopwatch, capped at the limit when one is set.
        $elapsed = (int) $attempt->started_at->diffInSeconds(now());
        $limit = $this->limitSeconds($assignment);
        $timeTaken = $limit > 0 ? min($elapsed, $limit) : $elapsed;

        DB::transaction(function () use ($assignment, $student, $attempt, $questions, $result, $timeTaken) {
            $attempt->update([
                'status' => 'completed',
                'completed_at' => now(),
                'score_raw' => $result['raw'],
                'score_simple_percent' => $result['simple'],
                'score_negative_percent' => $result['negative'],
                'time_taken_seconds' => $timeTaken,
            ]);

            foreach ($questions as $id => $question) {
                $verdict = $result['verdicts'][(int) $id] ?? ['chosen' => null, 'is_correct' => false, 'is_graded' => true];

                AttemptAnswer::create([
                    'tenant_id' => $attempt->tenant_id,
                    'attempt_id' => $attempt->id,
                    'student_id' => $student->id,
                    'question_id' => $id,
                    'chosen_answer_id' => $verdict['chosen'],
                    'is_correct' => $verdict['is_correct'],
                    'is_graded' => $verdict['is_graded'],
                ]);
            }

            $assignment->update(['status' => 'completed', 'is_completed' => true]);
        });

        return redirect()->route('student.exams.result', $assignment);
    }

    public function result(Request $request, StudentAssignedQuiz $assignment): View
    {
        $this->guard($request, $assignment);
        abort_unless($assignment->test, 404);

        // Same attempt the consultant's result page shows: the newest one
        // that actually finished.
        $attempt = $assignment->attempts()
            ->where('status', 'completed')
            ->latest('id')
            ->first();
        abort_unless($attempt, 404);

        $attempt->load('answers');

        return view('student.exam-result', [
            'assignment' => $assignment,
            'test' => $assignment->test,
            'attempt' => $attempt,
            'report' => ExamReport::fromAttempt($attempt),
            'answers' => $attempt->answers->keyBy('question_id'),
            'questions' => $assignment->test->questions()->with('answers')->get(),
        ]);
    }

    /** The assignment must be this student's own, inside the current tenant. */
    private function guard(Request $request, StudentAssignedQuiz $assignment)
    {
        $student = $request->user('student');

        abort_unless((int) $assignment->student_id === (int) $student->id, 404);

        return $student;
    }

    /** Configured limit in seconds; 0 when the exam is untimed. */
    private function limitSeconds(StudentAssignedQuiz $assignment): int
    {
        return (int) ($assignment->test?->time_limit_minutes ?? 0) * 60;
    }

    /** Seconds left for this student, floored at zero (0 = untimed). */
    private function remainingSeconds(StudentAssignedQuiz $assignment, StudentTestAttempt $attempt): int
    {
        $limit = $this->limitSeconds($assignment);

        if ($limit === 0) {
            return 0;
        }

        return max(0, $limit - (int) $attempt->started_at->diffInSeconds(now()));
    }
}
