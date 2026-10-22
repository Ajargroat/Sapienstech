<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI draft: either the lesson's blocks (kind=blocks) or its multiple-choice
 * questions (kind=questions). The row is the pipeline's durable state — the
 * provider is never called inline in a request, and `payload` is written only
 * after server-side validation, so `payload = NULL` plus an `error_code` is
 * the honest record of a failure or a refusal.
 */
class LessonAiDraft extends Model
{
    use BelongsToTenant, HasFactory;

    public const KIND_BLOCKS = 'blocks';

    public const KIND_QUESTIONS = 'questions';

    protected $fillable = [
        'tenant_id',
        'lesson_id',
        'kind',
        'status',
        'raw_text',
        'payload',
        'error_code',
        'error_text',
        'model',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'created_by_user_id',
        'released_question_id',
        'released_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
            'released_count' => 'integer',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(BookLesson::class, 'lesson_id');
    }

    /** The questions the validator admitted (question drafts only). */
    public function questions(): array
    {
        return (array) ($this->payload['questions'] ?? []);
    }
}
