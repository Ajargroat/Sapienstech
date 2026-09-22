<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Support\AssignmentFiles;
use App\Support\StudentAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's assignment workspace: the tasks published for their class
 * (grade), each with a live status pill — pending → submitted → completed
 * — plus the submit action that moves it forward.
 *
 * Status rows are lazy: opening a task never writes; only submitting (or
 * the teacher's acknowledgement) creates the pivot row.
 */
class StudentAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user('student');

        $teacherAccess = [];
        $assignments = Assignment::query()
            ->where('tenant_id', $student->tenant_id)
            ->visibleTo($student)
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->orderByDesc('created_at')
            ->with(['teacher', 'submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->get()
            ->filter(function (Assignment $assignment) use ($student, &$teacherAccess) {
                return $teacherAccess[$assignment->teacher_id] ??= $assignment->teacher
                    && StudentAccess::allows($assignment->teacher, $student);
            });

        return view('student.assignments.index', [
            'student' => $student,
            'assignments' => $assignments,
            'statuses' => Assignment::STATUSES,
        ]);
    }

    public function show(Request $request, Assignment $assignment): View
    {
        $student = $request->user('student');

        abort_unless($this->visible($student, $assignment), 404);

        return view('student.assignments.show', [
            'student' => $student,
            'assignment' => $assignment,
            'statuses' => Assignment::STATUSES,
            'submission' => $assignment->submissionFor($student->id),
        ]);
    }

    /** Mark the assignment as handed in (status: pending → submitted). */
    public function submit(Request $request, Assignment $assignment)
    {
        $student = $request->user('student');

        abort_unless($this->visible($student, $assignment), 404);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            // The student's own file: usually a phone photo of the finished work.
            'file' => AssignmentFiles::rules(),
        ]);

        $submission = AssignmentStudent::query()->firstOrNew([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);

        // A teacher-acknowledged task is final on this side.
        if ($submission->status === Assignment::STATUS_COMPLETED) {
            return back()->with('error', 'این تکلیف تکمیل‌شده است.');
        }

        $submission->tenant_id ??= $student->tenant_id;
        $submission->status = Assignment::STATUS_SUBMITTED;
        $submission->submitted_at = $submission->submitted_at ?? now();
        $submission->note = $data['note'] ?? $submission->note;

        // Re-submitting with a new file replaces the previous upload, which
        // TenantUploads removes; no file in the request leaves it in place.
        if ($request->hasFile('file')) {
            $submission->file_path = AssignmentFiles::store($request->file('file'), $submission->file_path);
        }

        $submission->save();

        return redirect()
            ->route('student.assignments.show', $assignment)
            ->with('success', 'تکلیف تحویل شد.');
    }

    private function visible($student, Assignment $assignment): bool
    {
        $sameClassroom = $assignment->classroom_id === null
            || $student->classrooms()->whereKey($assignment->classroom_id)->exists();

        return (int) $assignment->tenant_id === (int) $student->tenant_id
            && $assignment->is_published
            && ($assignment->grade === null || $assignment->grade === $student->grade)
            && $sameClassroom
            && $assignment->teacher
            && StudentAccess::allows($assignment->teacher, $student);
    }
}
