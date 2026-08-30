<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\View\View;

/**
 * Handles every student-specific destination reachable from the dashboard's
 * "Actions" menu (report card, exams, source permissions) plus the student
 * profile page. The schedule destination now has its own dedicated
 * controller, StudentScheduleController, since it needs real CRUD + a
 * JSON API rather than a static placeholder.
 *
 * Tenant isolation / cross-tenant access:
 * The {student} route parameter is resolved via Laravel's implicit route
 * model binding against the Student model. Because Student uses the
 * BelongsToTenant global scope, the binding query itself is already
 * constrained to the current tenant -- a Tenant A consultant requesting
 * /consultant/students/{id-that-belongs-to-tenant-B}/... will not find a
 * matching row and Laravel will throw a 404 (ModelNotFoundException)
 * automatically. No manual tenant_id comparison is needed or performed here.
 */
class StudentFeatureController extends Controller
{
    private const LABELS = [
        'report-card' => 'کارنامه دانش‌آموز',
        'exams' => 'آزمون‌های دانش‌آموز',
        'source-permissions' => 'دسترسی منابع دانش‌آموز',
    ];

    public function profile(Student $student): View
    {
        $tenant = tenant();

        abort_unless(
            $tenant && (int) $student->tenant_id === (int) $tenant->id,
            404
        );

        return view('consultant.students.profile', [
            'student' => $student,
        ]);
    }

    public function show(string $feature, Student $student): View
    {
        abort_unless(isset(self::LABELS[$feature]), 404);

        return view('consultant.student-feature-placeholder', [
            'title' => self::LABELS[$feature],
            'student' => $student,
        ]);
    }
}
