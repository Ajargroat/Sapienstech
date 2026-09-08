<?php

namespace App\Http\Middleware;

use App\Support\Merge;
use App\Support\SiteConfig;
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
     * The full per-user layer: saved personalisation plus the live studio
     * preview riding on top of it (until the previewer exits).
     *
     * Exposed as a seam so the studio's live-preview endpoint can re-resolve
     * the exact same tree the next page render will see.
     */
    public static function effectiveLayer(Request $request): array
    {
        $layer = self::savedLayer($request);

        // A live studio preview (session-scoped, set from the appearance tab)
        // rides above the saved personal layer until the previewer exits it.
        // Merge::structural, not array_replace_recursive: list-shaped values
        // (sections, glow blobs) must replace wholesale.
        $preview = $request->session()->get('studio.preview');

        if (is_array($preview) && $preview !== []) {
            $layer = Merge::structural($layer, $preview);
        }

        return $layer;
    }
}
