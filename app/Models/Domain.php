<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'verified_at',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Store every host in canonical form so lookup can never miss on case or a
     * trailing dot. Existing rows are already lowercase, so this is a no-op for
     * them.
     */
    protected static function booted(): void
    {
        static::saving(function (self $domain): void {
            $domain->domain = static::normalize($domain->domain);
        });
    }

    /**
     * Canonical form of a host for storage and lookup.
     *
     * One definition, used by IdentifyTenant, the cache keys and the
     * tenant:make command. Hosts arrive from a header, so case and a trailing
     * root dot are both possible; ports never reach here because
     * Request::getHost() already strips them.
     */
    public static function normalize(?string $host): string
    {
        return strtolower(trim((string) $host, " \t\n\r\0\x0B."));
    }

    /** The cache key holding this host's resolution. */
    public static function cacheKey(?string $host): string
    {
        return 'domain:'.self::normalize($host);
    }
}
