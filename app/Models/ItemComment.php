<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment left on a schedule_items row, by either a consultant/staff
 * user or the student themselves. Kept separate from event_comments,
 * which belongs to the unrelated schedule_events table.
 */
class ItemComment extends Model
{
    use BelongsToTenant;

    // item_comments only has created_at, no updated_at column.
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'commenter_type',
        'commenter_user_id',
        'commenter_student_id',
        'comment_text',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ScheduleItem::class, 'item_id');
    }

    public function commenterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commenter_user_id');
    }

    public function commenterStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'commenter_student_id');
    }

    public function commenterName(): string
    {
        return $this->commenter_type === 'student'
            ? (string) ($this->commenterStudent?->name ?? 'دانش‌آموز')
            : (string) ($this->commenterUser?->name ?? 'مشاور');
    }
}
