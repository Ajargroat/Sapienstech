<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A published (or draft) lesson: a page range of a book, rendered from
 * ordered blocks. The book/chapter are strings on purpose — the "book" here
 * is real data for the reader's breadcrumb and the question bank's
 * `chapter_label`, not a foreign key into the long-dead books/chapters tables.
 */
class BookLesson extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'lesson_import_id',
        'book_title',
        'chapter_title',
        'lesson_title',
        'subject',
        'grade',
        'source_page_from',
        'source_page_to',
        'status',
        'published_at',
        'created_by_user_id',
        'ai_drafted_at',
    ];

    protected function casts(): array
    {
        return [
            'source_page_from' => 'integer',
            'source_page_to' => 'integer',
            'published_at' => 'datetime',
            'ai_drafted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LessonImport::class, 'lesson_import_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(LessonBlock::class, 'lesson_id')->orderBy('position')->orderBy('id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(LessonAiDraft::class, 'lesson_id')->latest('id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonReadingProgress::class, 'lesson_id');
    }

    /**
     * Visible to students: published, and matching their grade (or open to
     * all). Copied verbatim from LessonMaterial::scopeVisibleTo so the lesson
     * journal filters exactly like the material library.
     */
    public function scopeVisibleTo($query, Student $student)
    {
        return $query
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', $student->grade));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
