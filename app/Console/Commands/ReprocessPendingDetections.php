<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CameraDetectionLog;
use App\Models\Vehicle;
use App\Repositories\VehicleRepository;
use App\Services\VehiclePassageService;
use App\Services\CameraDetectionService;
use App\Repositories\CameraDetectionLogRepository;
use Illuminate\Support\Facades\Log;

class ReprocessPendingDetections extends Command
{
    protected $signature = 'detections:reprocess-pending';
    protected $description = 'Reprocess pending vehicle type detections for vehicles that already exist';

    public function handle()
    {
        $this->info('Reprocessing pending detections with existing vehicles...');

        $repository = new CameraDetectionLogRepository();
        $vehicleRepository = new VehicleRepository(new Vehicle());
        $passageService = app(VehiclePassageService::class);
        $operatorId = 1; // System operator

        $pendingDetections = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
            ->orderBy('detection_timestamp', 'asc')
            ->get();

        $this->info("Found {$pendingDetections->count()} pending detections");

        $processed = 0;
        $skipped = 0;

        foreach ($pendingDetections as $detection) {
            $plateNumber = trim($detection->numberplate);
            
            if (empty($plateNumber)) {
                $this->warn("Skipping detection ID {$detection->id}: Empty plate number");
                $skipped++;
                continue;
            }
            
            $vehicle = $vehicleRepository->lookupByPlateNumber($plateNumber);
            
            if ($vehicle) {
                $this->info("Processing detection ID {$detection->id} - Plate: {$plateNumber} (Vehicle ID: {$vehicle->id})");
                
                try {
                    $additionalData = [
                        'make' => $detection->make_str,
                        'model' => $detection->model_str,
                        'color' => $detection->color_str,
                        'notes' => 'Reprocessed from pending detection',
                        'detection_timestamp' => $detection->detection_timestamp,
                        'camera_detection_log_id' => $detection->id,
                        'payment_method' => 'cash', // Default payment method
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
                        $this->info("  ✓ Successfully processed");
                    } else {
                        $this->error("  ✗ Failed: " . ($result['message'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Error: " . $e->getMessage());
                    Log::error('Error reprocessing detection', [
                        'detection_id' => $detection->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->info("\n=== Summary ===");
        $this->info("Processed: {$processed}");
        $this->info("Skipped: {$skipped}");
        $this->info("Remaining pending: " . CameraDetectionLog::where('processing_status', 'pending_vehicle_type')->count());

        return 0;
    }
}







