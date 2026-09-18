<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\TenantUploads;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A study file (PDF, image, …) a teacher publishes to their students.
 * The file itself lives under the tenant's own asset tree via
 * TenantUploads — the DB stores only the relative path.
 */
class LessonMaterial extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'title',
        'description',
        'subject',
        'grade',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'download_count',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'download_count' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function fileUrl(): ?string
    {
        return TenantUploads::url($this->file_path);
    }

    /** Human-readable size for the download cards. */
    public function formattedSize(): string
    {
        $bytes = (float) $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 0).' KB';
        }

        return persian_digits((int) $bytes).' B';
    }

    /** Visible to students: published, and matching their grade (or open to all). */
    public function scopeVisibleTo($query, Student $student)
    {
        return $query
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', $student->grade));
    }
}
