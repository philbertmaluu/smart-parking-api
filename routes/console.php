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
// Note: Removed withoutOverlapping() to ensure it runs every 2 seconds even if previous run is still executing
Schedule::command('fetch:camera-data')
    ->everyTwoSeconds()
    ->timezone('Africa/Dar_es_Salaam')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/schedule-camera.log')); // Log output for debugging

// Schedule periodic camera preview fetch job for devices that support snapshot.
// Runs every minute; this schedules an Artisan command which dispatches the jobs.
Schedule::command('camera:dispatch-previews')
    ->everyMinute()
    ->timezone('Africa/Dar_es_Salaam')
    ->name('fetch:camera-previews')
    ->withoutOverlapping()
    ->runInBackground();

