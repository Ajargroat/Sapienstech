<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
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

        // Role authorization (roadmap Phase 6). These gates are enforced on
        // the upcoming admin pages with ->middleware('can:manage-users')
        // and ->middleware('can:manage-website').
        Gate::define('manage-users', fn (User $user): bool => $user->isTenantAdmin());
        Gate::define('manage-website', fn (User $user): bool => tenant()?->hierarchy_type
                    ? $user->isTenantOwner()
                    : $user->isTenantAdmin());
    }
}
