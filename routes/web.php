<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CameraController;

Route::get('/', function () {
    return view('welcome');
});

// Camera stream pages
Route::get('/camera-stream', [CameraController::class, 'index']);
Route::get('/camera-simple', function () {
    return view('simple-camera-stream');
});
Route::get('/stream-tester', function () {
    return view('stream-tester');
});
Route::get('/camera-diagnostic', function () {
    return view('camera-diagnostic');
});

// Public camera routes (for testing)
Route::get('/stream/snapshot/{cameraId?}', [CameraController::class, 'snapshot']);
Route::get('/camera/test-connection', [CameraController::class, 'testConnection']);
