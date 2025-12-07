<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CameraDetectionService;
use App\Services\VehiclePassageService;
use App\Repositories\CameraDetectionLogRepository;
use App\Repositories\VehicleRepository;
use App\Models\CameraDetectionLog;
use Illuminate\Support\Facades\Log;

class ProcessCameraQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'camera:process-queue 
                            {--force : Force processing even if detections are already marked}
                            {--limit= : Limit the number of detections to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all stuck camera detections and clear the queue';

    /**
     * Camera Detection Service
     *
     * @var CameraDetectionService
     */
    protected $cameraDetectionService;

    /**
     * Vehicle Passage Service
     *
     * @var VehiclePassageService
     */
    protected $vehiclePassageService;

    /**
     * Create a new command instance.
     *
     * @param CameraDetectionService $cameraDetectionService
     * @param VehiclePassageService $vehiclePassageService
     */
    public function __construct(
        CameraDetectionService $cameraDetectionService,
        VehiclePassageService $vehiclePassageService
    ) {
        parent::__construct();
        $this->cameraDetectionService = $cameraDetectionService;
        $this->vehiclePassageService = $vehiclePassageService;
        
        // Set VehiclePassageService in CameraDetectionService for passage processing
        $this->cameraDetectionService->setPassageService($this->vehiclePassageService);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Processing Camera Detection Queue ===');
        $this->newLine();
        
        try {
            $force = $this->option('force');
            $limit = $this->option('limit') ? (int) $this->option('limit') : null;
            
            // Get queue statistics
            $this->info('Analyzing queue status...');
            
            $unprocessedCount = CameraDetectionLog::where('processed', false)
                ->where(function($query) {
                    $query->whereNull('processing_status')
                          ->orWhere('processing_status', 'pending');
                })
                ->count();
            
            $pendingVehicleTypeCount = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
                ->count();
            
            $pendingExitCount = CameraDetectionLog::where('processing_status', 'pending_exit')
                ->count();
            
            $this->info("Unprocessed detections: {$unprocessedCount}");
            $this->info("Pending vehicle type: {$pendingVehicleTypeCount}");
            $this->info("Pending exit: {$pendingExitCount}");
            $this->newLine();
            
            if ($unprocessedCount === 0 && !$force) {
                $this->info('✓ No unprocessed detections found. Queue is clear.');
                return Command::SUCCESS;
            }
            
            // Process unprocessed detections
            $this->info('Processing unprocessed detections...');
            $processResult = $this->cameraDetectionService->processUnprocessedDetections();
            
            if ($processResult['success']) {
                if ($processResult['processed'] > 0) {
                    $this->info("✓ Processed {$processResult['processed']} detections into passages");
                }
                
                if ($processResult['errors'] > 0) {
                    $this->warn("⚠ Failed to process {$processResult['errors']} detections");
                }
                
                if ($processResult['processed'] === 0 && $processResult['errors'] === 0) {
                    $this->line("ℹ No unprocessed detections to process");
                }
            } else {
                $this->error("✗ Failed to process detections: {$processResult['message']}");
            }
            
            $this->newLine();
            
            // Get updated statistics
            $this->info('Updated queue status:');
            $updatedUnprocessed = CameraDetectionLog::where('processed', false)
                ->where(function($query) {
                    $query->whereNull('processing_status')
                          ->orWhere('processing_status', 'pending');
                })
                ->count();
            
            $updatedPendingVehicleType = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
                ->count();
            
            $updatedPendingExit = CameraDetectionLog::where('processing_status', 'pending_exit')
                ->count();
            
            $this->info("Unprocessed detections: {$updatedUnprocessed}");
            $this->info("Pending vehicle type: {$updatedPendingVehicleType}");
            $this->info("Pending exit: {$updatedPendingExit}");
            
            if ($updatedUnprocessed === 0) {
                $this->newLine();
                $this->info('✓ Queue processing complete!');
            } else {
                $this->newLine();
                $this->warn("⚠ {$updatedUnprocessed} detections still unprocessed. Run with --force to retry.");
            }
            
            Log::info('Camera queue processing completed', [
                'processed' => $processResult['processed'] ?? 0,
                'errors' => $processResult['errors'] ?? 0,
                'remaining_unprocessed' => $updatedUnprocessed,
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("✗ Error: {$e->getMessage()}");
            Log::error('Camera queue processing exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}





