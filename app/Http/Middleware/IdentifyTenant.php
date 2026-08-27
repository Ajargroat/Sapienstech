<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the request's Host header and makes
 * it available for the rest of the request lifecycle.
 *
 * Deliberately does NOT read tenant identity from any query string,
 * header, or form input the client controls -- only from the domain
 * the request actually arrived on. See roadmap Phase 4, "Very important".
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $domain = Cache::remember(
            "domain:{$host}",
            now()->addMinutes(10),
            fn () => Domain::with('tenant')->where('domain', $host)->first()
        );

        if (! $domain || ! $domain->tenant) {
            abort(404, 'No tenant is configured for this domain.');
        }

        $tenant = $domain->tenant;

        if ($tenant->isSuspended()) {
            abort(403, 'This site is currently unavailable.');
        }

        // Available for the rest of this request as app('tenant') or tenant().
        app()->instance('tenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
