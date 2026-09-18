<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /teacher area: the authenticated user must be a tenant
 * teacher. Anyone else — guest (already handled by auth), consultant,
 * tenant admin, or a teacher of another tenant (BelongsToTenant scope) —
 * gets a 404 like every other gated section of the app.
 */
class EnsureTeacher
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isTeacher(), 404);

        return $next($request);
    }
}
