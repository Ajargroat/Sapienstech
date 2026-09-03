<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ScheduleItem;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's own workspace: this week's sessions, upcoming exams, recent
 * results and headline stats — the student-side mirror of the consultant
 * dashboard.
 *
 * Every query is keyed off the authenticated student (never a client-supplied
 * id), and the BelongsToTenant global scope on each model keeps the data
 * inside the current tenant on top of that.
 */
class StudentDashboardController extends Controller
{
    /** Keys match the student_assigned_quizzes.status enum. */
    private const STATUSES = [
        'completed' => 'انجام‌شده',
        'grading' => 'در حال تصحیح',
        'in_progress' => 'در حال برگزاری',
        'scheduled' => 'برگزارنشده',
        'missed' => 'غیبت',
    ];

    public function index(Request $request): View
    {
        $student = $request->user('student');

        // Saturday-anchored Persian week — same convention as the consultant
        // schedule editor (StudentScheduleController::resolveWeekStart()).
        $today = Carbon::today();
        $weekStart = $today->copy()->subDays(($today->dayOfWeek + 1) % 7)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $weekItems = ScheduleItem::query()
            ->where('student_id', $student->id)
            ->whereBetween('start_datetime', [$weekStart, $weekEnd])
            ->orderBy('start_datetime')
            ->get();

        $assignments = StudentAssignedQuiz::query()
            ->where('student_id', $student->id)
            ->with(['test', 'latestAttempt'])
            ->orderByRaw('COALESCE(student_assigned_quizzes.scheduled_at, student_assigned_quizzes.assigned_at) DESC')
            ->get();

        $upcomingExams = $assignments
            ->filter(fn (StudentAssignedQuiz $a) => $a->status === 'scheduled' && $a->test)
            ->take(4)
            ->values();

        $recentResults = $assignments
            ->filter(fn (StudentAssignedQuiz $a) => $a->status === 'completed' && $a->test && $a->latestAttempt)
            ->take(4)
            ->values();

        $averagePercent = StudentTestAttempt::query()
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->avg('score_simple_percent');

        return view('student.dashboard', [
            'student' => $student,
            'weekItems' => $weekItems,
            'upcomingExams' => $upcomingExams,
            'recentResults' => $recentResults,
            'statuses' => self::STATUSES,
            'stats' => [
                'week_sessions' => $weekItems->count(),
                'week_done' => $weekItems->where('is_completed', true)->count(),
                'upcoming_exams' => $assignments->where('status', 'scheduled')->count(),
                'completed_exams' => $assignments->where('status', 'completed')->count(),
                'average_percent' => $averagePercent === null ? null : (int) round((float) $averagePercent),
            ],
        ]);
    }
}
