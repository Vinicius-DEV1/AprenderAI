<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('metrics:collect')->everyMinute();
Schedule::command('api:check-health')->everyThirtyMinutes();
Schedule::command('ai:reset-quotas')->daily();
Schedule::command('ai_keys:recover')->hourly();
