<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered piece of a lesson. `type` picks the partial that renders it;
 * `data` is that type's payload (validated server-side by
 * App\Support\LessonBlocks). `is_dirty` records that a human touched it after
 * the AI draft — the editor's only provenance signal.
 */
class LessonBlock extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPES = ['heading', 'paragraph', 'points', 'equation', 'tip', 'figure', 'figure_duo'];

    protected $fillable = [
        'tenant_id',
        'lesson_id',
        'position',
        'type',
        'anchor',
        'data',
        'source_page',
        'figure_id',
        'confidence',
        'is_dirty',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'position' => 'integer',
            'source_page' => 'integer',
            'is_dirty' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(BookLesson::class, 'lesson_id');
    }

    public function figure(): BelongsTo
    {
        return $this->belongsTo(LessonFigure::class);
    }
}
