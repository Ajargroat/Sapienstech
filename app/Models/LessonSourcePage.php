<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rendered PNG of one book page. This is what «صفحهٔ ۱۲ کتاب» opens: the
 * student (or the editor's left pane) compares the extracted lesson against
 * the page the book actually printed — typos included.
 */
class LessonSourcePage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'lesson_import_id',
        'page_number',
        'image_path',
        'width',
        'height',
        'char_count',
        'flags',
        'paragraphs',
    ];

    protected function casts(): array
    {
        return [
            'flags' => 'array',
            'paragraphs' => 'array',
            'page_number' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'char_count' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LessonImport::class, 'lesson_import_id');
    }
}
