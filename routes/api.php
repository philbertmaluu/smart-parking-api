<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\StationController;
use App\Http\Controllers\API\GateController;
use App\Http\Controllers\API\VehicleController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\VehicleBodyTypeController;
use App\Http\Controllers\API\PaymentTypeController;
use App\Http\Controllers\API\BundleTypeController;
use App\Http\Controllers\API\VehiclePassageController;
use App\Http\Controllers\API\GateControlController;

// Public routes
Route::prefix('toll-v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
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
        Route::get('vehicles/search/plate/{plateNumber}', [VehicleController::class, 'searchByPlate']);
        Route::get('vehicles/lookup/{plateNumber}', [VehicleController::class, 'lookupByPlate']);
        Route::get('vehicles/active/list', [VehicleController::class, 'getActiveVehiclesList']);
        Route::get('vehicles/body-type/{bodyTypeId}', [VehicleController::class, 'getVehiclesByBodyType']);
        Route::get('vehicles/registered/list', [VehicleController::class, 'getRegisteredVehicles']);
        Route::get('vehicles/unregistered/list', [VehicleController::class, 'getUnregisteredVehicles']);
        Route::apiResource('vehicles', VehicleController::class);
        Route::get('vehicles/{vehicle}/statistics', [VehicleController::class, 'getStatistics']);

        // Customer routes
        Route::apiResource('customers', CustomerController::class);
        Route::get('customers/{customer}/statistics', [CustomerController::class, 'getStatistics']);
        Route::get('customers/active/list', [CustomerController::class, 'getActiveCustomers']);

        // Vehicle Body Type routes
        Route::apiResource('vehicle-body-types', VehicleBodyTypeController::class);
        Route::get('vehicle-body-types/active/list', [VehicleBodyTypeController::class, 'getActiveVehicleBodyTypes']);
        Route::get('vehicle-body-types/category/{category}', [VehicleBodyTypeController::class, 'getByCategory']);
        Route::get('vehicle-body-types/with-vehicle-count', [VehicleBodyTypeController::class, 'getWithVehicleCount']);
        Route::get('vehicle-body-types/with-pricing', [VehicleBodyTypeController::class, 'getWithPricing']);
        Route::get('vehicle-body-types/category/{category}/with-pricing', [VehicleBodyTypeController::class, 'getByCategoryWithPricing']);

        // Payment Type routes
        Route::apiResource('payment-types', PaymentTypeController::class);
        Route::get('payment-types/active/list', [PaymentTypeController::class, 'getActivePaymentTypes']);
        Route::get('payment-types/with-vehicle-passage-count', [PaymentTypeController::class, 'getWithVehiclePassageCount']);
        Route::get('payment-types/usage-statistics', [PaymentTypeController::class, 'getUsageStatistics']);
        Route::get('payment-types/recent-usage', [PaymentTypeController::class, 'getWithRecentUsage']);
        Route::get('payment-types/name/{name}', [PaymentTypeController::class, 'getByName']);

        // Bundle Type routes
        Route::apiResource('bundle-types', BundleTypeController::class);
        Route::post('bundle-types/{id}/toggle-status', [BundleTypeController::class, 'toggleStatus']);
        Route::get('bundle-types/active/list', [BundleTypeController::class, 'getActiveBundleTypes']);
        Route::get('bundle-types/duration/{durationDays}', [BundleTypeController::class, 'getByDuration']);
        Route::get('bundle-types/with-bundle-count', [BundleTypeController::class, 'getWithBundleCount']);
        Route::get('bundle-types/popular', [BundleTypeController::class, 'getPopular']);

        // Vehicle Passage routes
        Route::apiResource('vehicle-passages', VehiclePassageController::class);
        Route::post('vehicle-passages/entry', [VehiclePassageController::class, 'processEntry']);
        Route::post('vehicle-passages/exit', [VehiclePassageController::class, 'processExit']);
        Route::post('vehicle-passages/quick-lookup', [VehiclePassageController::class, 'quickLookup']);
        Route::get('vehicle-passages/passage/{passageNumber}', [VehiclePassageController::class, 'getByPassageNumber']);
        Route::get('vehicle-passages/vehicle/{vehicleId}', [VehiclePassageController::class, 'getByVehicle']);
        Route::get('vehicle-passages/station/{stationId}', [VehiclePassageController::class, 'getByStation']);
        Route::get('vehicle-passages/active/list', [VehiclePassageController::class, 'getActivePassages']);
        Route::get('vehicle-passages/completed/list', [VehiclePassageController::class, 'getCompletedPassages']);
        Route::get('vehicle-passages/statistics', [VehiclePassageController::class, 'getStatistics']);
        Route::put('vehicle-passages/{id}/status', [VehiclePassageController::class, 'updateStatus']);
        Route::get('vehicle-passages/search', [VehiclePassageController::class, 'search']);

        // Gate Control routes
        Route::post('gate-control/plate-detection', [GateControlController::class, 'processPlateDetection']);
        Route::post('gate-control/quick-lookup', [GateControlController::class, 'quickLookup']);
        Route::post('gate-control/manual', [GateControlController::class, 'manualControl']);
        Route::post('gate-control/emergency', [GateControlController::class, 'emergencyControl']);
        Route::get('gate-control/gates/{gateId}/status', [GateControlController::class, 'getGateStatus']);
        Route::get('gate-control/gates/active', [GateControlController::class, 'getActiveGates']);
        Route::get('gate-control/gates/{gateId}/history', [GateControlController::class, 'getGateControlHistory']);
        Route::get('gate-control/gates/{gateId}/test-connection', [GateControlController::class, 'testGateConnection']);
        Route::get('gate-control/monitoring-dashboard', [GateControlController::class, 'getMonitoringDashboard']);
    });

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'service' => 'Smart Parking API'
        ]);
    });
});
