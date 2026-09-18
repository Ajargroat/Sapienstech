<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\ClassSchedule;
use App\Models\LessonMaterial;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The teacher panel's landing page: headline stats, today's classes, the
 * assignments the teacher is waiting on, and the freshest student
 * submissions.
 *
 * Every query runs through the authenticated teacher ($request->user(),
 * web guard) — never a client-supplied teacher id — and the
 * BelongsToTenant global scope keeps all of it inside the tenant.
 */
class TeacherDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $teacher = $request->user();

        // Persian week: Saturday is day 0. Carbon's dayOfWeek is Sunday-based,
        // so the +1 mod 7 mapping is the same one the schedule editor uses.
        $persianDay = (int) ((now()->dayOfWeek + 1) % 7);

        $todayClasses = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $persianDay)
            ->orderBy('start_time')
            ->get();

        $assignments = Assignment::query()
            ->where('teacher_id', $teacher->id)
            ->withCount('submissions')
            ->get();

        $assignmentIds = $assignments->pluck('id');

        $pendingReviews = AssignmentStudent::query()
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', Assignment::STATUS_SUBMITTED)
            ->count();

        $completed = AssignmentStudent::query()
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', Assignment::STATUS_COMPLETED)
            ->count();

        $recentSubmissions = AssignmentStudent::query()
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', Assignment::STATUS_SUBMITTED)
            ->with(['student', 'assignment'])
            ->latest('submitted_at')
            ->take(5)
            ->get();

        $upcomingAssignments = $assignments
            ->filter(fn (Assignment $a) => $a->is_published && $a->due_at && $a->due_at->isFuture())
            ->sortBy(fn (Assignment $a) => $a->due_at->timestamp)
            ->take(5)
            ->values();

        return view('teacher.dashboard', [
            'teacher' => $teacher,
            'todayClasses' => $todayClasses,
            'recentSubmissions' => $recentSubmissions,
            'upcomingAssignments' => $upcomingAssignments,
            'statuses' => Assignment::STATUSES,
            'stats' => [
                'students' => Student::query()->count(),
                'materials' => LessonMaterial::query()->where('teacher_id', $teacher->id)->count(),
                'assignments' => $assignments->count(),
                'pending_reviews' => $pendingReviews,
                'completed' => $completed,
            ],
        ]);
    }
}
