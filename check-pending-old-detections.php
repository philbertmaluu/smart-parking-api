<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;

echo "=== Checking Pending Detections ===\n\n";

// Check for any pending detections (regardless of age)
$pending = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
    ->where('processed', false)
    ->orderBy('detection_timestamp', 'desc')
    ->get();

echo "Pending vehicle type detections: " . $pending->count() . "\n\n";

if ($pending->count() > 0) {
    echo "These should appear in the frontend:\n";
    foreach ($pending as $det) {
        $age = $det->detection_timestamp->diffForHumans();
        echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Gate: {$det->gate_id}, Age: {$age}\n";
    }
} else {
    echo "No pending detections found.\n";
    echo "\nAll detections have been processed.\n";
    echo "\nTo see new detections:\n";
    echo "1. Wait for a NEW vehicle to pass by the camera\n";
    echo "2. OR use the 'Capture Vehicle' button in the frontend\n";
    echo "3. OR create a test detection using: php create-test-detection.php\n";
}

// Check recent detections that might have been missed
echo "\n=== Recent Detections (Last 5) ===\n";
$recent = CameraDetectionLog::orderBy('detection_timestamp', 'desc')
    ->take(5)
    ->get();

foreach ($recent as $det) {
    $age = $det->detection_timestamp->diffForHumans();
    echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Status: {$det->processing_status}, Processed: " . ($det->processed ? 'yes' : 'no') . ", Age: {$age}\n";
}

