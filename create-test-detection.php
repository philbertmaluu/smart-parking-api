<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Services\CameraDetectionService;
use App\Services\VehiclePassageService;
use App\Repositories\CameraDetectionLogRepository;
use App\Repositories\VehicleRepository;

echo "=== Creating Test Detection ===\n\n";

// Create a test detection with a unique plate
$testPlate = 'NEWTEST' . date('His'); // Unique plate with timestamp
$gateId = 2; // Gate 2

echo "Creating test detection:\n";
echo "  Plate: {$testPlate}\n";
echo "  Gate: {$gateId}\n\n";

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
echo "  Status: {$detection->processing_status}\n\n";

// Now process it to mark as pending_vehicle_type
echo "Processing detection...\n";

$repository = new CameraDetectionLogRepository();
$vehicleRepository = app(\App\Repositories\VehicleRepository::class);
$passageService = app(\App\Services\VehiclePassageService::class);

$service = new CameraDetectionService($repository, $vehicleRepository);
$service->setPassageService($passageService);

$result = $service->processUnprocessedDetections();

echo "Processing result:\n";
echo "  Processed: " . ($result['processed'] ?? 0) . "\n";
echo "  Errors: " . ($result['errors'] ?? 0) . "\n\n";

// Check detection status
$detection->refresh();
echo "After processing:\n";
echo "  Status: {$detection->processing_status}\n";
echo "  Processed: " . ($detection->processed ? 'yes' : 'no') . "\n";
echo "  Notes: {$detection->processing_notes}\n\n";

// Check if it's in pending_vehicle_type
$pending = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
    ->where('id', $detection->id)
    ->first();

if ($pending) {
    echo "✓ SUCCESS: Detection is now in pending_vehicle_type status!\n";
    echo "  It should appear in the frontend within 2-3 seconds.\n";
    echo "  Plate: {$pending->numberplate}\n";
    echo "  Gate: {$pending->gate_id}\n";
} else {
    echo "✗ FAILED: Detection is NOT in pending_vehicle_type status\n";
    echo "  Current status: {$detection->processing_status}\n";
}

