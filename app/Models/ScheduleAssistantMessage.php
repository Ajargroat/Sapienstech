<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn in a planner-assistant conversation, either the consultant's
 * question or the assistant's answer.
 *
 * `suggestions` holds the assistant's structured proposals (add a block, edit a
 * block, a weekly note) after server-side validation. It is JSON on the message
 * rather than a table because a suggestion has no independent identity: it is
 * never queried across messages, never updated, and never applied except by the
 * consultant clicking a card that replays it through the *existing* schedule
 * endpoints. Storing it beside the sentence it explains is the honest shape.
 *
 * Token counts and `error_code` are stored per message so the usage meter can
 * be reconciled against real turns and a failed turn is visible in history
 * rather than a silent gap.
 */
class ScheduleAssistantMessage extends Model
{
    use BelongsToTenant;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'tenant_id',
        'thread_id',
        'role',
        'content',
        'suggestions',
        'model',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'suggestions' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssistantThread::class, 'thread_id');
    }
}
