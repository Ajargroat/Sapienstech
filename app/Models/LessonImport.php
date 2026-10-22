<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One PDF textbook handed to the extractor. Its `status` is the whole
 * progress contract the staff panel polls, and its children (source page
 * renders + figure crops) are what make the feature useful even when the
 * PDF has no text layer.
 *
 * Tenant-owned: unlinke Question/Answer, this is NEVER shared across tenants,
 * so BelongsToTenant's creating-stamp is exactly right here.
 */
class LessonImport extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'book_title',
        'grade',
        'pdf_path',
        'page_count',
        'status',
        'structure',
        'stats',
        'error_code',
        'error_text',
        'created_by_user_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'page_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sourcePages(): HasMany
    {
        return $this->hasMany(LessonSourcePage::class)->orderBy('page_number');
    }

    public function figures(): HasMany
    {
        return $this->hasMany(LessonFigure::class)->orderBy('page_number')->orderBy('id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(BookLesson::class)->orderBy('source_page_from')->orderBy('id');
    }

    public function isScanned(): bool
    {
        return $this->structure === 'scanned';
    }

    /** Any state in which the worker still owns the row. */
    public function isRunning(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }
}
