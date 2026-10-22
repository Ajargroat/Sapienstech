<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-way consultation request from a tenant's public website.
 *
 * Unlike a join request there is nothing to become: a visitor asks to be
 * contacted back, a tenant admin/owner approves or rejects it, and the row
 * remains the record of truth either way — approving creates nothing,
 * rejecting deletes nothing.
 */
class ConsultationRequest extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['tenant_id', 'name', 'email', 'phone', 'message', 'status'];

    /**
     * Requests still awaiting a decision.
     *
     * Named scope so a caller reads `ConsultationRequest::pending()` rather
     * than restating the status string and drifting from it.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
