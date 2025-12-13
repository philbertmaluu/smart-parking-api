<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Repositories\CameraDetectionLogRepository;

echo "=== Testing API Pending Detections ===\n\n";

// Check what the repository returns
$repository = new CameraDetectionLogRepository();
$pending = $repository->getPendingVehicleTypeDetections();

echo "Pending vehicle type detections from repository: " . $pending->count() . "\n";

if ($pending->count() > 0) {
    echo "\nDetections:\n";
    foreach ($pending as $det) {
        echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Gate: {$det->gate_id}, Status: {$det->processing_status}\n";
    }
} else {
    echo "\nNo pending detections found.\n";
    echo "\nChecking all unprocessed detections:\n";
    $allUnprocessed = CameraDetectionLog::where('processed', false)->get();
    echo "Total unprocessed: " . $allUnprocessed->count() . "\n";
    foreach ($allUnprocessed->take(5) as $det) {
        echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Status: {$det->processing_status}, Processed: " . ($det->processed ? 'yes' : 'no') . "\n";
    }
}

echo "\n=== Simulating what happens when a NEW detection is stored ===\n";

// Create a test detection
$testPlate = 'NEWTEST' . rand(1000, 9999);
$gateId = 2;

$newDetection = CameraDetectionLog::create([
    'camera_detection_id' => 999999 + rand(100000, 999999),
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

echo "Created test detection: ID {$newDetection->id}, Plate: {$testPlate}\n";
echo "Initial status: {$newDetection->processing_status}\n\n";

// Now check if it would be returned by the API
$pendingAfter = $repository->getPendingVehicleTypeDetections();
echo "Pending detections after creating test: " . $pendingAfter->count() . "\n";

// Process it using the service
echo "\nProcessing detection through service...\n";
$service = app(\App\Services\CameraDetectionService::class);
$result = $service->processUnprocessedDetections();

echo "Processing result:\n";
echo "  Processed: " . ($result['processed'] ?? 0) . "\n";
echo "  Errors: " . ($result['errors'] ?? 0) . "\n";

$newDetection->refresh();
echo "\nAfter processing:\n";
echo "  Status: {$newDetection->processing_status}\n";
echo "  Processed: " . ($newDetection->processed ? 'yes' : 'no') . "\n";
echo "  Notes: {$newDetection->processing_notes}\n";

// Check if it's now in pending_vehicle_type
$pendingFinal = $repository->getPendingVehicleTypeDetections();
echo "\nPending detections after processing: " . $pendingFinal->count() . "\n";

if ($pendingFinal->contains('id', $newDetection->id)) {
    echo "✓ SUCCESS: New detection is in pending_vehicle_type!\n";
} else {
    echo "✗ FAILED: New detection is NOT in pending_vehicle_type\n";
    echo "  Current status: {$newDetection->processing_status}\n";
}

// Clean up
echo "\nCleaning up...\n";
$newDetection->delete();
echo "✓ Test detection deleted\n";

