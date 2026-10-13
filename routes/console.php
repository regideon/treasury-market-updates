<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// End-of-day pull, once a day on banking days. Times are in the app's
// configured timezone - set APP_TIMEZONE=Asia/Manila in .env so this
// actually runs after the local market close.
Schedule::command('market:pull-eod')
    ->weekdays()
    ->at('18:30')
    ->withoutOverlapping();
