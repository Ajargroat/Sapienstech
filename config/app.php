<?php

/*
|--------------------------------------------------------------------------
| Application overrides
|--------------------------------------------------------------------------
|
| Laravel merges this file over the framework's own config/app.php, so only
| the keys below are overridden — name, env, debug, url, locale, providers,
| cipher… all keep their framework defaults.
|
| The one override that matters here is the TIMEZONE. Laravel ships 'UTC',
| but every datetime this app stores is a *wall clock* the user typed in the
| dashboard (exam windows, schedules, due dates) — i.e. Iran local time. With
| the clock pinned to UTC those stored times were compared against a UTC
| `now()` that runs 3.5 hours behind, so an exam set for 22:29 only "opened"
| around 02:00. Pinning the app to Asia/Tehran makes `now()` and the stored
| wall clocks agree, so windowOpen()/isPast() flip at the right minute.
|
| Override with APP_TIMEZONE in .env if a deployment ever moves.
|
*/

return [
    'timezone' => env('APP_TIMEZONE', 'Asia/Tehran'),
];
