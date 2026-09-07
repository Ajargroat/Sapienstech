<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\WebsiteConfig;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Existing consultant dashboard composer (unchanged). The student
        // shell renders with the exact same tenant/theme tokens, so it joins
        // the same composer instead of duplicating the wiring.
        //
        // `filters` and `sidebar` used to be passed here from config keys that
        // do not exist, so both views always received []. The dashboard's real
        // `$filters` comes from its controller, which is a different view.
        View::composer(['layouts.consultant', 'layouts.student'], function ($view) {
            $c = site();

            $view->with([
                'tenant' => $c['tenant'],
                'theme'  => $c['theme'],
                'labels' => $c['labels'],
            ]);
        });

        // Public website: the real Tenant model and the tenant's website
        // config, for branding (name, colors, logo).
        View::composer(['layouts.public'], function ($view) {
            $tenant = tenant();

            $view->with([
                'tenant' => $tenant,
                'config' => $tenant
                    ? WebsiteConfig::where('tenant_id', $tenant->id)->first()
                    : null,
            ]);
        });

        // Landing page + login: fully config-driven, so they render with the
        // exact same tenant/theme tokens as the consultant dashboard.
        //
        // `auth.student-login` is no longer a view — login.blade.php now hosts
        // both role tabs — so it has been dropped from this list.
        View::composer(['public.landing', 'auth.login', 'public.blog.index', 'public.blog.show'], function ($view) {
            $c = site();

            $public = $c['public'] ?? [];

            // Blog section: when the tenant switched the section to their
            // real posts (blog settings tab), replace the config placeholder
            // items with the latest published ones, shaped identically so
            // every variant template renders either source unchanged.
            if ($view->getName() === 'public.landing'
                && ($public['landing']['blog']['source'] ?? 'config') === 'database') {
                $public['landing']['blog']['items'] = BlogPost::published()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->take(max(1, (int) ($public['landing']['blog']['count'] ?? 3)))
                    ->get()
                    ->map->toLandingItem()
                    ->all();

                if (($public['landing']['blog']['see_all']['href'] ?? '#') === '#') {
                    $public['landing']['blog']['see_all']['href'] = route('blog.index');
                }
            }

            $view->with([
                'tenant' => $c['tenant'],
                'theme'  => $c['theme'],
                'public' => $public,
                'labels' => $c['labels'],
            ]);
        });
    }
}
