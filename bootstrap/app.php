<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'consultant.feature' => \App\Http\Middleware\EnsureConsultantFeature::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\IdentifyTenant::class,
        ]);

        // Where the built-in auth/guest middleware sends people:
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('consultant.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
