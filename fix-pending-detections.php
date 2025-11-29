<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Models\Vehicle;
use App\Services\CameraDetectionService;
use App\Repositories\CameraDetectionLogRepository;
use App\Repositories\VehicleRepository;
use App\Services\VehiclePassageService;

echo "=== Fixing Pending Detections with Existing Vehicles ===\n\n";

$repository = new CameraDetectionLogRepository();
$vehicleRepository = new VehicleRepository(new Vehicle());
$cameraService = new CameraDetectionService($repository, $vehicleRepository);

// Get all pending vehicle type detections
$pendingDetections = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
    ->orderBy('detection_timestamp', 'asc')
    ->get();

echo "Found " . $pendingDetections->count() . " pending detections\n\n";

$fixed = 0;
$processed = 0;

foreach ($pendingDetections as $detection) {
    $plateNumber = trim($detection->numberplate);
    
    if (empty($plateNumber)) {
        echo "Skipping detection ID {$detection->id}: Empty plate number\n";
        continue;
    }
    
    // Check if vehicle exists
    $vehicle = $vehicleRepository->lookupByPlateNumber($plateNumber);
    
    if ($vehicle) {
        echo "Detection ID {$detection->id} - Plate: {$plateNumber} - Vehicle exists (ID: {$vehicle->id})\n";
        echo "  Reprocessing detection...\n";
        
        // Reprocess the detection
        try {
            $passageService = app(VehiclePassageService::class);
            $operatorId = 1; // System operator
            
            $additionalData = [
                'make' => $detection->make_str,
                'model' => $detection->model_str,
                'color' => $detection->color_str,
                'notes' => 'Reprocessed from pending detection',
                'detection_timestamp' => $detection->detection_timestamp,
                'camera_detection_log_id' => $detection->id,
            ];
            
            $direction = $detection->direction;
            $gateId = $detection->gate_id ?? 1;
            
            if ($direction === 0 || $direction === null) {
                $result = $passageService->processVehicleEntry(
                    $plateNumber,
                    $gateId,
                    $operatorId,
                    $additionalData
                );
            } elseif ($direction === 1) {
                $result = $passageService->processVehicleExit(
                    $plateNumber,
                    $gateId,
                    $operatorId,
                    $additionalData
                );
            } else {
                // Try entry first
                $result = $passageService->processVehicleEntry(
                    $plateNumber,
                    $gateId,
                    $operatorId,
                    $additionalData
                );
            }
            
            if ($result && isset($result['success']) && $result['success']) {
                $detection->markAsProcessed('Reprocessed - vehicle already existed');
                $processed++;
                echo "  ✓ Successfully processed\n\n";
            } else {
                echo "  ✗ Failed to process: " . ($result['message'] ?? 'Unknown error') . "\n\n";
            }
        } catch (Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        }
        
        $fixed++;
    } else {
        echo "Detection ID {$detection->id} - Plate: {$plateNumber} - Vehicle does not exist (needs manual selection)\n";
    }
}

echo "\n=== Summary ===\n";
echo "Fixed: {$fixed} detections with existing vehicles\n";
echo "Processed: {$processed} detections successfully\n";
echo "Remaining pending: " . CameraDetectionLog::where('processing_status', 'pending_vehicle_type')->count() . "\n";






