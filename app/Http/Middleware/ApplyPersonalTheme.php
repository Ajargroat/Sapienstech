<?php

namespace App\Http\Middleware;

use App\Support\Merge;
use App\Support\SiteConfig;
use App\Support\ThemeTokens;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The per-user ("just for me") config layer.
 *
 * SiteConfig's four file/DB layers are the same for every visitor; anything
 * a single user personalises in the appearance studio lives in their
 * `preferences.site` JSON and is layered on top here, request-scoped, via
 * SiteConfig::personalize(). Guard-agnostic on purpose: it reads whichever
 * authenticatable (consultant User or Student) is logged in, so both portals
 * personalise through one mechanism.
 *
 * The layer is *always* set — empty when the user has none — so one user's
 * theme can never bleed into the next request.
 *
 * Runs after IdentifyTenant (the tenant must be resolved before anything
 * reads site()) and after auth (the session is started by the web group).
 */
class ApplyPersonalTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        app(SiteConfig::class)->personalize(self::effectiveLayer($request));

        return $next($request);
    }

    /**
     * The saved per-user layer only — the studio's diff baseline. Measuring
     * against this (rather than the preview-included tree) is what lets the
     * live endpoint restate the whole preview on every sync, and what makes
     * «برای همه» publish exactly what the form shows while previewing.
     */
    public static function savedLayer(Request $request): array
    {
        $layer = [];

        foreach (['web', 'student'] as $guard) {
            $user = auth($guard)->user();

            if (! $user instanceof Model) {
                continue;
            }

            $preferences = $user->getAttribute('preferences');

            if (is_array($preferences) && ! empty($preferences['site'])) {
                $layer = $preferences['site'];
                break;
            }
        }

        return $layer;
    }

    /**
     * The live studio preview's session key, namespaced per tenant.
     *
     * The web and student guards share ONE session store, and the studio now
     * runs as its own page on the tenant's own host — but a single browser can
     * still hold sessions across tenants (login screens, staff with accounts
     * on two academies). Naming the key with the resolved tenant id keeps two
     * tenants' preview layers from reading each other's state; the id comes
     * from the Host-resolved tenant, never client input.
     */
    public static function previewSessionKey(): string
    {
        return 'studio.preview.'.(tenant()?->id ?? 0);
    }

    /**
     * The full per-user layer: saved personalisation plus the live studio
     * preview riding on top of it (until the previewer exits), with the
     * tenant's brand-group lock enforced on BOTH merge inputs, so no locked
     * token path from the saved layer or the live preview can ever reach a
     * surface.
     *
     * Exposed as a seam so the studio's live-preview endpoint can re-resolve
     * the exact same tree the next page render will see.
     */
    public static function effectiveLayer(Request $request): array
    {
        $locked = self::brandLock();

        $layer = self::applyLock(self::savedLayer($request), $locked);

        // A live studio preview (session-scoped, set from the standalone
        // /studio page) rides above the saved personal layer until the
        // previewer exits it. Merge::structural, not array_replace_recursive:
        // list-shaped values (sections, glow blobs) must replace wholesale.
        $preview = $request->session()->get(self::previewSessionKey());

        if (is_array($preview) && $preview !== []) {
            $layer = Merge::structural($layer, self::applyLock($preview, $locked));
        }

        return $layer;
    }

    /**
     * The tenant's locked brand groups, read from the layers BELOW the
     * personal layer this middleware is about to install.
     *
     * Call timing is the security property: with personalize() not yet
     * called in this request, SiteConfig still resolves the file, archetype,
     * tenant-file, DB and runtime stack only — so the value it hands back is
     * the tenant's own deploy-time decision and can never be lifted by the
     * layer being policed. Only entries that name a real ThemeTokens::VARS
     * top-level group survive: a misspelled group or a non-string value is
     * inert, an empty list is inert, and the baseline null locks nothing.
     */
    protected static function brandLock(): array
    {
        $locked = app(SiteConfig::class)->get('theme.brand_lock');

        if (! is_array($locked) || $locked === []) {
            return [];
        }

        $groups = [];

        foreach (array_keys(ThemeTokens::VARS) as $path) {
            $groups[explode('.', (string) $path, 2)[0]] = true;
        }

        return array_values(array_intersect(
            array_values(array_filter($locked, 'is_string')),
            array_keys($groups),
        ));
    }

    /**
     * Drop every locked brand group's token paths from a candidate layer
     * BEFORE it is merged: a `theme.<group>` subtree disappears and
     * everything else rides untouched, so ThemeTokens resolves exactly what
     * the layers below the personal layer decided. An unlocked tenant or an
     * empty `theme` key changes nothing — that is the baseline no-op.
     */
    protected static function applyLock(array $layer, array $groups): array
    {
        if ($groups === [] || ! isset($layer['theme']) || ! is_array($layer['theme'])) {
            return $layer;
        }

        $theme = $layer['theme'];

        foreach ($groups as $group) {
            unset($theme[$group]);
        }

        if ($theme === []) {
            unset($layer['theme']);
        } else {
            $layer['theme'] = $theme;
        }

        return $layer;
    }
}
