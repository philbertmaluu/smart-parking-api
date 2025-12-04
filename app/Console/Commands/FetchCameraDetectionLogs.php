<?php

namespace App\Console\Commands;

use App\Services\CameraDetectionService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchCameraDetectionLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'camera:fetch-logs 
                            {--date= : Specific date/time to fetch (Y-m-d H:i:s or Y-m-d)}
                            {--interval= : Fetch logs from last N minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch camera detection logs from camera API and store in database';

    protected CameraDetectionService $cameraDetectionService;

    /**
     * Create a new command instance.
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
        $this->info('Starting camera detection log fetch...');

        // Determine date/time to use
        $dateTime = $this->getDateTime();

        if ($dateTime) {
            $this->info("Fetching logs for: {$dateTime->format('Y-m-d H:i:s')}");
        } else {
            $this->info('Fetching logs for current time');
        }

        // Fetch and store logs
        $result = $this->cameraDetectionService->fetchAndStoreLogs($dateTime);

        if (!$result['success']) {
            $this->error('Failed to fetch camera logs: ' . $result['message']);
            return Command::FAILURE;
        }

        // Display results
        $this->info("✓ Fetched: {$result['fetched']} detections");
        $this->info("✓ Stored: {$result['stored']} new detections");
        
        if ($result['skipped'] > 0) {
            $this->warn("⚠ Skipped: {$result['skipped']} duplicates");
        }
        
        if ($result['errors'] > 0) {
            $this->error("✗ Errors: {$result['errors']} detections failed to store");
        }

        $this->info('Done!');

        return Command::SUCCESS;
    }

    /**
     * Get date/time from command options
     *
     * @return Carbon|null
     */
    private function getDateTime(): ?Carbon
    {
        $dateOption = $this->option('date');
        $intervalOption = $this->option('interval');

        if ($intervalOption) {
            // Fetch from N minutes ago
            return now()->subMinutes((int) $intervalOption);
        }

        if ($dateOption) {
            try {
                // Try parsing as datetime first
                $dateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateOption);
                return $dateTime;
            } catch (\Exception $e) {
                try {
                    // Try parsing as date only
                    $dateTime = Carbon::createFromFormat('Y-m-d', $dateOption);
                    return $dateTime;
                } catch (\Exception $e) {
                    $this->error("Invalid date format: {$dateOption}");
                    $this->info('Expected format: Y-m-d H:i:s or Y-m-d');
                    return null;
                }
            }
        }

        return null; // Use current time
    }
}

