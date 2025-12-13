<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CameraDetectionService;
use App\Services\VehiclePassageService;
use App\Models\Gate;
use Illuminate\Support\Facades\Log;

class FetchCameraData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:camera-data {--date= : Optional date/time to fetch (YYYY-MM-DD or YYYY-MM-DD HH:mm:ss)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch camera detection data from ZKTeco camera and store in database';

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
    public function __construct(CameraDetectionService $cameraDetectionService, VehiclePassageService $vehiclePassageService)
    {
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
        $this->info('Starting camera data fetch...');
        
        try {
            // Get optional date parameter
            $date = $this->option('date');
            $dateTime = $date ? \Carbon\Carbon::parse($date) : null;
            
            // Get all active gates with active camera devices
            $gates = Gate::where('is_active', true)
                ->with(['devices' => function($query) {
                    $query->where('device_type', 'camera')
                          ->where('status', 'active');
                }])
                ->get();
            
            $totalFetched = 0;
            $totalStored = 0;
            $totalSkipped = 0;
            $totalErrors = 0;
            $totalProcessed = 0;
            
            foreach ($gates as $gate) {
                $cameras = $gate->devices->where('device_type', 'camera')->where('status', 'active');
                
                if ($cameras->isEmpty()) {
                    $this->line("⚠ Gate '{$gate->name}' (ID: {$gate->id}) has no active camera devices - skipping");
                    continue;
                }
                
                // Use the first active camera for this gate
                $cameraDevice = $cameras->first();
                
                $this->info("📷 Fetching from gate '{$gate->name}' (ID: {$gate->id}) - Camera: {$cameraDevice->ip_address}");
                
                // Set camera configuration from gate device
                $this->cameraDetectionService->setCameraFromDevice($cameraDevice);
                
                // Fetch and store camera logs for this gate
                $result = $this->cameraDetectionService->fetchAndStoreLogs($dateTime);

                if ($result['success']) {
                    $totalFetched += $result['fetched'];
                    $totalStored += $result['stored'];
                    $totalSkipped += $result['skipped'];
                    $totalErrors += $result['errors'];
                    
                    $this->info("  ✓ Fetched: {$result['fetched']}, Stored: {$result['stored']}, Skipped: {$result['skipped']}");
                    
                    if ($result['errors'] > 0) {
                        $this->warn("  ⚠ Errors: {$result['errors']}");
                    }
                } else {
                    $this->warn("  ⚠ Failed for gate '{$gate->name}': {$result['message']}");
                    $totalErrors++;
                }
            }
            
            // Process all unprocessed detections (across all gates) into vehicle passages
            if ($totalStored > 0 || $gates->isNotEmpty()) {
                $this->info('Processing detections into vehicle passages...');
                $processResult = $this->cameraDetectionService->processUnprocessedDetections();
                
                if ($processResult['success']) {
                    $totalProcessed = $processResult['processed'] ?? 0;
                    
                    if ($totalProcessed > 0) {
                        $this->info("✓ Processed {$totalProcessed} detections into passages");
                    }
                    
                    if (($processResult['errors'] ?? 0) > 0) {
                        $this->warn("⚠ Failed to process {$processResult['errors']} detections");
                    }
                    
                    if ($totalProcessed === 0 && ($processResult['errors'] ?? 0) === 0) {
                        $this->line("ℹ No unprocessed detections to process");
                    }
                } else {
                    $this->error("✗ Failed to process detections: {$processResult['message']}");
                }
            }
            
            // Summary
            $this->info("\n=== Summary ===");
            $this->info("Total fetched: {$totalFetched}");
            $this->info("Total stored: {$totalStored}");
            $this->info("Total skipped: {$totalSkipped}");
            $this->info("Total processed: {$totalProcessed}");
            if ($totalErrors > 0) {
                $this->warn("Total errors: {$totalErrors}");
            }
            
            Log::info('Camera data fetch completed', [
                'total_fetched' => $totalFetched,
                'total_stored' => $totalStored,
                'total_skipped' => $totalSkipped,
                'total_errors' => $totalErrors,
                'total_processed' => $totalProcessed,
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("✗ Error: {$e->getMessage()}");
            Log::error('Camera data fetch exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}
