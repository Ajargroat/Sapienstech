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

Artisan::command('chat:auto-close', function () {
    $closed = ChatService::autoCloseIdle();

    $this->info("بسته‌شده: {$closed} گفتگوی بی‌فعال.");
})->purpose('Close idle chat conversations per tenant configuration');
