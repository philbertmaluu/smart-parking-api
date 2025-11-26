<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CameraDetectionService;
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
     * Create a new command instance.
     *
     * @param CameraDetectionService $cameraDetectionService
     */
    public function __construct(CameraDetectionService $cameraDetectionService)
    {
        parent::__construct();
        $this->cameraDetectionService = $cameraDetectionService;
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
            
            $this->info('Fetching data from camera API...');
            
            // Fetch and store camera logs
            $result = $this->cameraDetectionService->fetchAndStoreLogs($dateTime);
            
            if ($result['success']) {
                $this->info("✓ Successfully fetched {$result['fetched']} detections");
                $this->info("✓ Stored {$result['stored']} new detections");
                
                if ($result['skipped'] > 0) {
                    $this->warn("⚠ Skipped {$result['skipped']} duplicate detections");
                }
                
                if ($result['errors'] > 0) {
                    $this->error("✗ Failed to store {$result['errors']} detections");
                }
                
                Log::info('Camera data fetch completed', $result);
                
                return Command::SUCCESS;
            } else {
                $this->error("✗ Failed to fetch camera data: {$result['message']}");
                Log::error('Camera data fetch failed', $result);
                
                return Command::FAILURE;
            }
            
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
