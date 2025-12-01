<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the passage total amount update command
// Run daily at 2:00 AM to update any passages that may have incorrect amounts
Schedule::command('passages:update-total-amounts')
    ->dailyAt('02:00')
    ->timezone('Africa/Dar_es_Salaam')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule camera detection log fetching
// Run every 2 seconds to fetch new plate number detections from ZKTeco camera
Schedule::command('fetch:camera-data')
    ->everyTwoSeconds()
    ->timezone('Africa/Dar_es_Salaam')
    ->withoutOverlapping()
    ->runInBackground();

// Schedule gate hardware processor
// Run every second to forward cached gate actions (open/close/deny) to physical boom gate devices
Schedule::command('gate:process-hardware --once')
    ->everySecond()
    ->timezone('Africa/Dar_es_Salaam')
    ->withoutOverlapping()
    ->runInBackground();
