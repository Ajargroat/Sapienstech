<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A figure auto-cropped out of a book page by the extractor. `confidence` is
 * `high` for an embedded image and `low` for a vector cluster (which may be a
 * table border) — the editor shows both and the reviewer decides.
 */
class LessonFigure extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'lesson_import_id',
        'page_number',
        'image_path',
        'xref',
        'bbox',
        'width',
        'height',
        'confidence',
        'linked_lesson_id',
    ];

    protected function casts(): array
    {
        return [
            'bbox' => 'array',
            'page_number' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LessonImport::class, 'lesson_import_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(BookLesson::class, 'linked_lesson_id');
    }
}
