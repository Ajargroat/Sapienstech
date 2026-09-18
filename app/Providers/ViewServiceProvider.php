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
        // Existing consultant dashboard composer (unchanged). The student and
        // teacher shells render with the exact same tenant/theme tokens, so
        // they join the same composer instead of duplicating the wiring.
        //
        // `filters` and `sidebar` used to be passed here from config keys that
        // do not exist, so both views always received []. The dashboard's real
        // `$filters` comes from its controller, which is a different view.
        View::composer(['layouts.consultant', 'layouts.student', 'layouts.teacher'], function ($view) {
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

            // Blog section: fed entirely by the tenant's real posts (blog
            // management, top nav). The latest published ones are injected
            // here, shaped like the old config items so every variant
            // template renders them unchanged; with nothing published yet
            // the section drops off the landing instead of showing an
            // empty heading.
            if ($view->getName() === 'public.landing') {
                $blog = &$public['landing']['blog'];

                $blog['items'] = BlogPost::published()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->take(max(1, (int) ($blog['count'] ?? 3)))
                    ->get()
                    ->map->toLandingItem()
                    ->all();

                if (($blog['see_all']['href'] ?? '#') === '#') {
                    $blog['see_all']['href'] = route('blog.index');
                }

                if ($blog['items'] === []) {
                    $public['landing']['sections'] = array_values(
                        array_diff($public['landing']['sections'] ?? [], ['blog']),
                    );
                }

                unset($blog);
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
