<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ScheduleItem;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentDeal;
use App\Models\StudentTestAttempt;
use App\Support\DealService;
use App\Support\StudentFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * The student's own workspace: this week's sessions, next month's upcoming
 * exams, recent results and headline stats — the student-side mirror of the
 * consultant dashboard.
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
            // Counted off the pivot relation: withCount('questions') loses its
            // aggregate to orderByPivot inside a MySQL subquery and the card
            // falls back to a stale question_count.
            ->with(['test' => fn ($query) => $query->withCount(['questionRows as questions_count']), 'latestAttempt'])
            ->orderByRaw('COALESCE(student_assigned_quizzes.scheduled_at, student_assigned_quizzes.assigned_at) DESC')
            ->get();

        // Upcoming exams are windowed to the next month (from today) so the
        // dashboard never drifts into a far-future backlog; nearest first.
        // An exam whose start already passed still lists while its window is
        // open — that is the one the student is meant to be sitting.
        $monthEnd = $today->copy()->addMonth()->endOfDay();
        $todayStart = $today->copy()->startOfDay();

        // Reachability is the window, not the stored status: opening the
        // runner writes status=in_progress (it records that an attempt was
        // opened), and a NULL end never closes the window — neither may hide
        // a card the student is still meant to sit. Completed/missed rows stay
        // out through the whitelist (completed has its own results section).
        // The badge keeps deriving its state from windowOpen()/effectiveStatus().
        $upcomingExams = $assignments
            ->filter(
                fn (StudentAssignedQuiz $a) => $a->test
                    && $a->scheduled_at
                    && in_array($a->status, ['scheduled', 'in_progress'], true)
                    && $a->scheduled_at->lte($monthEnd)
                    && ($a->scheduled_at->gte($todayStart) || $a->windowOpen())
            )
            ->sortBy(fn (StudentAssignedQuiz $a) => $a->scheduled_at->timestamp)
            ->values();

        $recentResults = $assignments
            ->filter(fn (StudentAssignedQuiz $a) => $a->status === 'completed' && $a->test && $a->latestAttempt)
            ->sortByDesc(fn (StudentAssignedQuiz $a) => $a->latestAttempt->completed_at?->timestamp ?? 0)
            ->take(4)
            ->values();

        $averagePercent = StudentTestAttempt::query()
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->avg('score_simple_percent');

        // Deal renewal banner: the student's active period whose decision
        // window (continue/withdraw) has opened — feature-gated like the
        // rest of the renewal flow. The table check keeps the dashboard
        // working before the deals migration has been applied.
        $dealBanner = site('features.deals', false) && Schema::hasTable('student_deals')
            ? StudentDeal::query()
                ->where('student_id', $student->id)
                ->whereNull('renewed_at')
                ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW)
                ->whereDate('ends_on', '<=', $today->copy()->addDays(DealService::WINDOW_DAYS)->toDateString())
                ->orderBy('ends_on')
                ->first()
            : null;

        return view('student.dashboard', [
            'student' => $student,
            'weekItems' => $weekItems,
            'weekStart' => $weekStart,
            'upcomingExams' => $upcomingExams,
            'recentResults' => $recentResults,
            'statuses' => self::STATUSES,
            'quizTypes' => StudentFilter::QUIZ_TYPE_VALUES,
            'stats' => [
                'week_sessions' => $weekItems->count(),
                'week_done' => $weekItems->where('is_completed', true)->count(),
                // Exactly the cards shown above — the stat used to count every
                // stored 'scheduled' row regardless of the listing rule.
                'upcoming_exams' => $upcomingExams->count(),
                'completed_exams' => $assignments->where('status', 'completed')->count(),
                'average_percent' => $averagePercent === null ? null : (int) round((float) $averagePercent),
            ],
            'dealBanner' => $dealBanner,
        ]);
    }
}
