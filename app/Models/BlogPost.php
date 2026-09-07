<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A tenant's blog post.
 *
 * The cover image is optional — "with or without pictures" is a first-class
 * display mode, not an error state; the landing variants render a gradient
 * plate when cover_image_path is null.
 */
class BlogPost extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'tenant_id', 'author_user_id', 'title', 'slug', 'excerpt', 'body',
        'cover_image_path', 'status', 'published_at', 'sort_order',
    ];

    protected $casts = ['published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $post) {
            if (! $post->slug) {
                $post->slug = self::uniqueSlug($post->title);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Shape matching the config-driven landing items, so the blog section
     * variants render DB posts through the exact same template.
     */
    public function toLandingItem(): array
    {
        return [
            'image'   => $this->cover_image_path,
            'from'    => 'primary',
            'to'      => 'secondary',
            'title'   => $this->title,
            'excerpt' => $this->excerpt ?: Str::limit(strip_tags((string) $this->body), 120),
            'url'     => route('blog.show', $this->slug),
            'visible' => true,
        ];
    }

    /**
     * Persian titles slugify to nothing (Str::slug is Latin-only), so the
     * fallback keeps the URL stable-ish and the random suffix keeps it unique.
     *
     * The uniqueness check must bypass the tenant scope: the slug column is
     * globally unique because the public route binds it without tenant
     * context in the URL.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'blog';

        $slug = $base;

        while (static::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
