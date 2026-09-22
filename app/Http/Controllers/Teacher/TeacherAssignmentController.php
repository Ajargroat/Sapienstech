<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\Academics;
use App\Support\AssignmentFiles;
use App\Support\TenantUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Assignment lifecycle for the teacher: create a task for a class (a grade,
 * optionally narrowed to one classroom of it), watch its per-student status
 * board (pending → submitted → completed), and acknowledge submissions.
 *
 * A student of the target grade (or classroom) without a pivot row counts as
 * 'pending' — rows are only written when a student submits or the teacher
 * intervenes.
 */
class TeacherAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $teacher = $request->user();

        $assignments = $teacher->assignments()
            ->with('classroom')
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
            'subjects' => Academics::subjects(),
            // The create dialog's classroom picker: grade => [id => name], so
            // the select can be filled from the grade the teacher picked.
            'classrooms' => Classroom::query()
                ->orderBy('name')
                ->get()
                ->groupBy('grade')
                ->map(fn ($group) => $group->pluck('name', 'id')->all())
                ->all(),
        ]);
    }

    public function store(Request $request)
    {
        $teacher = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:100'],
            'grade' => ['required', 'string', 'max:50'],
            'classroom_id' => [
                'nullable',
                'integer',
                // A classroom must be one of this tenant's, and of the grade
                // the assignment targets — the picker's pairing, enforced.
                Rule::exists('classrooms', 'id')->where(fn ($q) => $q
                    ->where('tenant_id', $teacher->tenant_id)
                    ->where('grade', $request->input('grade'))),
            ],
            'due_at' => ['nullable', 'date'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'is_published' => ['nullable', 'boolean'],
            // The teacher's own brief: one worksheet PDF or a photo of the task.
            'file' => AssignmentFiles::rules(),
        ]);

        $teacher->assignments()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $request->hasFile('file')
                ? AssignmentFiles::store($request->file('file'))
                : null,
            'subject' => $data['subject'] ?? null,
            'grade' => $data['grade'],
            'classroom_id' => $data['classroom_id'] ?? null,
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

        // The class roster: the assignment's classroom when narrowed, the
        // whole target grade otherwise.
        $students = Student::query()
            ->when(
                $assignment->classroom_id,
                fn ($q) => $q->whereIn('id', $assignment->classroom->students()->select('students.id')),
                fn ($q) => $q->where('grade', $assignment->grade),
            )
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
            'file' => AssignmentFiles::rules(),
        ]);

        $assignment->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            // A new upload replaces the old one (TenantUploads deletes it);
            // omitting the field entirely leaves the current attachment alone.
            'file_path' => $request->hasFile('file')
                ? AssignmentFiles::store($request->file('file'), $assignment->file_path)
                : $assignment->file_path,
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

        // A score is only meaningful against the assignment's own ceiling, so
        // that bound rides along whenever the teacher set one.
        $scoreRules = ['nullable', 'numeric', 'min:0', 'max:10000'];
        if ($assignment->max_score !== null) {
            $scoreRules[] = 'max:'.$assignment->max_score;
        }

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Assignment::STATUSES))],
            'score' => $scoreRules,
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission = AssignmentStudent::query()->firstOrNew([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);

        $submission->fill([
            'status' => $data['status'],
            'score' => $data['score'] ?? null,
            // An empty note means "clear it"; only a request that omits the
            // field entirely leaves the stored note untouched.
            'note' => array_key_exists('note', $data) ? $data['note'] : $submission->note,
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

        // The pivot rows cascade on delete, so their uploads would be orphaned
        // on disk — clear every file alongside the row itself.
        DB::transaction(function () use ($assignment) {
            TenantUploads::delete($assignment->file_path);

            foreach ($assignment->submissions as $submission) {
                TenantUploads::delete($submission->file_path);
            }

            $assignment->delete();
        });

        return redirect()
            ->route('teacher.assignments.index')
            ->with('success', 'تکلیف حذف شد.');
    }
}
