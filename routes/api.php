<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\API\StationController;
use App\Http\Controllers\API\GateController;
use App\Http\Controllers\API\VehicleController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\VehicleBodyTypeController;
use App\Http\Controllers\API\VehicleBodyTypePriceController;
use App\Http\Controllers\API\PricingController;
use App\Http\Controllers\API\PaymentTypeController;
use App\Http\Controllers\API\BundleTypeController;
use App\Http\Controllers\API\AccountController;
use App\Http\Controllers\API\CustomerAccountController;
use App\Http\Controllers\API\BundleController;
use App\Http\Controllers\API\BundleSubscriptionController;
use App\Http\Controllers\API\VehiclePassageController;
use App\Http\Controllers\API\ReceiptController;
use App\Http\Controllers\API\CameraController;
use App\Http\Controllers\API\TollController;
use App\Http\Controllers\API\GateDeviceController;
use App\Http\Controllers\API\OperatorController;
use App\Http\Controllers\API\CameraDetectionController;
use App\Http\Controllers\API\PrinterController;

// Public routes
Route::prefix('toll-v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/gate/control', function (Request $request) {
    $command = $request->input('command', 'hell');
    $port = $request->input('port'); // optional - if not given, auto-detect
    $gateId = $request->input('gate_id');

    Log::info('Gate control requested', [
        'gate_id' => $gateId,
        'command' => $command,
        'port'    => $port,
        'ip'      => $request->ip(),
        'os'      => PHP_OS_FAMILY,
    ]);

    $success = false;
    $methods = [];
    $errors = [];

    // Auto-detect port if not provided
    if (!$port) {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: find first COM port
            exec('wmic path Win32_SerialPort get DeviceID', $wmicOutput);
            foreach ($wmicOutput as $line) {
                if (preg_match('/COM\d+/', $line, $matches)) {
                    $port = trim($matches[0]);
                    break;
                }
            }
            $port = $port ?: 'COM4'; // fallback
        } else {
            // Linux: find first ttyUSB or ttyACM
            exec('ls /dev/ttyUSB* /dev/ttyACM* 2>/dev/null', $lsOutput);
            $port = !empty($lsOutput) ? trim($lsOutput[0]) : '/dev/ttyUSB0';
        }
    }

    Log::info('Using port', ['port' => $port]);

    // Method 1: fopen/fwrite (works on both Windows & Linux with permissions)
    $method1Success = false;
    $fp = @fopen($port, 'w');
    if ($fp !== false) {
        fwrite($fp, $command . "\r\n");
        fclose($fp);
        $method1Success = true;
        $success = true;
    } else {
        $errors[] = "fopen failed: " . error_get_last()['message'] ?? 'Permission denied or port not found';
    }
    $methods['fopen'] = $method1Success;

    // Method 2: exec with cmd (Windows) or sh (Linux)
    if (PHP_OS_FAMILY === 'Windows') {
        exec("cmd /c echo $command > $port", $output1, $return1);
    } else {
        exec("echo '$command' > $port 2>&1", $output1, $return1);
    }
    $methods['exec'] = ($return1 === 0);
    if ($return1 !== 0) {
        $errors[] = "exec failed (code $return1): " . implode("\n", $output1);
    } else {
        $success = true;
    }

    // Method 3: shell_exec
    if (PHP_OS_FAMILY === 'Windows') {
        $output2 = shell_exec("cmd /c echo $command > $port");
    } else {
        $output2 = shell_exec("echo '$command' > $port 2>&1");
    }
    $methods['shell_exec'] = !empty($output2);
    if (empty($output2)) {
        $errors[] = "shell_exec returned empty or failed";
    } else {
        $success = true;
    }

    // Method 4: system
    ob_start();
    if (PHP_OS_FAMILY === 'Windows') {
        system("cmd /c echo $command > $port", $return2);
    } else {
        system("echo '$command' > $port 2>&1", $return2);
    }
    $systemOutput = ob_get_clean();
    $methods['system'] = ($return2 === 0);
    if ($return2 !== 0) {
        $errors[] = "system failed (code $return2)";
    } else {
        $success = true;
    }

    // Log full attempts
    Log::info('Gate Control Attempts', [
        'port' => $port,
        'command' => $command,
        'methods' => $methods,
        'errors' => $errors,
    ]);

    if ($success) {
        return response()->json([
            'success' => true,
            'message' => 'Gate command sent successfully',
            'port' => $port,
            'methods_tried' => $methods,
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'All methods failed to execute gate command',
            'errors' => $errors,
            'methods_tried' => $methods,
        ], 500);
    }
});

// Optional: Endpoint to list available serial ports (useful for debugging)
Route::get('/gate/available-ports', function () {
    $ports = [];

    if (PHP_OS_FAMILY === 'Windows') {
        exec('wmic path Win32_SerialPort get DeviceID,Description,Name', $output);
        foreach ($output as $line) {
            if (preg_match('/COM\d+/', $line, $matches)) {
                $ports[] = trim($matches[0]);
            }
        }
    } else {
        exec('ls /dev/ttyUSB* /dev/ttyACM* 2>/dev/null', $output);
        foreach ($output as $line) {
            $ports[] = trim($line);
        }
    }

    return response()->json([
        'success' => true,
        'ports' => $ports,
        'default' => !empty($ports) ? $ports[0] : (PHP_OS_FAMILY === 'Windows' ? 'COM4' : '/dev/ttyUSB0'),
    ]);
});
    // Public camera config endpoint (for operators to view cameras)
    Route::get('/gates/{gate}/camera-config', [GateController::class, 'getCameraConfig']);

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

        // Gate Device routes (Hardware Integration)
        Route::apiResource('gate-devices', GateDeviceController::class);
        Route::get('gate-devices/gate/{gateId}', [GateDeviceController::class, 'getByGate']);
        Route::get('gate-devices/type/{type}', [GateDeviceController::class, 'getByType']);
        Route::get('gate-devices/active/list', [GateDeviceController::class, 'getActiveList']);
        Route::post('gate-devices/{id}/test-connection', [GateDeviceController::class, 'testConnection']);

        // Operator routes (Operators Management)
        Route::get('operators', [OperatorController::class, 'index']);
        Route::post('operators', [AuthController::class, 'createOperator']);
        Route::get('operators/all', [OperatorController::class, 'getAll']);
        
        // Logged-in operator routes (MUST come before parameterized routes)
        Route::get('operators/me/available-gates', [OperatorController::class, 'getMyAvailableGates']);
        Route::post('operators/me/select-gate', [OperatorController::class, 'selectGate']);
        Route::post('operators/me/deselect-gate', [OperatorController::class, 'deselectGate']);
        Route::get('operators/me/selected-gate/devices', [OperatorController::class, 'getMySelectedGateDevices']);
        Route::get('operators/me/recent-vehicles', [OperatorController::class, 'getMyRecentVehicles']);
        
        // Parameterized operator routes (must come after 'me' routes)
        Route::get('operators/{operatorId}', [OperatorController::class, 'show']);
        Route::put('operators/{operatorId}', [AuthController::class, 'updateOperator']);
        Route::delete('operators/{operatorId}', [AuthController::class, 'deleteOperator']);
        Route::post('operators/{operatorId}/activate', [AuthController::class, 'activateOperator']);
        Route::post('operators/{operatorId}/deactivate', [AuthController::class, 'deactivateOperator']);
        Route::post('operators/{operatorId}/reset-password', [AuthController::class, 'resetOperatorPassword']);
        Route::get('operators/{operatorId}/stations', [OperatorController::class, 'getStations']);
        Route::get('operators/{operatorId}/available-gates', [OperatorController::class, 'getAvailableGates']);
        Route::post('operators/{operatorId}/assign-station', [OperatorController::class, 'assignStation']);
        Route::post('operators/{operatorId}/unassign-station', [OperatorController::class, 'unassignStation']);

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

        // Account routes
        Route::apiResource('accounts', AccountController::class);
        Route::post('accounts/{id}/toggle-status', [AccountController::class, 'toggleStatus']);
        Route::get('accounts/active/list', [AccountController::class, 'getActiveAccounts']);
        Route::get('accounts/type/{type}', [AccountController::class, 'getByType']);
        Route::get('accounts/customer/{customerId}', [AccountController::class, 'getByCustomer']);
        Route::get('accounts/{id}/statistics', [AccountController::class, 'getStatistics']);

        // Customer Account routes (Complete User + Customer + Account management)
        Route::apiResource('customer-accounts', CustomerAccountController::class);
        Route::post('customer-accounts/{accountId}/vehicles', [CustomerAccountController::class, 'addVehicle']);
        Route::delete('customer-accounts/{accountId}/vehicles/{vehicleId}', [CustomerAccountController::class, 'removeVehicle']);
        Route::get('customer-accounts/{accountId}/vehicles', [CustomerAccountController::class, 'getVehicles']);

        // Vehicle Body Type routes
        Route::apiResource('vehicle-body-types', VehicleBodyTypeController::class);
        Route::get('vehicle-body-types/active/list', [VehicleBodyTypeController::class, 'getActiveVehicleBodyTypes']);
        Route::get('vehicle-body-types/category/{category}', [VehicleBodyTypeController::class, 'getByCategory']);
        Route::get('vehicle-body-types/with-vehicle-count', [VehicleBodyTypeController::class, 'getWithVehicleCount']);
        Route::get('vehicle-body-types/with-pricing', [VehicleBodyTypeController::class, 'getWithPricing']);
        Route::get('vehicle-body-types/category/{category}/with-pricing', [VehicleBodyTypeController::class, 'getByCategoryWithPricing']);

        // Vehicle Body Type Pricing routes
        Route::apiResource('vehicle-body-type-prices', VehicleBodyTypePriceController::class);
        Route::post('vehicle-body-type-prices/current-price', [VehicleBodyTypePriceController::class, 'getCurrentPrice']);
        Route::get('vehicle-body-type-prices/station/{stationId}', [VehicleBodyTypePriceController::class, 'getCurrentPricesForStation']);
        Route::get('vehicle-body-type-prices/body-type/{bodyTypeId}', [VehicleBodyTypePriceController::class, 'getCurrentPricesForBodyType']);
        Route::post('vehicle-body-type-prices/bulk-update', [VehicleBodyTypePriceController::class, 'bulkUpdate']);
        Route::post('vehicle-body-type-prices/pricing-history', [VehicleBodyTypePriceController::class, 'getPricingHistory']);
        Route::post('vehicle-body-type-prices/effective-on-date', [VehicleBodyTypePriceController::class, 'getPricesEffectiveOnDate']);
        Route::get('vehicle-body-type-prices/search', [VehicleBodyTypePriceController::class, 'search']);
        Route::get('vehicle-body-type-prices/summary', [VehicleBodyTypePriceController::class, 'getPricingSummary']);
        Route::get('vehicle-body-type-prices/body-type/{bodyTypeId}/comparison', [VehicleBodyTypePriceController::class, 'getPriceComparison']);

        // Pricing routes
        Route::post('pricing/calculate', [PricingController::class, 'calculatePricing']);
        Route::post('pricing/calculate-by-plate', [PricingController::class, 'calculatePricingByPlate']);
        Route::get('pricing/station/{stationId}/summary', [PricingController::class, 'getStationPricingSummary']);
        Route::get('pricing/station/{stationId}/validate', [PricingController::class, 'validatePricingConfiguration']);
        Route::post('pricing/base-price', [PricingController::class, 'getBasePrice']);
        Route::post('pricing/payment-type', [PricingController::class, 'determinePaymentType']);
        Route::post('pricing/check-bundle', [PricingController::class, 'checkBundleSubscription']);
        Route::post('pricing/bulk-calculate', [PricingController::class, 'calculateBulkPricing']);

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

        // Bundle routes
        Route::apiResource('bundles', BundleController::class);
        Route::post('bundles/{id}/toggle-status', [BundleController::class, 'toggleStatus']);
        Route::get('bundles/active/list', [BundleController::class, 'getActiveBundles']);
        Route::get('bundles/type/{bundleTypeId}', [BundleController::class, 'getByType']);
        Route::get('bundles/price-range', [BundleController::class, 'getByPriceRange']);
        Route::get('bundles/with-subscription-count', [BundleController::class, 'getWithSubscriptionCount']);
        Route::get('bundles/popular', [BundleController::class, 'getPopular']);

        // Bundle Subscription routes
        Route::apiResource('bundle-subscriptions', BundleSubscriptionController::class);
        Route::put('bundle-subscriptions/{id}/status', [BundleSubscriptionController::class, 'updateStatus']);
        Route::get('bundle-subscriptions/active/list', [BundleSubscriptionController::class, 'getActiveSubscriptions']);
        Route::get('bundle-subscriptions/account/{accountId}', [BundleSubscriptionController::class, 'getByAccount']);
        Route::get('bundle-subscriptions/bundle/{bundleId}', [BundleSubscriptionController::class, 'getByBundle']);
        Route::get('bundle-subscriptions/usage-stats', [BundleSubscriptionController::class, 'getWithUsageStats']);
        Route::get('bundle-subscriptions/expiring', [BundleSubscriptionController::class, 'getExpiringSubscriptions']);

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
        Route::get('vehicle-passages/dashboard-summary', [VehiclePassageController::class, 'getDashboardSummary']);
        Route::put('vehicle-passages/{id}/status', [VehiclePassageController::class, 'updateStatus']);
        Route::get('vehicle-passages/{id}/preview-exit', [VehiclePassageController::class, 'previewExit']);
        Route::patch('vehicle-passages/{id}/set-vehicle-type', [VehiclePassageController::class, 'setVehicleType']);
        Route::get('vehicle-passages/search', [VehiclePassageController::class, 'search']);


        // Receipt routes
        Route::apiResource('receipts', ReceiptController::class);
        Route::get('receipts/number/{receiptNumber}', [ReceiptController::class, 'getByReceiptNumber']);
        Route::get('receipts/vehicle-passage/{vehiclePassageId}', [ReceiptController::class, 'getByVehiclePassage']);
        Route::get('receipts/statistics', [ReceiptController::class, 'getStatistics']);
        Route::get('receipts/recent', [ReceiptController::class, 'getRecentReceipts']);
        Route::get('receipts/total-revenue', [ReceiptController::class, 'getTotalRevenue']);
        Route::get('receipts/search', [ReceiptController::class, 'search']);
        Route::get('receipts/print/{id}', [ReceiptController::class, 'printReceipt']);
        Route::get('receipts/by-date-range', [ReceiptController::class, 'getByDateRange']);
        Route::get('receipts/by-payment-method', [ReceiptController::class, 'getByPaymentMethod']);

        // Camera routes
        Route::get('camera/stream', [CameraController::class, 'index']);
        Route::get('stream/snapshot/{cameraId?}', [CameraController::class, 'snapshot']);
        Route::get('stream/mjpeg/{cameraId?}', [CameraController::class, 'mjpegStream']);
        Route::get('stream/optimized/{cameraId?}', [CameraController::class, 'optimizedStream']);
        Route::get('stream/hls/{cameraId?}', [CameraController::class, 'hlsStream']);
        Route::get('stream/hls/{cameraId}/{segment}', [CameraController::class, 'hlsSegment']);
        Route::post('stream/hls/{cameraId?}/stop', [CameraController::class, 'stopHlsStream']);
        Route::get('camera/test-connection', [CameraController::class, 'testConnection']);
        Route::get('camera/status/{cameraId?}', [CameraController::class, 'getStatus']);

        // Camera Detection routes (Plate number detection logs)
        Route::prefix('camera-detection')->group(function () {
            Route::get('/fetch', [CameraDetectionController::class, 'fetchLogs']);
            Route::post('/store', [CameraDetectionController::class, 'storeLogs']);
            Route::post('/fetch-and-store', [CameraDetectionController::class, 'fetchAndStoreLogs']);
            Route::post('/quick-capture', [CameraDetectionController::class, 'quickCapture']);
            Route::get('/logs', [CameraDetectionController::class, 'getStoredLogs']);
            Route::get('/logs/latest', [CameraDetectionController::class, 'getLatestDetectionInfo']);
            Route::get('/logs/unprocessed', [CameraDetectionController::class, 'getUnprocessedLogs']);
            Route::get('/logs/pending-vehicle-type', [CameraDetectionController::class, 'getPendingVehicleTypeDetections']);
            Route::get('/logs/pending-exit', [CameraDetectionController::class, 'getPendingExitDetections']);
            Route::get('/logs/plate/{plateNumber}', [CameraDetectionController::class, 'getLogsByPlateNumber']);
            Route::put('/logs/{id}/mark-processed', [CameraDetectionController::class, 'markAsProcessed']);
            Route::post('/logs/{id}/process-with-vehicle-type', [CameraDetectionController::class, 'processWithVehicleType']);
            Route::post('/logs/{id}/process-exit', [CameraDetectionController::class, 'processExitDetection']);
            Route::get('/config', [CameraDetectionController::class, 'getConfig']);
            Route::get('/queue-status', [CameraDetectionController::class, 'getQueueStatus']);
        });

        // Toll Service routes (Simplified toll system)
        Route::prefix('toll')->group(function () {
            Route::post('/entry', [TollController::class, 'processEntry']);
            Route::post('/exit', [TollController::class, 'processExit']);
            Route::post('/confirm-payment', [TollController::class, 'confirmPayment']);
            Route::get('/active-passages', [TollController::class, 'getActivePassages']);
            Route::get('/passage/{passageId}', [TollController::class, 'getPassageDetails']);
            Route::post('/calculate-toll', [TollController::class, 'calculateTollAmount']);
            Route::get('/statistics', [TollController::class, 'getStatistics']);
        });

        // Printer routes (Receipt thermal printing - Zy-Q822)
        Route::prefix('printer')->group(function () {
            Route::get('/status', [PrinterController::class, 'status']);
            Route::post('/test', [PrinterController::class, 'testConnection']);
            Route::post('/print/entry/{passageId}', [PrinterController::class, 'printEntryReceipt']);
            Route::post('/print/exit/{passageId}', [PrinterController::class, 'printExitReceipt']);
            Route::post('/print/receipt/{receiptId}', [PrinterController::class, 'printReceipt']);
        });
    });

    // Public camera endpoints for testing
    Route::get('camera/status/{cameraId?}', [CameraController::class, 'getStatus']);
    Route::get('camera/test-connection', [CameraController::class, 'testConnection']);
    Route::get('camera-proxy', [CameraController::class, 'cameraProxy']);
    Route::post('camera-proxy', [CameraController::class, 'cameraProxy']);
    Route::get('stream/snapshot/{cameraId?}', [CameraController::class, 'snapshot']);
    Route::get('stream/mjpeg/{cameraId?}', [CameraController::class, 'mjpegStream']);
    Route::get('stream/optimized/{cameraId?}', [CameraController::class, 'optimizedStream']);
    Route::get('stream/hls/{cameraId?}', [CameraController::class, 'hlsStream']);
    Route::get('stream/hls/{cameraId}/{segment}', [CameraController::class, 'hlsSegment']);
    Route::post('stream/hls/{cameraId?}/stop', [CameraController::class, 'stopHlsStream']);

    // Public ZKTeco Camera routes (for camera setup and testing)
    Route::prefix('zkteco')->group(function () {
        Route::get('config', [CameraController::class, 'zktecoConfig']);
        Route::post('test-connection', [CameraController::class, 'zktecoTestConnection']);
        Route::post('snapshot', [CameraController::class, 'zktecoSnapshot']);
        Route::post('rtsp-url', [CameraController::class, 'zktecoRtspUrl']);
        Route::post('device-info', [CameraController::class, 'zktecoDeviceInfo']);
        Route::post('validate-credentials', [CameraController::class, 'zktecoValidateCredentials']);
        Route::get('mjpeg-stream', [CameraController::class, 'zktecoMjpegStream']);
        Route::post('mjpeg-stream', [CameraController::class, 'zktecoMjpegStream']);
    });

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'service' => 'Smart Parking API'
        ]);
    });

    // Debug endpoint to check system status
    Route::get('/debug/status', function () {
        try {
            $dbConnection = config('database.default');
            $dbPath = config('database.connections.sqlite.database');
            $appKey = config('app.key') ? 'Set (' . strlen(config('app.key')) . ' chars)' : 'NOT SET!';
            
            // Try to count records
            $stationCount = \App\Models\Station::count();
            $gateCount = \App\Models\Gate::count();
            $userCount = \App\Models\User::count();
            $gateDeviceCount = \App\Models\GateDevice::count();
            
            return response()->json([
                'status' => 'ok',
                'app_key' => $appKey,
                'db_connection' => $dbConnection,
                'db_path' => $dbPath,
                'db_exists' => $dbConnection === 'sqlite' ? file_exists($dbPath) : 'N/A',
                'counts' => [
                    'stations' => $stationCount,
                    'gates' => $gateCount,
                    'users' => $userCount,
                    'gate_devices' => $gateDeviceCount,
                ],
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    });
});
