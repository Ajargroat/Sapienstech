<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHierarchyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        if (! $user) {
            return $next($request);
        }

        if ($request->is('consultant', 'consultant/*')) {
            // Teachers have a dedicated portal, not a second consultant identity.
            abort_if($user->isTeacher(), 404);
            abort_unless($user->isTenantAdmin() || $user->isConsultantStaff(), 404);

            if (tenant()?->hierarchy_type && ! $user->isTenantOwner()) {
                abort_if($request->is('consultant/settings', 'consultant/settings/*', 'consultant/blog', 'consultant/blog/*'), 404);
            }
            if (tenant()?->hierarchy_type && $request->is('studio', 'studio/*', 'consultant/settings/appearance', 'consultant/settings/appearance/*')) {
                // The appearance studio moved to /studio; the legacy paths
                // stay covered so an old URL cannot reopen the hole.
                abort_unless($user->isTenantOwner(), 404);
            }
        }

        return $next($request);
    }
}
