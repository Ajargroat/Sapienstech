<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task a teacher assigns to a class (a grade of the tenant). Grade null
 * targets every grade. Status tracking happens per student in the
 * assignment_students pivot, created lazily: a student of the target
 * grade without a pivot row is implicitly 'pending'.
 */
class Assignment extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING => 'در انتظار انجام',
        self::STATUS_SUBMITTED => 'تحویل‌شده',
        self::STATUS_COMPLETED => 'تکمیل‌شده',
    ];

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'title',
        'description',
        'subject',
        'grade',
        'due_at',
        'max_score',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'max_score' => 'decimal:2',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentStudent::class, 'assignment_id');
    }

    /** The pivot row for one student, if it exists. */
    public function submissionFor(int $studentId): ?AssignmentStudent
    {
        return $this->submissions->firstWhere('student_id', $studentId);
    }

    /** The student-facing status: pivot state, or pending when never touched. */
    public function statusFor(int $studentId): string
    {
        return $this->submissionFor($studentId)?->status ?? self::STATUS_PENDING;
    }

    public function scopeVisibleTo($query, Student $student)
    {
        return $query
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', $student->grade));
    }
}
