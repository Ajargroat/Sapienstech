<?php

namespace App\Http\Controllers\Lessons;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateLessonBlocks;
use App\Models\BookLesson;
use App\Models\LessonAiDraft;
use App\Support\Ai\AiBudget;
use App\Support\Lessons\LessonAccess;
use App\Support\Lessons\LessonDrafter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Starts AI drafts and reports their status. **It never calls the provider.**
 *
 * Every POST here enqueues and returns; the queue worker (already part of the
 * deployment) does the spending. That is PREFERENCE §7.3's "never inline in an
 * HTTP request", and it is what keeps a slow provider from holding a web
 * worker — and a tenant's browser — hostage.
 *
 * All four routes 404 unless the matching `lessons.ai_*` flag is on, because
 * they live behind the same flag middleware as the rest of the surface.
 */
class LessonAiController extends Controller
{
    public function blocks(Request $request, BookLesson $lesson): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        abort_unless(LessonDrafter::blocksEnabled(), 404);

        $this->throttle($request->user()->id);

        $draft = LessonAiDraft::create([
            'lesson_id' => $lesson->id,
            'kind' => LessonAiDraft::KIND_BLOCKS,
            'status' => 'queued',
            'created_by_user_id' => $request->user()->id,
        ]);

        GenerateLessonBlocks::forDraft($draft)->dispatch();

        return redirect()
            ->to(lessons_route('editor.edit', $lesson))
            ->with('success', 'پیش‌نویس بلوک در صف قرار گرفت.');
    }

    public function draft(BookLesson $lesson, LessonAiDraft $draft): JsonResponse
    {
        LessonAccess::authorizeStaff($lesson);

        abort_unless((int) $draft->lesson_id === (int) $lesson->id, 404);

        return response()->json([
            'id' => $draft->id,
            'kind' => $draft->kind,
            'status' => $draft->status,
            'error_code' => $draft->error_code,
            'error_text' => $this->safeError($draft),
            'blocks' => $draft->kind === LessonAiDraft::KIND_BLOCKS ? (($draft->payload['blocks'] ?? null) ? count($draft->payload['blocks']) : 0) : null,
            'questions' => $draft->kind === LessonAiDraft::KIND_QUESTIONS ? count($draft->questions()) : null,
            'usage' => app(AiBudget::class)->status(),
        ]);
    }

    public function questions(Request $request, BookLesson $lesson): RedirectResponse
    {
        LessonAccess::authorizeStaff($lesson);

        abort_unless(LessonDrafter::questionsEnabled(), 404);

        $data = $request->validate([
            'count' => ['nullable', 'integer', 'min:3', 'max:'.max(3, (int) site('lessons.ai_questions_max', 12))],
        ]);

        $this->throttle($request->user()->id);

        $draft = LessonAiDraft::create([
            'lesson_id' => $lesson->id,
            'kind' => LessonAiDraft::KIND_QUESTIONS,
            'status' => 'queued',
            'created_by_user_id' => $request->user()->id,
        ]);

        GenerateLessonBlocks::forDraft($draft, $data['count'] ?? null)->dispatch();

        return redirect()
            ->to(lessons_route('questions.review', $lesson))
            ->with('success', 'تولید سؤال در صف قرار گرفت.');
    }

    /**
     * Provider bodies can quote the request back (including a key), so the
     * browser only ever sees OUR copy for the error code.
     */
    private function safeError(LessonAiDraft $draft): ?string
    {
        if ($draft->status !== 'refused' && $draft->status !== 'failed') {
            return null;
        }

        $key = match ($draft->error_code) {
            'unconfigured' => 'unconfigured_text',
            'rate_limited' => 'rate_limited_text',
            'budget_exhausted' => 'budget_exhausted_text',
            'unreachable', 'server_error', 'bad_request', 'bad_response' => 'generic_error_text',
            default => null,
        };

        return $key ? (string) site('ai.'.$key, '') : $draft->error_text;
    }

    private function throttle(int $userId): void
    {
        $perMinute = max(1, (int) site('ai.rate_limit_per_minute', 6));
        $key = 'lesson-ai:'.(tenant()?->id).':'.$userId;

        abort_if(RateLimiter::tooManyAttempts($key, $perMinute), 429, 'Too many AI requests; try again shortly.');

        RateLimiter::hit($key, 60);
    }
}
