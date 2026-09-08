<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tenant-owned upload storage.
 *
 * Files land in public/tenants/{slug}/{folder}/ — the same tree tenant_asset()
 * already resolves — so uploads need no storage:link dependency and are
 * physically partitioned per tenant. The DB stores the *relative* path
 * ("avatars/xyz.jpg") and views render it through tenant_asset().
 *
 * Deliberately not on the "public" filesystem disk: that convention predates
 * the tenant split (question images) and would mix tenants into one folder.
 */
class TenantUploads
{
    /**
     * Store $file under the current tenant's folder and return its relative
     * path. The previous file ($existing, if any) is deleted.
     */
    public static function store(UploadedFile $file, string $folder, ?string $existing = null): string
    {
        $slug = self::slug();
        $folder = trim($folder, '/');

        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $name = Str::random(24).'.'.$extension;

        $file->move(self::directory($slug, $folder), $name);

        self::delete($existing);

        return "{$folder}/{$name}";
    }

    /** Absolute URL for a stored relative path (or the shared tree fallback). */
    public static function url(?string $relative): ?string
    {
        return $relative ? tenant_asset($relative) : null;
    }

    /** Remove a previously stored tenant file, if it is one. */
    public static function delete(?string $relative): void
    {
        if (! $relative || ! ($slug = tenant()?->slug)) {
            return;
        }

        $path = public_path("tenants/{$slug}/".trim($relative, '/'));

        if (is_file($path)) {
            @unlink($path);
        }
    }

    protected static function slug(): string
    {
        $slug = tenant()?->slug;

        if (! $slug) {
            throw new RuntimeException('Tenant uploads require a resolved tenant.');
        }

        return $slug;
    }

    protected static function directory(string $slug, string $folder): string
    {
        $directory = public_path("tenants/{$slug}/{$folder}");

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create upload directory: {$directory}");
        }

        return $directory;
    }
}
