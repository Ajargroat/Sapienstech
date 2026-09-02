<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentTestAttempt extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'student_test_attempts';

    protected $fillable = [
        'tenant_id', 'assignment_id', 'student_id', 'test_id', 'status',
        'started_at', 'score_raw', 'score_simple_percent', 'score_negative_percent',
        'time_taken_seconds', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'score_raw' => 'decimal:2',
            'score_simple_percent' => 'decimal:2',
            'score_negative_percent' => 'decimal:2',
            'time_taken_seconds' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(StudentAssignedQuiz::class, 'assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }
}
