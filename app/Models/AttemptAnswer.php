<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'attempt_answers';
    public $timestamps = false;
    protected $fillable = [
        'tenant_id', 'attempt_id', 'student_id', 'question_id',
        'chosen_answer_id', 'is_correct', 'answered_at',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'answered_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(StudentTestAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function chosenAnswer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'chosen_answer_id');
    }
}
