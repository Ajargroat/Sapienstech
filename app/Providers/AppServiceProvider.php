<?php

namespace App\Providers;

use App\Models\Domain;
use App\Models\User;
use App\Support\LessonPdf\PdfExtractor;
use App\Support\LessonPdf\PythonPdfExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * The one binding the lesson pipeline needs that the container cannot
         * infer. ExtractLessonPdf asks for the PdfExtractor *interface*, and an
         * interface has no default implementation — without this line the
         * worker dies with "Target [PdfExtractor] is not instantiable" before
         * handle() runs, so the import row never leaves `queued` and the show
         * page polls "در حال استخراج" forever.
         *
         * `fromConfig()`, not `new`: the interpreter path, timeout and DPI are
         * per-tenant site settings, so the extractor must read them lazily —
         * at resolution, when the tenant is already bound — not at boot.
         * Deliberately a plain bind() (not singleton) for that same reason:
         * every resolve re-reads the tenant's config.
         */
        $this->app->bind(
            PdfExtractor::class,
            fn () => PythonPdfExtractor::fromConfig(),
        );
    }

    public function boot(): void
    {
        /*
         * Bespoke per-tenant landing surfaces live in resources/landings/
         * rather than resources/views/, so the shared skeleton and the pages
         * that replace it do not share a directory. Prepending the folder as a
         * view location lets `view('landings.{slug}.landing')` resolve through
         * the normal finder — and keeps an absent folder completely inert:
         * the finder finds nothing, LandingSurface returns null, and the
         * legacy landing renders.
         *
         * A namespace would be the obvious alternative, but `landings.{slug}
         * .landing` would then have to be written `landings::{slug}.landing`,
         * and the dot form is what keeps the view name a plain, greppable
         * string in the resolver and its tests.
         *
         * The path is normalised to forward slashes first. The finder builds
         * candidates as `$path.'/'.$file`, so on Windows an un-normalised
         * `resources\landings` becomes `resources\landings/probe-tenant2/...`
         * and `is_file()` misses a file that plainly exists — a silent "view
         * not found" that only appears on Windows.
         */
        View::getFinder()->prependLocation(str_replace('\\', '/', resource_path('landings')));

        RateLimiter::for('login', function (Request $request) {
            // The login card posts `identifier` (email, national code or
            // phone), not `email`. Keying on the field that does not exist
            // silently reduced this to a per-IP limit, so one attempt per
            // account was never actually counted.
            return Limit::perMinute(5)->by(
                strtolower((string) $request->input('identifier')).'|'.$request->ip()
            );
        });

        /**
         * Public join requests. Tighter than login per email but permissive per
         * IP, so one shared office connection does not lock out a whole cohort:
         * the email key catches repeat submissions for the same person, the IP
         * key catches a script cycling addresses.
         */
        RateLimiter::for('join-request', function (Request $request) {
            return [
                Limit::perMinute(3)->by(strtolower((string) $request->input('email'))),
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        /**
         * Public consultation requests. One tight limit per visitor: five
         * per minute, keyed by IP AND tenant, so a script cannot hammer one
         * tenant's form but a shared office connection is not locked out of
         * a different tenant's site. Printable key (`ip|tenant id`, or
         * `ip|anon` when no tenant is bound).
         */
        /**
         * Public consultation requests. One tight limit per visitor: five
         * per minute, keyed by IP AND tenant, so a script cannot hammer one
         * tenant's form but a shared office connection is not locked out of
         * a different tenant's site. Printable key (`ip|tenant id`, or
         * `ip|anon` when no tenant resolves).
         *
         * The throttle middleware runs BEFORE IdentifyTenant (route
         * middleware are ordered ahead of the appended web group), so
         * tenant() is not bound yet here — the limiter resolves the tenant
         * from the host itself, through the same cached domain lookup
         * IdentifyTenant uses, or the key would silently be per-IP only.
         */
        RateLimiter::for('consultation-request', function (Request $request) {
            $host = Domain::normalize($request->getHost());
            $domain = Cache::remember(
                Domain::cacheKey($host),
                now()->addSeconds((int) config('tenancy.domain_cache_ttl', 600)),
                fn () => Domain::with('tenant')->where('domain', $host)->first()
            );

            return Limit::perMinute(5)->by(
                $request->ip().'|'.($domain?->tenant?->id ?? 'anon')
            );
        });

        // Role authorization (roadmap Phase 6). These gates are enforced on
        // the upcoming admin pages with ->middleware('can:manage-users')
        // and ->middleware('can:manage-website').
        Gate::define('manage-users', fn (User $user): bool => $user->isTenantAdmin());
        Gate::define('manage-website', fn (User $user): bool => tenant()?->hierarchy_type
                    ? $user->isTenantOwner()
                    : $user->isTenantAdmin());
    }
}
