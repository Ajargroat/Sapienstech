<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Student;
use App\Support\Academics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student management for teachers: the tenant's student roster, searchable
 * and grade-filterable, plus a per-student detail view.
 *
 * Tenant isolation comes from Student::BelongsToTenant (global scope) —
 * this controller never touches tenant_id, and a teacher only ever sees
 * students of their own academy.
 */
class TeacherStudentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $grade = trim((string) $request->input('grade', ''));

        $students = Student::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($grade !== '', fn ($q) => $q->where('grade', $grade))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $gradeCounts = Student::query()
            ->selectRaw('grade, count(*) as total')
            ->whereNotNull('grade')
            ->groupBy('grade')
            ->pluck('total', 'grade');

        return view('teacher.students.index', [
            'students' => $students,
            'search' => $search,
            'grade' => $grade,
            'gradeOptions' => Academics::gradeOptions(),
            'gradeCounts' => $gradeCounts,
        ]);
    }

    public function show(Request $request, Student $student): View
    {
        // Route binding through the tenant-scoped model: a foreign-tenant id
        // never resolves. The teacher relationship narrows to own data.
        $teacher = $request->user();

        $assignments = $teacher->assignments()
            ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', $student->grade))
            ->where(fn ($q) => $q
                ->whereNull('classroom_id')
                ->orWhereIn('classroom_id', $student->classrooms()->select('classrooms.id')))
            ->with(['submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderByDesc('created_at')
            ->get();

        $materials = $teacher->lessonMaterials()
            ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', $student->grade))
            ->latest()
            ->get(['id', 'title', 'subject', 'grade', 'file_name']);

        return view('teacher.students.show', [
            'student' => $student,
            'assignments' => $assignments,
            'statuses' => Assignment::STATUSES,
            'materials' => $materials,
            'gradeOptions' => Academics::gradeOptions(),
        ]);
    }
}
