<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One company-run mock exam a student took: score breakdown, percent, rank and
 * the per-lesson percentages that feed the کارنامه card grid.
 */
class CompanyExamResult extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'student_id', 'company_id', 'title', 'exam_date', 'status',
        'total_questions', 'correct_count', 'wrong_count', 'blank_count',
        'percent', 'exam_rank', 'participants', 'lesson_percents',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'percent' => 'decimal:2',
            'lesson_percents' => 'array',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(ExamCompany::class, 'company_id');
    }
}
