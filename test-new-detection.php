<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Services\CameraDetectionService;
use App\Services\VehiclePassageService;
use App\Repositories\CameraDetectionLogRepository;
use App\Repositories\VehicleRepository;

echo "=== Testing New Detection Flow ===\n\n";

// Create a test detection
$testPlate = 'TEST' . time(); // Unique plate number
$gateId = 2; // Gate 2

echo "Creating test detection with plate: {$testPlate}\n";

$detection = CameraDetectionLog::create([
    'camera_detection_id' => 999999 + time(), // Unique ID
    'gate_id' => $gateId,
    'numberplate' => $testPlate,
    'detection_timestamp' => now(),
    'direction' => 0, // Entry
    'processed' => false,
    'processing_status' => 'pending',
    'make_str' => 'Test',
    'model_str' => 'Vehicle',
    'color_str' => 'Red',
]);

echo "✓ Detection created: ID {$detection->id}\n";
echo "  Status: {$detection->processing_status}\n";
echo "  Processed: " . ($detection->processed ? 'yes' : 'no') . "\n\n";

// Now process it
echo "Processing detection...\n";

$repository = new CameraDetectionLogRepository();
$vehicleRepository = new VehicleRepository();
$passageService = new VehiclePassageService($vehicleRepository);

$service = new CameraDetectionService($repository, $vehicleRepository);
$service->setPassageService($passageService);

$result = $service->processUnprocessedDetections();

echo "Processing result:\n";
print_r($result);

// Check detection status
$detection->refresh();
echo "\nAfter processing:\n";
echo "  Status: {$detection->processing_status}\n";
echo "  Processed: " . ($detection->processed ? 'yes' : 'no') . "\n";
echo "  Notes: {$detection->processing_notes}\n\n";

// Check if it's in pending_vehicle_type
$pending = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
    ->where('id', $detection->id)
    ->first();

if ($pending) {
    echo "✓ SUCCESS: Detection is now in pending_vehicle_type status!\n";
} else {
    echo "✗ FAILED: Detection is NOT in pending_vehicle_type status\n";
    echo "  Current status: {$detection->processing_status}\n";
}

// Clean up
echo "\nCleaning up test detection...\n";
$detection->delete();
echo "✓ Test detection deleted\n";

