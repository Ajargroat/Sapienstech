<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealNotification extends Model
{
    use BelongsToTenant;

    public const KIND_REMINDER = 'reminder';

    public const KIND_DECISION = 'decision';

    public const KIND_PAYMENT = 'payment';

    protected $fillable = [
        'tenant_id',
        'deal_id',
        'student_id',
        'kind',
        'message',
        'day',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'string',
            'read_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(StudentDeal::class, 'deal_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
