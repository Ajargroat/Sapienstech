<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentAssignedQuiz;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only exam pages inside the student portal. The student portal has no
 * exam-taking UI (sessions are run by the consultant's exam runner); this
 * controller only exposes the student's own finished attempts for review —
 * the student-side mirror of the consultant's exam result page.
 *
 * Like every other student route, the record is keyed off the authenticated
 * student and the BelongsToTenant global scope keeps it inside the tenant.
 */
class StudentExamController extends Controller
{
    public function result(Request $request, StudentAssignedQuiz $assignment): View
    {
        $student = $request->user('student');

        abort_unless((int) $assignment->student_id === (int) $student->id, 404);
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
            'answers' => $attempt->answers->keyBy('question_id'),
            'questions' => $assignment->test->questions()->with('answers')->get(),
        ]);
    }
}
