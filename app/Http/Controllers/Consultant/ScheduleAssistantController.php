<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\ScheduleAssistantThread;
use App\Models\Student;
use App\Support\Ai\AiBudget;
use App\Support\Ai\AiHealth;
use App\Support\ScheduleAssistant;
use App\Support\StudentAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The weekly-planner AI assistant, consultant-facing.
 *
 * A sibling of StudentScheduleNoteController rather than part of it, for the
 * same reason that one was split out of StudentScheduleController: the planner
 * class is already large, and this is a self-contained surface.
 *
 * Isolation rules are restated here, not inherited — exactly as the note
 * controller does — because these are the rules that must not drift:
 *
 *  - {student} is re-checked against tenant() and against StudentAccess, fail
 *    closed (404). BelongsToTenant on Student would usually cover the first
 *    half, but an explicit check means a missing tenant context fails closed
 *    instead of matching everything.
 *  - The thread is keyed (tenant_id, user_id, student_id), so one consultant can
 *    never read or write another consultant's conversation.
 *  - The API key and endpoint never appear in any response: failures are mapped
 *    onto App\Support\Ai\AiError, and only that enum's copy is rendered.
 *
 * Nothing here writes to a schedule. "Apply" on a suggestion posts to the
 * EXISTING schedule item endpoints, so every mutation still flows through
 * StoreScheduleItemRequest and StudentScheduleController's proven guards. The
 * assistant literally has no write path of its own.
 */
class ScheduleAssistantController extends Controller
{
    public function __construct(
        private readonly ScheduleAssistant $assistant,
        private readonly AiHealth $health,
        private readonly AiBudget $budget,
    ) {}

    /** The stored conversation plus the meter and the service status. */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->assertAccess($request, $student);

        $thread = ScheduleAssistantThread::for($request->user(), $student);

        return response()->json([
            'messages' => $this->assistant->history($thread),
            'usage' => $this->budget->status(),
            'health' => $this->health->check(),
            'enabled' => ScheduleAssistant::enabled(),
        ]);
    }

    /** Ask a question (or request the weekly briefing when `mode=briefing`). */
    public function send(Request $request, Student $student): JsonResponse
    {
        $this->assertAccess($request, $student);

        if (! ScheduleAssistant::enabled()) {
            return response()->json([
                'ok' => false,
                'error' => 'unconfigured',
                'error_text' => (string) site('ai.disabled_text', ''),
                'retryable' => false,
            ], 503);
        }

        $data = $request->validate([
            'question' => ['nullable', 'string', 'max:2000'],
            'mode' => ['nullable', 'in:briefing,chat'],
            'week_start_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $mode = $data['mode'] ?? 'chat';
        $question = trim((string) ($data['question'] ?? ''));

        if ($mode !== 'briefing' && $question === '') {
            return response()->json([
                'ok' => false,
                'error' => 'bad_request',
                'error_text' => (string) site('ai.generic_error_text', ''),
                'retryable' => false,
            ], 422);
        }

        // Per-consultant throttle, keyed by tenant + user so one consultant's
        // burst cannot lock out a colleague. Same idiom as ChatService.
        $perMinute = max(1, (int) site('ai.rate_limit_per_minute', 6));
        $limitKey = 'ai:send:'.(tenant()?->id).':'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($limitKey, $perMinute)) {
            return response()->json([
                'ok' => false,
                'error' => 'rate_limited',
                'error_text' => (string) site('ai.rate_limited_text', ''),
                'retryable' => true,
            ], 429);
        }

        RateLimiter::hit($limitKey, 60);

        $weekStart = $this->resolveWeekStart($data['week_start_date'] ?? null);
        $thread = ScheduleAssistantThread::for($request->user(), $student);

        // Remember which week this conversation is about, without moving a
        // thread that is mid-discussion onto a new week behind the user's back.
        $thread->forceFill([
            'week_start_date' => $thread->week_start_date?->toDateString() ?? $weekStart,
        ])->save();

        try {
            $result = $this->assistant->ask($thread, $student, $question, $weekStart, $mode);
        } catch (Throwable $e) {
            // Last-resort guard. Anything unexpected is a bug, but it must not
            // become a stack trace in a panel beside a consultant's timetable,
            // and it must not echo provider internals upward.
            report($e);

            return response()->json([
                'ok' => false,
                'error' => 'server_error',
                'error_text' => (string) site('ai.generic_error_text', ''),
                'retryable' => true,
            ], 502);
        }

        $status = $result['ok'] ? 200 : ($result['error']?->status() ?? 502);

        return response()->json([
            'ok' => $result['ok'],
            'error' => $result['error']?->value,
            'error_text' => $result['error_text'],
            'retryable' => $result['retryable'],
            'reply' => $result['assistant_message']?->content,
            'suggestions' => $result['suggestions'],
            'usage' => $result['usage'],
            'week_start_date' => $weekStart,
        ], $status);
    }

    /** Throw this conversation away. Only ever the caller's own thread. */
    public function clear(Request $request, Student $student): JsonResponse
    {
        $this->assertAccess($request, $student);

        $thread = ScheduleAssistantThread::for($request->user(), $student);
        $this->assistant->clearThread($thread);

        return response()->json([
            'success' => true,
            'messages' => [],
            'usage' => $this->budget->status(),
        ]);
    }

    /**
     * The "try again" affordance behind the status dot.
     *
     * Refreshes rather than reads the cache on purpose: a consultant whose
     * operator has just fixed the key should see it work, not a cached failure.
     */
    public function healthCheck(Request $request, Student $student): JsonResponse
    {
        $this->assertAccess($request, $student);

        return response()->json([
            'health' => $this->health->refresh(),
            'enabled' => ScheduleAssistant::enabled(),
            'usage' => $this->budget->status(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    /**
     * Same two-step check the schedule controllers use: the resolved tenant
     * owns this student, and this consultant is allowed to see them at all
     * (StudentAccess is what enforces the student_staff boundary in a
     * hierarchical consultancy).
     */
    private function assertAccess(Request $request, Student $student): void
    {
        $tenant = tenant();

        abort_unless(
            $tenant && (int) $student->tenant_id === (int) $tenant->id,
            404
        );

        abort_unless($request->user(), 404);

        // A consultant must already be able to see this student on their roster
        // before an assistant may be told anything about them.
        abort_unless(
            StudentAccess::allows($request->user(), $student),
            404
        );
    }

    /** The same Saturday-anchored Persian week the planner uses. */
    private function resolveWeekStart(?string $requested): string
    {
        $base = null;

        if ($requested && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            try {
                $base = Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
            } catch (Throwable) {
                $base = null;
            }
        }

        $base ??= Carbon::today();

        return $base->copy()->subDays(($base->dayOfWeek + 1) % 7)->toDateString();
    }
}
