<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class StudentDeal extends Model
{
    use BelongsToTenant;

    public const DECISION_PENDING = 'pending';

    public const DECISION_CONTINUE = 'continue';

    public const DECISION_WITHDRAW = 'withdraw';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'consultant_id',
        'previous_deal_id',
        'starts_on',
        'ends_on',
        'amount',
        'currency',
        'period_days',
        'decision',
        'decision_note',
        'decided_at',
        'renewed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'amount' => 'integer',
            'period_days' => 'integer',
            'decided_at' => 'datetime',
            'renewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_deal_id');
    }

    public function next(): HasOne
    {
        return $this->hasOne(self::class, 'previous_deal_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DealPayment::class, 'deal_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(DealNotification::class, 'deal_id');
    }

    public function isRenewed(): bool
    {
        return $this->renewed_at !== null;
    }

    /** Whole days left until the period ends (negative once overdue). */
    public function daysLeft(?Carbon $today = null): int
    {
        return (int) ($today ?? Carbon::today())->diffInDays($this->ends_on, false);
    }

    public function pendingPayment(): ?DealPayment
    {
        return $this->payments->firstWhere('status', DealPayment::STATUS_PENDING)
            ?? $this->payments()->where('status', DealPayment::STATUS_PENDING)->first();
    }
}
