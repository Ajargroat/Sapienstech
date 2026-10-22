<?php

namespace App\Jobs;

use App\Models\BookLesson;
use App\Models\LessonAiDraft;
use App\Models\Tenant;
use App\Support\Lessons\LessonBlockDraft;
use App\Support\Lessons\LessonDrafter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Draft a lesson's blocks with the AI, in the queue.
 *
 * Shared by both kinds of draft — the class name is part of the S3 contract
 * the plan's tests dispatch, so a second job class for questions would be a
 * second place to forget the tenant binding. `$draft->kind` decides what runs.
 *
 * Every rule from PREFERENCE §7.3 is honoured here and nowhere else:
 *  - the payload is `tenant_id` + `draft_id` + `lesson_id`, INTEGERS only
 *    (a model snapshot on a queue payload goes stale);
 *  - the tenant is re-resolved by primary key and bound to the container
 *    BEFORE anything reads `tenant()`;
 *  - a failed or refused draft stores `payload = NULL` plus an error code;
 *  - the provider is never called from an HTTP request.
 */
class GenerateLessonBlocks implements ShouldQueue
{
    use Queueable;

    public int $timeout = 28;

    public int $tries = 1;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $draftId,
        public readonly int $lessonId,
        public readonly ?int $questionCount = null,
    ) {}

    public static function forDraft(LessonAiDraft $draft, ?int $questionCount = null): self
    {
        return new self(
            (int) $draft->tenant_id,
            (int) $draft->id,
            (int) $draft->lesson_id,
            $questionCount,
        );
    }

    public function handle(LessonDrafter $drafter): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        app()->instance('tenant', $tenant);

        $draft = LessonAiDraft::query()->findOrFail($this->draftId);

        if ($draft->status === 'refused') {
            return; // a no-text-layer lesson is refused at dispatch; never call out.
        }

        $draft->update(['status' => 'running']);

        try {
            $lesson = BookLesson::query()->findOrFail($this->lessonId);

            $outcome = $draft->kind === LessonAiDraft::KIND_QUESTIONS
                ? $drafter->draftQuestions($lesson, $this->questionCount)
                : $drafter->draftBlocks($lesson);

            $this->persist($draft, $lesson, $outcome);
        } catch (Throwable $e) {
            $draft->update([
                'status' => 'failed',
                'payload' => null,
                'error_code' => 'job_crashed',
                'error_text' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    /** @param array<string, mixed> $outcome */
    private function persist(LessonAiDraft $draft, BookLesson $lesson, array $outcome): void
    {
        $result = $outcome['result'] ?? null;

        $attributes = [
            'raw_text' => $outcome['raw'] ?? null,
            'model' => $result?->model,
            'input_tokens' => (int) ($result->inputTokens ?? 0),
            'output_tokens' => (int) ($result->outputTokens ?? 0),
            'latency_ms' => $result?->latencyMs,
        ];

        if (($outcome['ok'] ?? false) && ($outcome['payload'] ?? null) !== null) {
            $draft->update($attributes + [
                'status' => 'ready',
                'payload' => $outcome['payload'],
                'error_code' => null,
                'error_text' => null,
            ]);

            if ($draft->kind === LessonAiDraft::KIND_BLOCKS) {
                LessonBlockDraft::replaceInto($lesson, $outcome['payload']);
            }

            return;
        }

        // Refused (answered, but nothing survived validation) vs failed (the
        // call itself did not produce a usable reply). Both store NULL.
        $draft->update($attributes + [
            'status' => ($outcome['refused'] ?? false) ? 'refused' : 'failed',
            'payload' => null,
            'error_code' => $outcome['error']?->value ?? (($outcome['validator'][0] ?? null) ?: 'bad_response'),
            'error_text' => mb_substr((string) ($outcome['error_text'] ?? ''), 0, 500),
        ]);
    }
}
