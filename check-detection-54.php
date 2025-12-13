<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;

echo "=== Checking Detection ID 54 (T103ABE) ===\n\n";

// Check if detection ID 54 exists
$det = CameraDetectionLog::where('camera_detection_id', 54)->first();

if ($det) {
    echo "✓ FOUND in database:\n";
    echo "  - DB ID: {$det->id}\n";
    echo "  - Camera Detection ID: {$det->camera_detection_id}\n";
    echo "  - Plate: {$det->numberplate}\n";
    echo "  - Status: {$det->processing_status}\n";
    echo "  - Processed: " . ($det->processed ? 'yes' : 'no') . "\n";
    echo "  - Timestamp: {$det->detection_timestamp}\n";
} else {
    echo "✗ NOT FOUND in database!\n";
    echo "  This detection should be stored but isn't.\n";
    echo "  The duplicate check might be failing.\n\n";
    
    // Check all recent detections
    echo "Recent detections in database:\n";
    $recent = CameraDetectionLog::orderBy('detection_timestamp', 'desc')
        ->take(5)
        ->get();
    
    foreach ($recent as $d) {
        echo "  - Camera ID: {$d->camera_detection_id}, Plate: {$d->numberplate}, Time: {$d->detection_timestamp}\n";
    }
}

