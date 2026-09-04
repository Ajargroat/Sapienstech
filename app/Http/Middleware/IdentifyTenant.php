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
        // getHost() strips the port, so `:8000` never reaches the lookup.
        $host = Domain::normalize($request->getHost());

        $domain = Cache::remember(
            Domain::cacheKey($host),
            now()->addSeconds((int) config('tenancy.domain_cache_ttl', 600)),
            fn () => Domain::with('tenant')->where('domain', $host)->first()
        );

        $domain ??= $this->fallbackDomain();

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

    /**
     * Dev-only escape hatch so `php artisan serve` on localhost works without a
     * hosts-file entry for every tenant.
     *
     * Reads config('tenancy.fallback_domains') rather than the raw environment,
     * so it survives `php artisan config:cache`. Empty by default and must stay
     * empty in production: a public hostname here would hand whichever tenant
     * owns the primary domain to anyone sending a crafted Host header.
     */
    protected function fallbackDomain(): ?Domain
    {
        $allowed = array_map([Domain::class, 'normalize'], (array) config('tenancy.fallback_domains', []));

        if ($allowed === [] || ! in_array(Domain::normalize(request()->getHost()), $allowed, true)) {
            return null;
        }

        return Domain::with('tenant')->where('is_primary', true)->oldest()->first();
    }
}
