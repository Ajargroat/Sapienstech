<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles every student-specific destination reachable from the dashboard's
 * "Actions" menu (report card, exams, schedule, source permissions) plus
 * the student profile page.
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
        'schedule' => 'برنامه دانش‌آموز',
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

    /**
     * Persists the profile edit form. Which fields exist, whether they are
     * required, and their input type are all driven by
     * config('consultant.profile.form'), so each tenant can shape its own
     * profile form without touching the view or this controller. Avatar
     * storage settings (disk/path/limits) come from the same config file.
     */
    public function update(Request $request, Student $student): RedirectResponse
    {
        $avatarConfig = config('consultant.profile.avatar', []);

        $rules = [
            'avatar' => [
                'nullable',
                'image',
                'mimes:' . ($avatarConfig['mimes'] ?? 'jpeg,jpg,png,webp'),
                'max:' . ($avatarConfig['max_size_kb'] ?? 1024),
            ],
        ];

        foreach (config('consultant.profile.form.fields', []) as $field) {
            if (! isset($field['key'])) {
                continue;
            }

            $rules[$field['key']] = [
                ($field['required'] ?? false) ? 'required' : 'nullable',
                ($field['type'] ?? 'text') === 'email' ? 'email' : 'string',
                'max:255',
            ];
        }

        $validated = $request->validate($rules);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store(
                $avatarConfig['path'] ?? 'avatars',
                $avatarConfig['disk'] ?? 'public'
            );
        }

        $student->update($validated);

        return redirect()
            ->route('consultant.student.profile', $student)
            ->with('status', 'پروفایل با موفقیت به‌روزرسانی شد.');
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
