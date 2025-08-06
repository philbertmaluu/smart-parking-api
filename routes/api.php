<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\StationController;
use App\Http\Controllers\API\GateController;
use App\Http\Controllers\API\VehicleController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\AuthController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::prefix('toll-v1')->middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    // Operator management routes (Admin/Manager only)
    Route::prefix('operators')->group(function () {
        Route::get('/', [AuthController::class, 'getOperators']);
        Route::post('/', [AuthController::class, 'createOperator']);
        Route::get('/{id}', [AuthController::class, 'getOperator']);
        Route::put('/{id}', [AuthController::class, 'updateOperator']);
        Route::post('/{id}/activate', [AuthController::class, 'activateOperator']);
        Route::post('/{id}/deactivate', [AuthController::class, 'deactivateOperator']);
        Route::post('/{id}/reset-password', [AuthController::class, 'resetOperatorPassword']);
        Route::delete('/{id}', [AuthController::class, 'deleteOperator']);
    });

    // Roles
    Route::get('/roles', [AuthController::class, 'getRoles']);

    // Station routes
    Route::apiResource('stations', StationController::class);
    Route::get('stations/{station}/statistics', [StationController::class, 'getStatistics']);
    Route::get('stations/active/list', [StationController::class, 'getActiveStations']);

    // Gate routes
    Route::apiResource('gates', GateController::class);
    Route::get('gates/{gate}/statistics', [GateController::class, 'getStatistics']);
    Route::get('gates/active/list', [GateController::class, 'getActiveGates']);
    Route::get('gates/station/{stationId}', [GateController::class, 'getGatesByStation']);
    Route::get('gates/type/entry', [GateController::class, 'getEntryGates']);
    Route::get('gates/type/exit', [GateController::class, 'getExitGates']);
    Route::get('gates/type/both', [GateController::class, 'getBothGates']);

    // Vehicle routes
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicles/{vehicle}/statistics', [VehicleController::class, 'getStatistics']);
    Route::get('vehicles/active/list', [VehicleController::class, 'getActiveVehicles']);
    Route::get('vehicles/body-type/{bodyTypeId}', [VehicleController::class, 'getVehiclesByBodyType']);

    // Customer routes
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{customer}/statistics', [CustomerController::class, 'getStatistics']);
    Route::get('customers/active/list', [CustomerController::class, 'getActiveCustomers']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'Smart Parking API'
    ]);
});
