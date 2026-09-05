<?php

namespace App\Providers;

use App\Models\Domain;
use App\Support\SiteConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not singleton: under Octane a plain singleton would hand one
        // tenant's resolved tree to the next request.
        $this->app->scoped(SiteConfig::class, static fn () => new SiteConfig(config_path()));
    }

    public function boot(): void
    {
        // IdentifyTenant caches domain lookups for 10 minutes. Without these,
        // adding a tenant appears broken for a third of an hour.
        $forget = static fn (Domain $domain) => Cache::forget(Domain::cacheKey($domain->domain));

        Domain::saved($forget);
        Domain::deleted($forget);
    }
}
