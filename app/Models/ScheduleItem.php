<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single block on a student's weekly schedule, created either by a
 * consultant ("consultant_event") or by the student themselves
 * ("student_personal_block").
 *
 * Tenant isolation is enforced by BelongsToTenant exactly like Student and
 * User -- every query is automatically scoped to the current tenant, and
 * tenant_id is stamped automatically on create. Never query this model
 * with a tenant_id supplied manually from request input.
 */
class ScheduleItem extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'week_start_date',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'color',
        'item_type',
        'created_by_type',
        'created_by_user_id',
        'created_by_student_id',
        'link_url',
        'book_name',
        'test_count',
        'page_count',
        'is_completed',
        'completion_timestamp',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'is_completed' => 'boolean',
            'completion_timestamp' => 'datetime',
            'test_count' => 'integer',
            'page_count' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdByStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'created_by_student_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ItemComment::class, 'item_id')->orderBy('created_at');
    }
}
