<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('maintenance:capture-month-end')
    ->dailyAt('23:55')
    ->timezone(config('app.timezone', 'Asia/Tokyo'))
    ->when(fn () => now()->isLastOfMonth())
    ->withoutOverlapping();
