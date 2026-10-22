<?php

namespace App\Providers;

use App\Models\WebsiteConfig;
use App\Support\LandingFeatures;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
                'theme' => $c['theme'],
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
        //
        // `*.landing` is the bespoke per-tenant surface (see LandingSurface):
        // resources/landings/{slug}/landing.blade.php resolves as the view
        // "{slug}.landing", so the only stable thing to match on is the
        // trailing segment. It joins this composer because a bespoke page still
        // renders the tenant's own branding, labels and content — it replaces
        // the *layout* decision, not the data.
        // The bespoke per-tenant login shell (see App\Support\AuthSurface)
        // follows the same rule as `*.landing` below: it replaces the layout
        // decision, never the data, so it joins this composer to render the
        // tenant's own branding, labels and content.
        //
        // DB-backed public reads are fed by App\Support\LandingFeatures (the
        // landing feature contract): every class-B manifest entry injects into
        // the views it declares — the shell AND every bespoke page receive the
        // identical items, see_all default and empty-drop semantics, keyed to
        // the feature rather than to one hard-coded view name.
        View::composer(['public.landing', '*.landing', '*.login-shell', 'auth.login', 'public.blog.index', 'public.blog.show'], function ($view) {
            $c = site();

            $public = $c['public'] ?? [];

            // Class-B manifest features. The steps and their order inside
            // this loop are load-bearing: the composed shape
            // ($public.landing.{section}) is what studio paths and the
            // section loop ride on, and see_all is defaulted before the
            // empty-drop decides the section's fate.
            foreach (LandingFeatures::composers() as $feature) {
                if (! Str::is($feature['views'], $view->getName())) {
                    continue;
                }

                $section = &$public['landing'][$feature['section']];

                // The tenant's real posts, shaped like the config items so
                // every variant template renders them unchanged.
                $section['items'] = ($feature['provider'])($section);

                if (($section['see_all']['href'] ?? '#') === '#') {
                    $section['see_all']['href'] = route($feature['see_all_route']);
                }

                // Nothing published yet: the section drops off the landing
                // instead of showing an empty heading — bespoke surfaces too.
                if ($feature['drop_section_when_empty'] && $section['items'] === []) {
                    $public['landing']['sections'] = array_values(
                        array_diff($public['landing']['sections'] ?? [], [$feature['section']]),
                    );
                }

                unset($section);
            }

            $view->with([
                'tenant' => $c['tenant'],
                'theme' => $c['theme'],
                'public' => $public,
                'labels' => $c['labels'],
            ]);
        });
    }
}
