<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekday's consultant note for one student-week.
 *
 * A row is created lazily the first time the consultant saves text for that
 * day; an emptied textarea deletes it again, so the table only ever holds
 * notes that actually say something.
 *
 * Tenant isolation is BelongsToTenant, exactly like ScheduleItem: every query
 * is scoped to the current tenant and tenant_id is stamped on create. Never
 * accept tenant_id from request input.
 */
class ScheduleWeekNote extends Model
{
    use BelongsToTenant;

    /** The seven weekdays, in the grid's own Saturday-first order. */
    public const DAYS = [
        0 => 'شنبه',
        1 => 'یکشنبه',
        2 => 'دوشنبه',
        3 => 'سه‌شنبه',
        4 => 'چهارشنبه',
        5 => 'پنجشنبه',
        6 => 'جمعه',
    ];

    protected $fillable = [
        'tenant_id',
        'student_id',
        'week_start_date',
        'day_index',
        'note_text',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'day_index' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
