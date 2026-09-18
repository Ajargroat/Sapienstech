<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\Student;
use App\Support\Academics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Assignment lifecycle for the teacher: create a task for a class (a
 * grade), watch its per-student status board (pending → submitted →
 * completed), and acknowledge submissions.
 *
 * A student of the target grade without a pivot row counts as 'pending' —
 * rows are only written when a student submits or the teacher intervenes.
 */
class TeacherAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $teacher = $request->user();

        $assignments = $teacher->assignments()
            ->withCount(['submissions'])
            ->orderByDesc('created_at')
            ->get();

        // Submitted-but-not-yet-acknowledged counts per assignment.
        $submittedCounts = AssignmentStudent::query()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->where('status', Assignment::STATUS_SUBMITTED)
            ->selectRaw('assignment_id, count(*) as total')
            ->groupBy('assignment_id')
            ->pluck('total', 'assignment_id');

        return view('teacher.assignments.index', [
            'assignments' => $assignments,
            'submittedCounts' => $submittedCounts,
            'statuses' => Assignment::STATUSES,
            'gradeOptions' => Academics::gradeOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('teacher.assignments.create', [
            'gradeOptions' => Academics::gradeOptions(),
            'subjects' => Academics::subjects(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:50'],
            'due_at' => ['nullable', 'date'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $request->user()->assignments()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'max_score' => $data['max_score'] ?? null,
            'is_published' => $request->boolean('is_published', true),
        ]);

        return redirect()
            ->route('teacher.assignments.index')
            ->with('success', 'تکلیف ایجاد شد.');
    }

    public function show(Request $request, Assignment $assignment): View
    {
        $teacher = $request->user();

        abort_unless($assignment->teacher_id === $teacher->id, 404);

        // The class roster: every student the assignment targets.
        $students = Student::query()
            ->when($assignment->grade, fn ($q, $grade) => $q->where('grade', $grade))
            ->orderBy('name')
            ->get();

        $submissions = $assignment->submissions->keyBy('student_id');

        return view('teacher.assignments.show', [
            'assignment' => $assignment,
            'students' => $students,
            'submissions' => $submissions,
            'statuses' => Assignment::STATUSES,
            'gradeOptions' => Academics::gradeOptions(),
        ]);
    }

    public function update(Request $request, Assignment $assignment)
    {
        $teacher = $request->user();

        abort_unless($assignment->teacher_id === $teacher->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:50'],
            'due_at' => ['nullable', 'date'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $assignment->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'max_score' => $data['max_score'] ?? null,
            'is_published' => $request->boolean('is_published', true),
        ]);

        return redirect()
            ->route('teacher.assignments.show', $assignment)
            ->with('success', 'تکلیف به‌روزرسانی شد.');
    }

    /** Acknowledge (or reopen) one student's work on this assignment. */
    public function updateSubmission(Request $request, Assignment $assignment, Student $student)
    {
        $teacher = $request->user();

        abort_unless($assignment->teacher_id === $teacher->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Assignment::STATUSES))],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission = AssignmentStudent::query()->firstOrNew([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);

        $submission->fill([
            'status' => $data['status'],
            'score' => $data['score'] ?? null,
            'note' => $data['note'] ?? $submission->note,
        ]);

        // Submitted/completed keep the delivery moment; only reopening
        // (back to pending) clears it.
        $submission->submitted_at = $data['status'] === Assignment::STATUS_PENDING
            ? null
            : ($submission->submitted_at ?? now());

        if (! $submission->exists) {
            $submission->tenant_id = $teacher->tenant_id;
        }

        $submission->save();

        return redirect()
            ->route('teacher.assignments.show', $assignment)
            ->with('success', 'وضعیت تکلیف به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Assignment $assignment)
    {
        $teacher = $request->user();

        abort_unless($assignment->teacher_id === $teacher->id, 404);

        $assignment->delete();

        return redirect()
            ->route('teacher.assignments.index')
            ->with('success', 'تکلیف حذف شد.');
    }
}
