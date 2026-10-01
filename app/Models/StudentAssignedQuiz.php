<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentAssignedQuiz extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'student_assigned_quizzes';

    protected $fillable = [
        'tenant_id', 'test_id', 'student_id', 'assigned_by_user_id',
        'assigned_at', 'scheduled_at', 'ends_at', 'status', 'is_completed', 'bulk_action_id',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_completed' => 'boolean',
        ];
    }

    /**
     * Is now inside the exam's window? Needs a scheduled start; a NULL end
     * means "open once started" (bulk/legacy rows), so it never closes.
     */
    public function windowOpen(): bool
    {
        if (! $this->scheduled_at?->isPast()) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    /**
     * Display state derived from the clock, never stored: the window opening
     * does not write to `status`, so the stored vocabulary and its filters and
     * counts keep working unchanged.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === 'scheduled' && $this->windowOpen()) {
            return 'in_progress';
        }

        return $this->status ?? 'scheduled';
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(StudentTestAttempt::class, 'assignment_id');
    }

    public function latestAttempt(): HasOne
    {
        return $this->hasOne(StudentTestAttempt::class, 'assignment_id')->ofMany(['id' => 'max']);
    }
}
