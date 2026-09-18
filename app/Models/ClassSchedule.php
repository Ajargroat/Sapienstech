<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring weekly class block a teacher holds. The Persian week is
 * anchored on Saturday (day_of_week 0) through Friday (6) — the same
 * convention the consultant schedule editor uses.
 */
class ClassSchedule extends Model
{
    use HasFactory, BelongsToTenant;

    public const DAY_LABELS = [
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
        'teacher_id',
        'title',
        'description',
        'subject',
        'grade',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'color',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'day_of_week' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function dayLabel(): string
    {
        return self::DAY_LABELS[$this->day_of_week] ?? (string) $this->day_of_week;
    }

    public function scopeVisibleTo($query, Student $student)
    {
        return $query
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', $student->grade));
    }
}
