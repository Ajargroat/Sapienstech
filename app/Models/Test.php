<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Test extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'test_title', 'lesson', 'exam_type',
        'time_limit_minutes', 'total_marks', 'created_by_user_id',
        'cover_image_path',
    ];

    protected function casts(): array
    {
        return ['time_limit_minutes' => 'integer', 'total_marks' => 'decimal:2'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'test_questions')
            ->withPivot(['id', 'position', 'points'])
            ->orderByPivot('position');
    }

    /**
     * Pivot rows only, in no particular order.
     *
     * `withCount('questions')` resolves through questions(), whose
     * orderByPivot('position') lands inside the count's subquery — MySQL
     * forbids ORDER BY there, the aggregate is dropped, and cards fell back to
     * the stale question_count column (the "always ۲ questions" bug). Counting
     * this plain relation has no ORDER BY to lose.
     */
    public function questionRows(): HasMany
    {
        return $this->hasMany(TestQuestion::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentAssignedQuiz::class, 'test_id');
    }
}
