<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Services\CameraDetectionService;
use App\Repositories\CameraDetectionLogRepository;
use App\Repositories\VehicleRepository;

echo "=== Camera Detection Comparison Tool ===\n\n";

// Get camera service
$repository = new CameraDetectionLogRepository();
$vehicleRepository = new VehicleRepository(new \App\Models\Vehicle());
$cameraService = new CameraDetectionService($repository, $vehicleRepository);

// Get all camera detection IDs from database
$dbDetectionIds = CameraDetectionLog::pluck('camera_detection_id')->toArray();
$maxDbId = max($dbDetectionIds);

echo "Database Summary:\n";
echo "  Total detections in DB: " . count($dbDetectionIds) . "\n";
echo "  Max camera_detection_id in DB: " . ($maxDbId ?: 'N/A') . "\n";
echo "  Latest detection timestamp: " . CameraDetectionLog::max('detection_timestamp') . "\n";
echo "  Latest created_at: " . CameraDetectionLog::max('created_at') . "\n\n";

// Try to fetch from camera
echo "Attempting to fetch from camera API...\n";
$fetchResult = $cameraService->fetchCameraLogs();

if ($fetchResult['success']) {
    $cameraDetections = $fetchResult['data'];
    $cameraIds = array_column($cameraDetections, 'id');
    
    echo "Camera API Response:\n";
    echo "  Total detections from camera: " . count($cameraDetections) . "\n";
    
    if (count($cameraIds) > 0) {
        echo "  Min camera ID: " . min($cameraIds) . "\n";
        echo "  Max camera ID: " . max($cameraIds) . "\n";
        
        // Find IDs in camera but not in DB
        $newIds = array_diff($cameraIds, $dbDetectionIds);
        $missingIds = array_diff($dbDetectionIds, $cameraIds);
        
        echo "\nComparison:\n";
        echo "  New detections on camera (not in DB): " . count($newIds) . "\n";
        if (count($newIds) > 0) {
            echo "    IDs: " . implode(', ', array_slice($newIds, 0, 10)) . (count($newIds) > 10 ? '...' : '') . "\n";
        }
        
        echo "  Detections in DB (not on camera): " . count($missingIds) . "\n";
        if (count($missingIds) > 0) {
            echo "    IDs: " . implode(', ', array_slice($missingIds, 0, 10)) . (count($missingIds) > 10 ? '...' : '') . "\n";
        }
        
        // Show latest 5 from camera
        echo "\nLatest 5 detections from camera:\n";
        usort($cameraDetections, function($a, $b) {
            return ($b['id'] ?? 0) - ($a['id'] ?? 0);
        });
        foreach (array_slice($cameraDetections, 0, 5) as $detection) {
            $id = $detection['id'] ?? 'N/A';
            $plate = $detection['numberplate'] ?? 'N/A';
            $timestamp = $detection['timestamp'] ?? 'N/A';
            $inDb = in_array($id, $dbDetectionIds) ? '✓ In DB' : '✗ Not in DB';
            echo "  ID: $id | Plate: $plate | Timestamp: $timestamp | $inDb\n";
        }
    }
} else {
    echo "ERROR: Failed to fetch from camera API\n";
    echo "  Message: " . $fetchResult['message'] . "\n";
    echo "\nThis could mean:\n";
    echo "  - Camera is not accessible from this server\n";
    echo "  - Camera IP address is incorrect\n";
    echo "  - Network connectivity issue\n";
    echo "  - Camera API endpoint has changed\n";
}

echo "\n=== Latest 5 from Database ===\n";
$latestDb = CameraDetectionLog::orderBy('camera_detection_id', 'desc')
    ->limit(5)
    ->get(['camera_detection_id', 'numberplate', 'detection_timestamp', 'processing_status', 'created_at']);

foreach ($latestDb as $detection) {
    echo "  ID: {$detection->camera_detection_id} | Plate: {$detection->numberplate} | ";
    echo "Detected: {$detection->detection_timestamp} | Status: {$detection->processing_status}\n";
}

