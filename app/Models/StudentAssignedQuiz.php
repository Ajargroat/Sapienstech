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
        'assigned_at', 'scheduled_at', 'status', 'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'is_completed' => 'boolean',
        ];
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
