<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far one student got inside one lesson. Reading progress is tracked
 * silently (no «ذخیره درس» button in v1): the reader POSTs the highest heading
 * index it has scrolled past.
 */
class LessonReadingProgress extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'lesson_id',
        'student_id',
        'max_section_index',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'max_section_index' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(BookLesson::class, 'lesson_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
