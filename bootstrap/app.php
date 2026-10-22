<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Security floor for every response, tenant hosts included. Global
        // rather than web-only so no future route group can quietly opt out.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'consultant.feature' => \App\Http\Middleware\EnsureConsultantFeature::class,
            // Same tenant-resolved feature-flag middleware, named for the
            // student portal's route group for readability.
            'student.feature' => \App\Http\Middleware\EnsureConsultantFeature::class,
            // …and for the teacher panel. Role gating lives in EnsureTeacher.
            'teacher.feature' => \App\Http\Middleware\EnsureConsultantFeature::class,
            // The lesson library is mounted in BOTH panels, so its flag cannot
            // be spelled with one panel's prefix; this alias is the same tenant
            // flag check and resolves features.lesson_library for whichever
            // panel the request entered through.
            'lessons.feature' => \App\Http\Middleware\EnsureConsultantFeature::class,
            // The lesson READER is one screen shared by students and staff
            // preview, so its flag depends on which audience is asking.
            'lesson-reader.access' => \App\Http\Middleware\EnsureLessonReader::class,
            'teacher.role' => \App\Http\Middleware\EnsureTeacher::class,
            // Standalone Theme Studio (/studio): tenant-membership + owner rule.
            'studio.access' => \App\Http\Middleware\EnsureStudioAccess::class,
            // Our own operations console (/ops). Platform admin only: the PDF
            // pipeline is internal machinery, not a tenant feature.
            'ops.access' => \App\Http\Middleware\EnsurePlatformAdmin::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\EnsureUserDomain::class,
            \App\Http\Middleware\EnsureHierarchyAccess::class,
            \App\Http\Middleware\ApplyPersonalTheme::class,
        ]);

        // Smart redirect: keeps students on /student/*, teachers on /teacher/*
        // and consultants on /consultant/*.
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
            if (request()->is('teacher/*') || request()->routeIs('teacher.*')) {
                return route('teacher.dashboard');
            }
            return route('consultant.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
