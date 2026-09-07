<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recorded bulk operation from the «اقدامات گروهی» workspace.
 *
 * Children (StudentAssignedQuiz / ScheduleItem) point back here via
 * bulk_action_id, which is what makes batch revert a scoped delete. The
 * summary freezes the inputs (test, block template, filter set) so the
 * history page can explain itself without joining anything else.
 */
class BulkAction extends Model
{
    use BelongsToTenant;

    public const KIND_EXAM = 'exam';
    public const KIND_SCHEDULE = 'schedule';

    protected $fillable = [
        'tenant_id', 'user_id', 'kind', 'summary', 'affected_count', 'reverted_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'reverted_at' => 'datetime',
        'affected_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentAssignedQuiz::class, 'bulk_action_id');
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(ScheduleItem::class, 'bulk_action_id');
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
