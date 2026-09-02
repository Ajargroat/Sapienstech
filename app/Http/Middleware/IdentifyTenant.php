<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
* Resolves the current tenant from the request's Host header and makes
* both the tenant AND the domain available for the rest of the request.
* Domain identity comes only from the host the request arrived on —
* never from client-controlled input.
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

        app()->instance('tenant', $tenant);
        app()->instance('domain', $domain);

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('domain', $domain);

        return $next($request);
    }
}
