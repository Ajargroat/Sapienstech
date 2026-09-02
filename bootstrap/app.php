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
            \App\Http\Middleware\EnsureUserDomain::class,
        ]);

        // Smart redirect: keeps students on /student/* and consultants on /consultant/*
        $middleware->redirectGuestsTo(function () {
            if (request()->is('student/*') || request()->routeIs('student.*')) {
                return route('student.login');
            }
            return route('login');
        });

        $middleware->redirectUsersTo(function () {
            if (request()->is('student/*') || request()->routeIs('student.*')) {
                return route('student.dashboard');
            }
            return route('consultant.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
