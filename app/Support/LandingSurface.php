<?php

namespace App\Support;

use App\Models\Tenant;
use Throwable;

/**
 * Resolution of a tenant's bespoke landing surface.
 *
 * A tenant may own a hand-written landing page under
 * `resources/landings/{slug}/`, which completely replaces the shared
 * config-driven skeleton. This class is the ONLY place that decides whether
 * such a page is served — it is deliberately fail-closed:
 *
 *   - the slug must match `^[a-z0-9][a-z0-9-]*$`, so no value taken from the
 *     request can ever become a path segment;
 *   - the slug must exist in the `tenants` table (the page folder alone is not
 *     enough — a folder with no tenant is dead weight, not a surface);
 *   - a deploy-time `tenant.surface` key may point the tenant at a different
 *     surface directory (the production `dr-kafi-mentoring` alias uses
 *     `drkafimentoring`); the alias passes the same slug rule, so it can never
 *     become a path either;
 *   - `meta.json` must exist and declare this surface `enabled`;
 *   - the Blade file must exist;
 *   - ANY exception on the way (missing file, unreadable JSON, malformed
 *     meta) resolves to `null`, which means "use the legacy landing page".
 *
 * The last point is the one that matters in production: a bespoke page is
 * optional, so a broken one must degrade to the shared page instead of taking
 * a live consultant's domain down. There is no code path here that throws at
 * a caller.
 *
 * This class does not render anything and does not read the request. It is
 * given a Tenant and returns a view name or null, which keeps the decision
 * testable without an HTTP round trip.
 */
class LandingSurface
{
    /** Where bespoke surfaces live: resources/landings/{slug}/ */
    public const ROOT = 'landings';

    /** The file a surface must provide. */
    public const VIEW = 'landing.blade.php';

    /** The manifest that declares a surface published. */
    public const META = 'meta.json';

    /**
     * The bespoke view name for a tenant, or null for the legacy landing.
     *
     * `$force` exists for preview tooling: it bypasses the `enabled` flag (but
     * never the slug or file checks), so an author can see a draft page before
     * flipping it on. It is never derived from request input.
     */
    public function for(?Tenant $tenant, bool $force = false): ?string
    {
        try {
            return $this->resolve($tenant, $force);
        } catch (Throwable) {
            // Fail closed. A surface that cannot be resolved with certainty is
            // not served; the shared landing page always renders.
            return null;
        }
    }

    /** The directory that would hold this tenant's surface, valid or not. */
    public function path(?Tenant $tenant): ?string
    {
        $slug = $this->surfaceSlug($tenant);

        return $slug === null ? null : resource_path(self::ROOT.DIRECTORY_SEPARATOR.$slug);
    }

    /** The parsed manifest, or null when absent/unreadable/invalid. */
    public function meta(?Tenant $tenant): ?array
    {
        $dir = $this->path($tenant);

        if ($dir === null) {
            return null;
        }

        $file = $dir.DIRECTORY_SEPARATOR.self::META;

        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $meta = json_decode($raw, true);

        return is_array($meta) ? $meta : null;
    }

    /**
     * A slug that is safe to concatenate into a path.
     *
     * Mirrors the tenant-slug rule the rest of the platform assumes. The list
     * is checked against a pattern rather than sanitised, because a slug that
     * needed sanitising is not a slug this platform issued.
     */
    public function safeSlug(?Tenant $tenant): ?string
    {
        $slug = $tenant?->slug;

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1 ? $slug : null;
    }

    /**
     * The slug of the directory that holds this tenant's surfaces.
     *
     * Normally it is the tenant slug. A deploy-time alias (`tenant.surface`)
     * exists because a tenant may be renamed in the database while its shipped
     * surfaces keep their original folder — `dr-kafi-mentoring` in production
     * points at the tracked `drkafimentoring` folder. The alias is read from
     * the tenant's config file, never from the request, and is validated by
     * the same slug rule as the tenant slug before it is concatenated.
     */
    public function surfaceSlug(?Tenant $tenant): ?string
    {
        $slug = $this->safeSlug($tenant);

        if ($slug === null) {
            return null;
        }

        $alias = config("tenants.{$slug}.tenant.surface");

        return is_string($alias) && preg_match('/^[a-z0-9][a-z0-9-]*$/', $alias) === 1
            ? $alias
            : $slug;
    }

    /** @return string|null the view name, or null to use the legacy landing */
    protected function resolve(?Tenant $tenant, bool $force): ?string
    {
        $slug = $this->surfaceSlug($tenant);

        if ($slug === null) {
            return null;
        }

        $dir = resource_path(self::ROOT.DIRECTORY_SEPARATOR.$slug);

        // The Blade file is the surface. Without it there is nothing to serve.
        if (! is_file($dir.DIRECTORY_SEPARATOR.self::VIEW)) {
            return null;
        }

        if (! $force) {
            $meta = $this->meta($tenant);

            // No manifest means "not published", not "publish everything".
            // A surface goes live only when a human wrote that it should.
            if ($meta === null || ($meta['enabled'] ?? false) !== true) {
                return null;
            }
        }

        // The view name is relative to the `resources/landings` view location
        // registered in AppServiceProvider, so it repeats the folder name:
        // {slug}/landing resolves to resources/landings/{slug}/landing.blade.php
        // without any path being built from an unverified value.
        return $slug.'.landing';
    }
}
