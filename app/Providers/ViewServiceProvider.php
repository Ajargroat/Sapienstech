<?php

namespace App\Providers;

use App\Models\WebsiteConfig;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Existing consultant dashboard composer (unchanged).
        View::composer('layouts.consultant', function ($view) {
            $c = config('consultant');
            $view->with([
                'tenant'  => $c['tenant'],
                'theme'   => $c['theme'],
                'labels'  => $c['labels'],
                'filters' => $c['filters'] ?? [],
                'sidebar' => $c['sidebar'] ?? [],
            ]);
        });

        // Public website + login page: the real Tenant model and the
        // tenant's website config, for branding (name, colors, logo).
        View::composer(['layouts.public', 'auth.login'], function ($view) {
            $tenant = tenant();

            $view->with([
                'tenant' => $tenant,
                'config' => $tenant
                    ? WebsiteConfig::where('tenant_id', $tenant->id)->first()
                    : null,
            ]);
        });
    }
}
