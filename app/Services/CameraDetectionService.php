<?php

namespace App\Services;

use App\Repositories\CameraDetectionLogRepository;
use App\Repositories\VehicleRepository;
use App\Services\VehiclePassageService;
use App\Models\CameraDetectionLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

/**
 * Camera Detection Service
 * Handles fetching and storing plate number detections from camera API
 */
class CameraDetectionService
{
    private string $cameraIp;
    private int $computerId;
    private int $gateId;
    private CameraDetectionLogRepository $repository;
    private VehicleRepository $vehicleRepository;
    private ?VehiclePassageService $passageService = null;

    public function __construct(CameraDetectionLogRepository $repository, VehicleRepository $vehicleRepository)
    {
        $this->cameraIp = env('CAMERA_IP', '192.168.0.109');
        $this->computerId = (int) env('CAMERA_COMPUTER_ID', 1);
        $this->gateId = (int) env('CAMERA_GATE_ID', 1);
        $this->repository = $repository;
        $this->vehicleRepository = $vehicleRepository;
    }

    /**
     * Set the Vehicle Passage Service for processing detections
     *
     * @param VehiclePassageService $passageService
     * @return void
     */
    public function setPassageService(VehiclePassageService $passageService): void
    {
        $this->passageService = $passageService;
    }

    /**
     * Fetch camera detection logs from API
     *
     * @param Carbon|null $dateTime Optional date/time to query. If null, uses current time
     * @return array
     */
    public function fetchCameraLogs(?Carbon $dateTime = null): array
    {
        try {
            // IMPORTANT: Camera's internal clock appears to be incorrect (showing 2000 dates)
            // Query from a very old date (2000-01-01) to ensure we get ALL detections
            // The camera API returns results from the specified date onwards
            if ($dateTime === null) {
                // Use a date that's guaranteed to be before any camera detection
                // Camera seems to use 2000-01-01 as base, so query from there
                $dateTime = Carbon::parse('2000-01-01 00:00:00');
            }
            
            // Format date as required by API: YYYY-MM-DDTHH:mm:ss.SSS
            $dateParam = $dateTime->format('Y-m-d\TH:i:s.v');
            
            // Generate timestamp for cache busting
            $timestamp = time() * 1000;
            
            // Build API URL
            $url = $this->buildApiUrl($dateParam, $timestamp);
            
            Log::info('Fetching camera logs', [
                'url' => $url,
                'date_param' => $dateParam,
            ]);

            // Make HTTP request (no authentication required)
            // Reduced timeout to 5 seconds for faster response
            try {
                $response = Http::timeout(5)
                    ->get($url);

                if (!$response->successful()) {
                    Log::error('Camera API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return [
                        'success' => false,
                        'message' => 'Camera API request failed: ' . $response->status(),
                        'data' => [],
                        'count' => 0,
                    ];
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Handle connection timeout/refused gracefully
                Log::warning('Camera connection failed (timeout or unreachable)', [
                    'camera_ip' => $this->cameraIp,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Camera is unreachable or timed out. Please check camera connection.',
                    'data' => [],
                    'count' => 0,
                ];
            } catch (\Exception $e) {
                Log::error('Camera API request exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Camera API error: ' . $e->getMessage(),
                    'data' => [],
                    'count' => 0,
                ];
            }

            $data = $response->json();

            // Handle case where API returns empty array or null
            if (!is_array($data)) {
                Log::warning('Camera API returned invalid data format', [
                    'response' => $response->body(),
                ]);

                return [
                    'success' => true,
                    'message' => 'No detections found',
                    'data' => [],
                    'count' => 0,
                ];
            }

            Log::info('Camera logs fetched successfully', [
                'count' => count($data),
            ]);

            return [
                'success' => true,
                'message' => 'Camera logs fetched successfully',
                'data' => $data,
                'count' => count($data),
            ];

        } catch (Exception $e) {
            Log::error('Error fetching camera logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error fetching camera logs: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
            ];
        }
    }

    /**
     * Store camera detection logs in database
     *
     * @param array $detections
     * @return array
     */
    public function storeCameraLogs(array $detections): array
    {
        $stored = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($detections as $detection) {
            try {
                // Check if detection already exists (by camera_detection_id)
                if (isset($detection['id']) && $this->repository->detectionExists($detection['id'])) {
                    $skipped++;
                    continue;
                }

                // Map API response to database fields
                $logData = $this->mapDetectionToLogData($detection);

                // Create log entry
                $this->repository->createDetectionLog($logData);
                $stored++;

            } catch (Exception $e) {
                Log::error('Error storing camera detection log', [
                    'detection' => $detection,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        Log::info('Camera detection logs stored', [
            'stored' => $stored,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return [
            'stored' => $stored,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($detections),
        ];
    }

    /**
     * Fetch and store camera logs in one operation
     *
     * @param Carbon|null $dateTime
     * @return array
     */
    public function fetchAndStoreLogs(?Carbon $dateTime = null): array
    {
        $fetchResult = $this->fetchCameraLogs($dateTime);

        if (!$fetchResult['success']) {
            return $fetchResult;
        }

        if ($fetchResult['count'] === 0) {
            return [
                'success' => true,
                'message' => 'No new detections found',
                'fetched' => 0,
                'stored' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        $storeResult = $this->storeCameraLogs($fetchResult['data']);

        return [
            'success' => true,
            'message' => 'Camera logs fetched and stored successfully',
            'fetched' => $fetchResult['count'],
            'stored' => $storeResult['stored'],
            'skipped' => $storeResult['skipped'],
            'errors' => $storeResult['errors'],
        ];
    }

    /**
     * Build API URL with parameters
     *
     * @param string $dateParam
     * @param int $timestamp
     * @return string
     */
    private function buildApiUrl(string $dateParam, int $timestamp): string
    {
        return sprintf(
            'http://%s/edge/cgi-bin/vparcgi.cgi?computerid=%d&oper=jsonlastresults&dd=%s&_=%d',
            $this->cameraIp,
            $this->computerId,
            $dateParam,
            $timestamp
        );
    }

    /**
     * Map API detection response to database log data
     *
     * @param array $detection
     * @return array
     */
    private function mapDetectionToLogData(array $detection): array
    {
        // Prefer gate_id coming from payload (frontend push) and fall back to configured gate
        $incomingGateId = $detection['gate_id'] ?? $detection['gateId'] ?? null;
        $resolvedGateId = $incomingGateId ?: $this->gateId;

        return [
            'camera_detection_id' => $detection['id'] ?? null,
            'gate_id' => $resolvedGateId,
            'numberplate' => $detection['numberplate'] ?? '',
            'originalplate' => $detection['originalplate'] ?? null,
            'detection_timestamp' => isset($detection['timestamp']) 
                ? Carbon::parse($detection['timestamp']) 
                : now(),
            'utc_time' => isset($detection['utctime']) 
                ? Carbon::parse($detection['utctime']) 
                : null,
            'located_plate' => (bool) ($detection['locatedPlate'] ?? false),
            'global_confidence' => isset($detection['globalconfidence']) 
                ? (float) $detection['globalconfidence'] 
                : null,
            'average_char_height' => isset($detection['averagecharheight']) 
                ? (float) $detection['averagecharheight'] 
                : null,
            'process_time' => $detection['processtime'] ?? null,
            'plate_format' => $detection['plateformat'] ?? null,
            'country' => $detection['country'] ?? null,
            'country_str' => $detection['country_str'] ?? null,
            'vehicle_left' => $detection['vehicleleft'] ?? 0,
            'vehicle_top' => $detection['vehicletop'] ?? 0,
            'vehicle_right' => $detection['vehicleright'] ?? 0,
            'vehicle_bottom' => $detection['vehiclebottom'] ?? 0,
            'result_left' => $detection['resultleft'] ?? 0,
            'result_top' => $detection['resulttop'] ?? 0,
            'result_right' => $detection['resultright'] ?? 0,
            'result_bottom' => $detection['resultbottom'] ?? 0,
            'speed' => isset($detection['speed']) 
                ? (float) $detection['speed'] 
                : 0.00,
            'lane_id' => $detection['laneid'] ?? null,
            'direction' => $detection['direction'] ?? null,
            'make' => $detection['make'] ?? null,
            'model' => $detection['model'] ?? null,
            'color' => $detection['color'] ?? null,
            'make_str' => $detection['make_str'] ?? null,
            'model_str' => $detection['model_str'] ?? null,
            'color_str' => $detection['color_str'] ?? null,
            'veclass_str' => $detection['veclass_str'] ?? null,
            'image_path' => $detection['imagepath'] ?? null,
            'image_retail_path' => $detection['imageretailpath'] ?? null,
            'width' => $detection['width'] ?? null,
            'height' => $detection['height'] ?? null,
            'list_id' => $detection['listid'] ?? null,
            'name_list_id' => $detection['namelistid'] ?? null,
            'evidences' => $detection['evidences'] ?? 0,
            'br_ocurr' => $detection['br_ocurr'] ?? 0,
            'br_time' => $detection['br_time'] ?? 0,
            'raw_data' => $detection, // Store complete raw response
            'processed' => false,
            'processing_status' => 'pending',
        ];
    }

    /**
     * Get camera configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'camera_ip' => $this->cameraIp,
            'computer_id' => $this->computerId,
            'gate_id' => $this->gateId,
        ];
    }

    /**
     * Process unprocessed camera detections into vehicle passages
     *
     * @return array
     */
    public function processUnprocessedDetections(): array
    {
        if (!$this->passageService) {
            return [
                'success' => false,
                'message' => 'VehiclePassageService not available',
                'processed' => 0,
                'errors' => 0,
            ];
        }

        $processed = 0;
        $errors = 0;

        try {
            // Use database transaction with locking to prevent race conditions
            return DB::transaction(function () use (&$processed, &$errors) {
                // Get all unprocessed detections ordered by timestamp (oldest first)
                // Use lockForUpdate() to prevent concurrent processing of the same detections
                // Exclude detections that are pending vehicle type (they need manual intervention)
                $unprocessedDetections = CameraDetectionLog::where('processed', false)
                    ->where(function($query) {
                        $query->whereNull('processing_status')
                              ->orWhere('processing_status', 'pending');
                    })
                    ->orderBy('detection_timestamp', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate() // Prevent concurrent processing
                    ->get()
                    ->filter(function($detection) {
                        $status = $detection->processing_status;
                        return $status !== 'pending_vehicle_type' && ($status === null || $status === 'pending');
                    });

                if ($unprocessedDetections->isEmpty()) {
                    return [
                        'success' => true,
                        'message' => 'No unprocessed detections found',
                        'processed' => 0,
                        'errors' => 0,
                    ];
                }

                // Get system operator ID for automated processes
                $operatorId = $this->getSystemOperatorId();

                foreach ($unprocessedDetections as $detection) {
                    try {
                        // Refresh detection from database to ensure we have latest status
                        $detection->refresh();
                        
                        // Double-check status hasn't changed (race condition protection)
                        if ($detection->processed || 
                            ($detection->processing_status !== null && 
                             $detection->processing_status !== 'pending')) {
                            continue; // Already processed or status changed
                        }
                        
                        // Skip if plate number is empty
                        if (empty($detection->numberplate) || trim($detection->numberplate) === '') {
                            $detection->markAsProcessed('Skipped: Empty plate number');
                            $errors++;
                            continue;
                        }

                        // Determine direction: 0 = entry, 1 = exit
                        $direction = $detection->direction;
                        $plateNumber = trim($detection->numberplate);
                        $gateId = $detection->gate_id ?? $this->gateId;
                        
                        // Validate gate ID exists
                        if (!$gateId) {
                            $detection->markAsFailed('Invalid gate ID');
                            $errors++;
                            Log::warning('Detection has invalid gate ID', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                            ]);
                            continue;
                        }

                        // Check if vehicle exists before processing
                        $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);
                        
                        if (!$vehicle) {
                            // Vehicle doesn't exist - mark as pending vehicle type
                            // Use update() directly to avoid race conditions
                            $detection->update([
                                'processing_status' => 'pending_vehicle_type',
                                'processing_notes' => 'Vehicle not found - awaiting vehicle type selection',
                            ]);
                            Log::info('Detection marked as pending vehicle type', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                            ]);
                            continue; // Skip processing, wait for manual intervention
                        }

                        // Check if vehicle has active passage (is parked)
                        // Use a more reliable check by querying the database directly
                        $lookupResult = $this->passageService->quickPlateLookup($plateNumber);
                        $hasActivePassage = $lookupResult['success'] && 
                                           isset($lookupResult['data']['active_passage']) && 
                                           $lookupResult['data']['active_passage'];
                        
                        // Log the active passage check for debugging
                        Log::debug('Active passage check for existing vehicle', [
                            'detection_id' => $detection->id,
                            'plate_number' => $plateNumber,
                            'has_active_passage' => $hasActivePassage,
                            'lookup_result' => $lookupResult,
                        ]);

                        // Get gate to check gate type
                        $gate = \App\Models\Gate::find($gateId);
                        if (!$gate) {
                            $detection->markAsFailed('Gate not found');
                            $errors++;
                            Log::warning('Gate not found for detection', [
                                'detection_id' => $detection->id,
                                'gate_id' => $gateId,
                                'plate_number' => $plateNumber,
                            ]);
                            continue;
                        }
                        
                        $gateSupportsExit = $gate->gate_type === 'exit' || $gate->gate_type === 'both';

                        // If vehicle has active passage and gate supports exit, mark as pending_exit
                        // This requires operator confirmation before processing exit
                        if ($hasActivePassage && $gateSupportsExit) {
                            // Use update() directly to avoid race conditions
                            $detection->update([
                                'processing_status' => 'pending_exit',
                                'processing_notes' => 'Vehicle has active passage - awaiting exit confirmation',
                            ]);
                            Log::info('Detection marked as pending exit (existing vehicle with active passage)', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                                'gate_id' => $gateId,
                                'gate_type' => $gate->gate_type,
                                'direction' => $direction,
                                'vehicle_exists' => true,
                            ]);
                            continue; // Skip auto-processing, wait for operator confirmation
                        }
                        
                        // Log when existing vehicle has no active passage (will be processed as entry)
                        if (!$hasActivePassage) {
                            Log::info('Existing vehicle detected with no active passage - processing as entry', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                                'gate_id' => $gateId,
                                'direction' => $direction,
                            ]);
                        }

                        // Prepare additional data from detection
                        $additionalData = [
                            'make' => $detection->make_str,
                            'model' => $detection->model_str,
                            'color' => $detection->color_str,
                            'notes' => 'Automated camera detection',
                            'detection_timestamp' => $detection->detection_timestamp,
                            'camera_detection_log_id' => $detection->id,
                        ];

                        $result = null;

                        // Process based on direction
                        if ($direction === 0 || $direction === null) {
                            // Entry detection
                            $result = $this->passageService->processVehicleEntry(
                                $plateNumber,
                                $gateId,
                                $operatorId,
                                $additionalData
                            );
                        } elseif ($direction === 1) {
                            // Exit detection - but if we got here, vehicle doesn't have active passage
                            // So this is an invalid exit, process as entry instead
                            if (!$hasActivePassage) {
                                $result = $this->passageService->processVehicleEntry(
                                    $plateNumber,
                                    $gateId,
                                    $operatorId,
                                    $additionalData
                                );
                            } else {
                                // Should have been caught above, but just in case
                                $detection->update([
                                    'processing_status' => 'pending_exit',
                                    'processing_notes' => 'Exit detection for parked vehicle - awaiting confirmation',
                                ]);
                                continue;
                            }
                        } else {
                            // Unknown direction - determine from active passage
                            if ($hasActivePassage && $gateSupportsExit) {
                                // Should have been caught above, but mark as pending_exit
                                $detection->update([
                                    'processing_status' => 'pending_exit',
                                    'processing_notes' => 'Vehicle has active passage - awaiting exit confirmation',
                                ]);
                                continue;
                            } else {
                                // No active passage, treat as entry
                                $result = $this->passageService->processVehicleEntry(
                                    $plateNumber,
                                    $gateId,
                                    $operatorId,
                                    $additionalData
                                );
                            }
                        }

                        // Mark as processed if successful
                        if ($result && isset($result['success']) && $result['success']) {
                            // Extract passage ID safely
                            $passageId = 'N/A';
                            if (isset($result['data'])) {
                                if (is_object($result['data']) && isset($result['data']->id)) {
                                    $passageId = $result['data']->id;
                                } elseif (is_array($result['data']) && isset($result['data']['id'])) {
                                    $passageId = $result['data']['id'];
                                }
                            }
                            
                            $directionLabel = ($direction === 1) ? 'exit' : 'entry';
                            $detection->markAsProcessed(
                                "Processed as {$directionLabel}. Passage ID: {$passageId}"
                            );
                            $processed++;
                        } else {
                            // Handle specific error cases
                            $errorMessage = isset($result['message']) ? $result['message'] : 'Unknown error';
                            
                            // If vehicle already has an active passage, mark as pending_exit
                            // This can happen if the initial check missed it (race condition) or if passage was created between check and processing
                            if (stripos($errorMessage, 'Vehicle already has an active passage') !== false) {
                                // Check gate supports exit before marking as pending_exit
                                if ($gateSupportsExit) {
                                    $detection->update([
                                        'processing_status' => 'pending_exit',
                                        'processing_notes' => 'Vehicle already has an active passage - awaiting exit confirmation',
                                    ]);
                                    Log::info('Detection marked as pending exit (vehicle has active passage)', [
                                        'detection_id' => $detection->id,
                                        'plate_number' => $plateNumber,
                                        'gate_id' => $gateId,
                                        'direction' => $direction,
                                    ]);
                                    continue; // Skip marking as processed, wait for operator confirmation
                                } else {
                                    // Gate doesn't support exit, mark as processed with note
                                    $detection->markAsProcessed("Vehicle already has active passage but gate doesn't support exit");
                                    Log::warning('Vehicle has active passage but gate does not support exit', [
                                        'detection_id' => $detection->id,
                                        'plate_number' => $plateNumber,
                                        'gate_id' => $gateId,
                                        'gate_type' => $gate->gate_type,
                                    ]);
                                }
                            } 
                            // Handle gate not found - shouldn't happen but handle gracefully
                            elseif (stripos($errorMessage, 'Gate not found') !== false) {
                                $detection->markAsFailed("Gate not found: {$errorMessage}");
                                $errors++;
                                Log::error('Gate not found during processing (should have been caught earlier)', [
                                    'detection_id' => $detection->id,
                                    'plate_number' => $plateNumber,
                                    'gate_id' => $gateId,
                                    'error' => $errorMessage,
                                ]);
                            }
                            // Handle pricing not found - this is a recoverable error, don't mark as processed
                            elseif (stripos($errorMessage, 'No pricing found') !== false || 
                                    stripos($errorMessage, 'pricing') !== false) {
                                // Mark as pending_vehicle_type so operator can handle pricing configuration
                                $detection->update([
                                    'processing_status' => 'pending_vehicle_type',
                                    'processing_notes' => "Pricing issue: {$errorMessage}",
                                ]);
                                Log::warning('Detection marked as pending due to pricing issue', [
                                    'detection_id' => $detection->id,
                                    'plate_number' => $plateNumber,
                                    'error' => $errorMessage,
                                ]);
                                continue; // Don't mark as processed, allow retry
                            }
                            // Handle vehicle not found - shouldn't happen since we check before processing
                            elseif (stripos($errorMessage, 'Vehicle not found') !== false) {
                                // Mark as pending_vehicle_type since vehicle lookup failed
                                $detection->update([
                                    'processing_status' => 'pending_vehicle_type',
                                    'processing_notes' => "Vehicle lookup failed: {$errorMessage}",
                                ]);
                                Log::warning('Vehicle not found during processing (should have been caught earlier)', [
                                    'detection_id' => $detection->id,
                                    'plate_number' => $plateNumber,
                                    'error' => $errorMessage,
                                ]);
                                continue; // Don't mark as processed, allow retry
                            }
                            // For other errors, mark as processed with error note
                            else {
                                $detection->markAsProcessed("Failed to process: {$errorMessage}");
                                $errors++;
                                
                                Log::warning('Failed to process camera detection into passage', [
                                    'detection_id' => $detection->id,
                                    'plate_number' => $plateNumber,
                                    'direction' => $direction,
                                    'error' => $errorMessage,
                                    'result' => $result,
                                ]);
                            }
                        }

                    } catch (Exception $e) {
                        $errors++;
                        // Use update() directly to avoid race conditions
                        $detection->update([
                            'processed' => true,
                            'processing_status' => 'failed',
                            'processed_at' => now(),
                            'processing_notes' => "Exception: " . $e->getMessage(),
                        ]);
                        
                        Log::error('Exception processing camera detection', [
                            'detection_id' => $detection->id,
                            'plate_number' => $detection->numberplate ?? 'unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Processed camera detections',
                    'processed' => $processed,
                    'errors' => $errors,
                    'total' => $unprocessedDetections->count(),
                ];
            }, 5); // 5 second timeout for transaction

        } catch (Exception $e) {
            Log::error('Error processing unprocessed detections', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error processing detections: ' . $e->getMessage(),
                'processed' => $processed,
                'errors' => $errors,
            ];
        }
    }

    /**
     * Get system operator ID for automated camera processes
     *
     * @return int
     */
    private function getSystemOperatorId(): int
    {
        // Check for configured operator ID in environment
        $operatorId = env('CAMERA_OPERATOR_ID');
        if ($operatorId && is_numeric($operatorId)) {
            $user = User::find($operatorId);
            if ($user && $user->is_active) {
                return (int) $operatorId;
            }
        }

        // Try to get first active admin user
        $adminUser = User::active()
            ->whereHas('role', function ($query) {
                $query->where('name', 'System Admin');
            })
            ->first();

        if ($adminUser) {
            return $adminUser->id;
        }

        // Fallback: get first active user
        $activeUser = User::active()->first();
        if ($activeUser) {
            return $activeUser->id;
        }

        // Last resort: return 1 (should exist as system user)
        return 1;
    }
}

