<?php

use App\Support\ChatService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Direct-chat idle auto-close: per-tenant `chat.idle_autoclose_days` decides
| both whether this does anything (0 = no-op) and the cutoff, resolved through
| the same tenant site() layer as the web UI. Run by the standard scheduler
| (`php artisan schedule:work` in dev); never enabled in a tenant's config →
| the command short-circuits per tenant.
*/
ScheduleFacade::command('chat:auto-close')
    ->dailyAt('03:30')
    ->withoutOverlapping();

/*
| Deal renewal reminders: every tenant's non-withdrawn, unrenewed deals that
| reach their decision window get a daily student-portal notification until
| the student decides or the period is renewed (dedupe handled by the
| unique (deal, kind, day) index). Config-gated like everything else: when a
| tenant disables the `deals` feature the command still runs but their
| deals stay visible to staff — switch the whole feature off per tenant
| with `features.deals` in the appearance studio.
*/
ScheduleFacade::command('deals:remind-due')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Artisan::command('chat:auto-close', function () {
    $closed = ChatService::autoCloseIdle();

    $this->info("بسته‌شده: {$closed} گفتگوی بی‌فعال.");
})->purpose('Close idle chat conversations per tenant configuration');
