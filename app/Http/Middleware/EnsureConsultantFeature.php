<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a consultant-dashboard feature for the current tenant.
 *
 * Reads through site() rather than config() so the switch is tenant-resolved:
 * disabling `direct_chat` for one tenant no longer requires a deploy of the
 * shared baseline.
 */
class EnsureConsultantFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless((bool) site("features.{$feature}", false), 404);

        return $next($request);
    }
}
