<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\WebsiteConfig;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * The only sanctioned writer of a tenant's runtime config layer
 * (`website_configs.layout_config`).
 *
 * Writes are sparse diffs: setting a dotted path to null *forgets* it, so the
 * key falls back to the tenant file / archetype / baseline — which is what
 * makes "reset this token" a first-class operation instead of a copy-paste
 * of defaults. Saving bumps `updated_at`, and the DB layer's cache key
 * includes that timestamp (SiteConfig::databaseLayer), so publication is
 * self-invalidating with no listeners.
 *
 * Callers that accept user input MUST validate paths/values first — see
 * App\Support\StudioSchema in the appearance phase. This class deliberately
 * knows nothing about validation so the blog toggle and the studio share one
 * write path.
 */
class ConfigWriter
{
    /**
     * Apply dotted-path changes to the tenant's runtime layer.
     *
     * @param array<string, mixed> $changes path => value, or null to forget
     */
    public static function publishForTenant(Tenant $tenant, array $changes): WebsiteConfig
    {
        $row = WebsiteConfig::withoutGlobalScopes()
            ->firstOrNew(['tenant_id' => $tenant->id]);

        $layer = $row->layout_config ?? [];

        foreach ($changes as $path => $value) {
            if ($value === null) {
                Arr::forget($layer, $path);
            } else {
                Arr::set($layer, $path, $value);
            }
        }

        $layer = self::prune($layer);

        // The key this row answered under before the save; second-granular
        // timestamps mean it can collide with the post-save key, so both are
        // dropped below to keep back-to-back publishes self-invalidating.
        $previousKey = $row->exists
            ? SiteConfig::dbLayerCacheKey($tenant->id, optional($row->updated_at)->getTimestamp())
            : null;

        // An all-forget diff can leave an empty object behind; keep the column
        // clean so toOverrideLayer() stays a no-op for untouched tenants.
        $row->layout_config = $layer === [] ? null : $layer;
        $row->save();

        if ($previousKey !== null) {
            cache()->forget($previousKey);
        }
        cache()->forget(SiteConfig::dbLayerCacheKey($tenant->id, optional($row->updated_at)->getTimestamp()));

        // The scoped SiteConfig memoized this tenant's tree earlier in the
        // request (e.g. the layout composer); drop it so a same-request
        // redirect already shows the published values.
        app(SiteConfig::class)->flush();

        return $row;
    }

    /**
     * Persist a personal ("just for me") layer on an authenticatable that
     * carries a `preferences` JSON column (User, Student).
     *
     * Same diff semantics as the tenant layer; applied per request by
     * ApplyPersonalTheme.
     */
    public static function saveForUser(object $user, array $changes): object
    {
        if (! in_array('preferences', (array) $user->getFillable(), true)) {
            throw new RuntimeException(get_class($user).' does not support personal preferences.');
        }

        $preferences = $user->preferences ?? [];
        $layer = $preferences['site'] ?? [];

        foreach ($changes as $path => $value) {
            if ($value === null) {
                Arr::forget($layer, $path);
            } else {
                Arr::set($layer, $path, $value);
            }
        }

        $layer = self::prune($layer);

        $preferences['site'] = $layer === [] ? null : $layer;

        $user->forceFill(['preferences' => array_filter($preferences, static fn ($v) => $v !== null)]);
        $user->save();

        return $user;
    }

    /**
     * Recursively drop empty associative branches left behind by Arr::forget,
     * so resetting every key under a parent collapses the parent too and the
     * stored layer stays a true sparse diff.
     */
    protected static function prune(array $layer): array
    {
        foreach ($layer as $key => $value) {
            if (is_array($value)) {
                $value = self::prune($value);

                if ($value === []) {
                    unset($layer[$key]);
                    continue;
                }

                $layer[$key] = $value;
            }
        }

        return $layer;
    }
}
