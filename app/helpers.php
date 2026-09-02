<?php

use App\Models\Domain;
use App\Models\Tenant;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}

if (! function_exists('domain')) {
    /**
     * The domain the current request arrived on, resolved by IdentifyTenant.
     */
    function domain(): ?Domain
    {
        return app()->bound('domain') ? app('domain') : null;
    }
}
