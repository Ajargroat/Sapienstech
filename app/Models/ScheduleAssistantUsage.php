<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant's token consumption for one month — the meter behind
 * `ai.monthly_token_budget`.
 *
 * Kept in its own table rather than summed from messages because the budget
 * check runs before every single request and must be one indexed lookup rather
 * than an aggregate over a table that grows forever. It also means the meter
 * stays correct if old messages are ever pruned.
 *
 * Counters are advanced with Eloquent's atomic increment() inside the request,
 * so two consultants asking at the same moment cannot lose one another's usage.
 */
class ScheduleAssistantUsage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'period',
        'tokens_in',
        'tokens_out',
        'request_count',
    ];

    protected function casts(): array
    {
        return [
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'request_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** The period key for a Carbon date, e.g. "2026-10". */
    public static function periodFor(?\DateTimeInterface $at = null): string
    {
        return ($at ? now()->parse($at) : now())->format('Y-m');
    }
}
