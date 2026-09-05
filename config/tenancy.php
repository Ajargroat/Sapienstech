<?php

/*
|--------------------------------------------------------------------------
| Platform tenancy settings
|--------------------------------------------------------------------------
|
| Deliberately separate from config/theme.php. theme.php is the *tenant-facing*
| baseline that SiteConfig merges and renders; this file holds the handful of
| platform-level switches that decide how a request is routed to a tenant at
| all, and that no tenant should be able to override.
|
| Values are resolved here rather than with env() at the call site, because
| env() returns null once `php artisan config:cache` has run.
|
*/

return [

    /*
    | Hosts allowed to resolve to the primary tenant instead of 404ing, so
    | `php artisan serve` works on localhost without a hosts-file entry per
    | tenant.
    |
    | MUST be empty in production. Any host listed here is served whichever
    | tenant owns the primary domain, so adding a public hostname would let
    | anyone claim that site with a crafted Host header.
    */
    'fallback_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANT_FALLBACK_DOMAINS', ''))
    ))),

    /*
    | How long a Host header lookup is cached. Busted automatically when a
    | Domain model is saved or deleted (see SiteServiceProvider).
    */
    'domain_cache_ttl' => 600,

];
