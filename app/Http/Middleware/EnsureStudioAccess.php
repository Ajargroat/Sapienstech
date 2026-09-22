<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Access gate for the standalone Theme Studio (/studio).
 *
 * Two checks, both carried over verbatim from the old appearance arm:
 *   1. The user belongs to the tenant() IdentifyTenant resolved — the
 *      same guarantee EnsureUserDomain gives normal dashboard requests,
 *      restated because /studio sits outside the consultant prefix.
 *   2. Hierarchy tenants restrict the studio to the tenant owner (the old
 *      EnsureHierarchyAccess appearance carve-out, which /studio previously
 *      inherited from its consultant/settings path).
 *
 * This is access control ONLY: it never reads site()/theme tokens, so gating
 * can never restyle the studio shell.
 */
class EnsureStudioAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        abort_unless($user !== null && tenant() !== null, 404);
        abort_unless($user->tenant_id === tenant()->id, 404);

        // Hierarchy tenants keep the owner-only rule the old appearance
        // routes enforced through EnsureHierarchyAccess.
        abort_if(
            tenant()?->hierarchy_type && ! $user->isTenantOwner(),
            404,
        );

        return $next($request);
    }
}
