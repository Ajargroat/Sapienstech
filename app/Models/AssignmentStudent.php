<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasFileAttachment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's lifecycle row for an assignment: pending → submitted →
 * completed (the teacher's acknowledgement). The tenant_id is stamped by
 * BelongsToTenant on create, keeping the "never trust client ids" rule.
 */
class AssignmentStudent extends Model
{
    use BelongsToTenant, HasFactory, HasFileAttachment;

    protected $fillable = [
        'tenant_id',
        'assignment_id',
        'student_id',
        'status',
        'submitted_at',
        'note',
        'file_path',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        // History relation: a submission keeps its author's name after that
        // student leaves the roster, so the teacher's review list does not
        // degrade to anonymous rows.
        return $this->belongsTo(Student::class)->withoutGlobalScope('roster');
    }
}
