<?php

namespace App\Support;

use App\Models\Tenant;
use Throwable;

/**
 * Resolution of a tenant's bespoke login shell.
 *
 * A tenant may own a hand-written login page under
 * `resources/landings/{slug}/` next to its custom landing: the file is
 * presentation only and must keep the security contract the shared card
 * carries (both guards' forms, CSRF, `throttle:login`, tab re-open). This
 * class is the ONLY place that decides whether such a shell is served — it
 * is built on the same fail-closed rules as LandingSurface:
 *
 *   - the slug must match `^[a-z0-9][a-z0-9-]*$`, so no value taken from the
 *     request can ever become a path segment;
 *   - the slug must exist in the `tenants` table;
 *   - a deploy-time `tenant.surface` key may point the tenant at a different
 *     surface directory (the production `dr-kafi-mentoring` alias uses
 *     `drkafimentoring`); the alias passes the same slug rule before it is
 *     concatenated into a path;
 *   - `meta.json` must exist and declare the surface `enabled`;
 *   - the `login-shell.blade.php` file must exist;
 *   - ANY exception on the way resolves to `null`, which means the shared
 *     `auth.login` view renders — the login screen always works, tenant or
 *     not.
 *
 * It never renders, never reads the request and never returns HTML: given a
 * Tenant, it answers a view name (`{slug}.login-shell`) or null.
 */
class AuthSurface
{
    /** Where bespoke surfaces live: resources/landings/{slug}/ */
    public const ROOT = 'landings';

    /** The file a login shell must provide. */
    public const VIEW = 'login-shell.blade.php';

    /** The manifest that declares a surface published. */
    public const META = 'meta.json';

    /**
     * The bespoke login-shell view name for a tenant, or null for the shared
     * card. No `force` mode on purpose: unlike a landing draft, a half-proof
     * login page is never worth previewing at the cost of a second code path.
     */
    public function for(?Tenant $tenant): ?string
    {
        try {
            return $this->resolve($tenant);
        } catch (Throwable) {
            // Fail closed. A shell that cannot be resolved with certainty
            // is not served; the shared login card always renders.
            return null;
        }
    }

    /** The directory that would hold this tenant's surfaces, valid or not. */
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
     * Mirrors LandingSurface::safeSlug() exactly, so the two surfaces share
     * one slug rule and one trap: a slug that needed sanitising is not a
     * slug this platform issued.
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

    /** @return string|null the view name, or null to use the shared card */
    protected function resolve(?Tenant $tenant): ?string
    {
        $slug = $this->surfaceSlug($tenant);

        if ($slug === null) {
            return null;
        }

        $dir = resource_path(self::ROOT.DIRECTORY_SEPARATOR.$slug);

        // The login-shell file IS the surface. Without it there is nothing
        // to serve and the shared card takes the request.
        if (! is_file($dir.DIRECTORY_SEPARATOR.self::VIEW)) {
            return null;
        }

        $meta = $this->meta($tenant);

        // No manifest means "not published", not "publish everything".
        // A shell goes live only when a human wrote that it should.
        if ($meta === null || ($meta['enabled'] ?? false) !== true) {
            return null;
        }

        // Resolves through the `resources/landings` view location registered
        // in AppServiceProvider, so no path is built from an unverified value.
        return $slug.'.login-shell';
    }
}
