<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LessonMaterial;
use App\Support\StudentAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The student's lessons library: published materials that match their
 * grade (or are open to every grade), grouped by subject, with one clean
 * download action per file.
 *
 * Everything keys off the authenticated student — downloads and the
 * library itself — and BelongsToTenant bounds the data to the academy.
 */
class StudentMaterialController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user('student');

        $teacherAccess = [];
        $materials = LessonMaterial::query()
            ->where('tenant_id', $student->tenant_id)
            ->visibleTo($student)
            ->orderByDesc('created_at')
            ->with('teacher')
            ->get()
            ->filter(function (LessonMaterial $material) use ($student, &$teacherAccess) {
                return $teacherAccess[$material->teacher_id] ??= $material->teacher
                    && StudentAccess::allows($material->teacher, $student);
            });

        return view('student.lessons', [
            'student' => $student,
            'bySubject' => $materials->groupBy(fn (LessonMaterial $m) => $m->subject ?: 'عمومی'),
            'total' => $materials->count(),
        ]);
    }

    public function download(Request $request, LessonMaterial $material): Response
    {
        $student = $request->user('student');

        // Match the library, including current teacher access after revocation.
        $allowed = (int) $material->tenant_id === (int) $student->tenant_id
            && $material->is_published
            && ($material->grade === null || $material->grade === $student->grade)
            && $material->teacher
            && StudentAccess::allows($material->teacher, $student);

        abort_unless($allowed, 404);

        $material->increment('download_count');

        return response()->download(
            public_path("tenants/{$material->tenant->slug}/".$material->file_path),
            $material->file_name
        );
    }
}
