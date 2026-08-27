<?php

use App\Models\Tenant;

if (! function_exists('tenant')) {
    /**
     * The tenant resolved by IdentifyTenant for the current request,
     * or null outside a tenant-scoped request (e.g. artisan commands).
     */
    function tenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}
