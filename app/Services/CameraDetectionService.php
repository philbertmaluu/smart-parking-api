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
        // Default to env variables if set, otherwise will be set per-gate
        $this->cameraIp = env('CAMERA_IP', '192.168.0.107');
        $this->computerId = (int) env('CAMERA_COMPUTER_ID', 1);
        $this->gateId = (int) env('CAMERA_GATE_ID', 1);
        $this->repository = $repository;
        $this->vehicleRepository = $vehicleRepository;
    }

    /**
     * Set camera configuration from gate device
     *
     * @param \App\Models\GateDevice $cameraDevice
     * @return void
     */
    public function setCameraFromDevice(\App\Models\GateDevice $cameraDevice): void
    {
        $this->cameraIp = $cameraDevice->ip_address;
        $this->gateId = $cameraDevice->gate_id;
        $this->computerId = (int) ($cameraDevice->device_id ?? env('CAMERA_COMPUTER_ID', 1));
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
            if ($dateTime === null) {
                $dateTime = Carbon::now()->subHours(24);
            }

            $dateParam = $dateTime->format('Y-m-d\TH:i:s.v');
            $timestamp = time() * 1000;

            $url = $this->buildApiUrl($dateParam, $timestamp);

            Log::info('Fetching camera logs', [
                'url' => $url,
                'date_param' => $dateParam,
            ]);

            try {
                $response = Http::timeout(15)->get($url);

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

            $responseData = $response->json();

            $data = [];
            if (is_array($responseData)) {
                if (isset($responseData['data']) && is_array($responseData['data'])) {
                    $data = $responseData['data'];
                } elseif (isset($responseData[0]) && is_array($responseData[0])) {
                    $data = $responseData;
                }
            }

            if (empty($data) || !is_array($data)) {
                Log::warning('Camera API returned invalid data format', [
                    'response' => $response->body(),
                    'parsed' => $responseData,
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
                'sample_ids' => array_slice(array_column($data, 'id'), 0, 5),
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
     * UPDATED: Strong deduplication to prevent repeated plates from lingering vehicles
     *
     * @param array $detections
     * @return array
     */
    public function storeCameraLogs(array $detections): array
    {
        $stored = 0;
        $skipped = 0;
        $errors = 0;

        // Configurable deduplication window (in seconds) - default 60 seconds
        $dedupeWindow = (int) env('CAMERA_DEDUPE_WINDOW', 60);

        foreach ($detections as $detection) {
            try {
                $plateNumber = trim($detection['numberplate'] ?? '');
                $detectionTimestamp = isset($detection['timestamp'])
                    ? Carbon::parse($detection['timestamp'])
                    : now();

                // Skip empty plates
                if (empty($plateNumber)) {
                    $skipped++;
                    continue;
                }

                // NEW: Strong deduplication - same plate on same gate in last X seconds
                $recentDuplicate = CameraDetectionLog::where('numberplate', $plateNumber)
                    ->where('gate_id', $this->gateId)
                    ->where('detection_timestamp', '>', now()->subSeconds($dedupeWindow))
                    ->exists();

                if ($recentDuplicate) {
                    $skipped++;
                    Log::debug('Skipping recent duplicate detection (same plate in last ' . $dedupeWindow . 's)', [
                        'plate' => $plateNumber,
                        'gate_id' => $this->gateId,
                        'timestamp' => $detectionTimestamp->toDateTimeString(),
                    ]);
                    continue;
                }

                // Optional: Keep tight check for exact duplicates (±2s + camera_detection_id)
                $cameraDetectionId = $detection['id'] ?? null;
                $tightDuplicate = false;
                if ($cameraDetectionId || $detectionTimestamp) {
                    $query = CameraDetectionLog::where('numberplate', $plateNumber)
                        ->where('gate_id', $this->gateId);

                    if ($detectionTimestamp) {
                        $query->whereBetween('detection_timestamp', [
                            $detectionTimestamp->copy()->subSeconds(2),
                            $detectionTimestamp->copy()->addSeconds(2)
                        ]);
                    }

                    if ($cameraDetectionId) {
                        $query->where('camera_detection_id', $cameraDetectionId);
                    }

                    $tightDuplicate = $query->exists();
                }

                if ($tightDuplicate) {
                    $skipped++;
                    Log::debug('Skipping tight duplicate detection', [
                        'plate' => $plateNumber,
                        'camera_detection_id' => $cameraDetectionId,
                    ]);
                    continue;
                }

                // Map and store
                $logData = $this->mapDetectionToLogData($detection);
                $this->repository->createDetectionLog($logData);
                $createdLog = CameraDetectionLog::latest('id')->first(); // Simple way to get the one just created
                event(new \App\Events\NewVehicleDetection($createdLog));
                $stored++;

                Log::info('✅ Stored NEW camera detection', [
                    'plate' => $plateNumber,
                    'gate_id' => $this->gateId,
                    'timestamp' => $detectionTimestamp->toDateTimeString(),
                ]);

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
            'dedupe_window_seconds' => $dedupeWindow,
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
        $lastTimestamp = $this->repository->getLatestDetectionTimestampForGate($this->gateId);

        Log::info('Fetching camera logs', [
            'gate_id' => $this->gateId,
            'last_timestamp' => $lastTimestamp ? $lastTimestamp->toDateTimeString() : 'none',
        ]);

        $fetchResult = $this->fetchCameraLogs($dateTime);

        if (!$fetchResult['success']) {
            return $fetchResult;
        }

        if ($fetchResult['count'] === 0) {
            return [
                'success' => true,
                'message' => 'No detections found from camera',
                'fetched' => 0,
                'stored' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        // Filter new detections (newer than last seen)
        $newDetections = [];
        $skippedOld = 0;

        foreach ($fetchResult['data'] as $detection) {
            $detectionTimestamp = isset($detection['timestamp'])
                ? Carbon::parse($detection['timestamp'])
                : null;

            $isNewer = !$lastTimestamp || ($detectionTimestamp && $detectionTimestamp->gt($lastTimestamp));

            $plateNumber = trim($detection['numberplate'] ?? '');
            $cameraDetectionId = $detection['id'] ?? null;

            $alreadyExists = false;
            if (!empty($plateNumber) && $detectionTimestamp) {
                $alreadyExists = CameraDetectionLog::where('numberplate', $plateNumber)
                    ->where('gate_id', $this->gateId)
                    ->whereBetween('detection_timestamp', [
                        $detectionTimestamp->copy()->subSeconds(2),
                        $detectionTimestamp->copy()->addSeconds(2)
                    ])
                    ->when($cameraDetectionId, fn($q) => $q->where('camera_detection_id', $cameraDetectionId))
                    ->exists();
            }

            if ($isNewer && !$alreadyExists) {
                $newDetections[] = $detection;
            } else {
                $skippedOld++;
            }
        }

        if (empty($newDetections)) {
            return [
                'success' => true,
                'message' => 'No new detections found',
                'fetched' => $fetchResult['count'],
                'stored' => 0,
                'skipped' => $fetchResult['count'],
                'errors' => 0,
            ];
        }

        $storeResult = $this->storeCameraLogs($newDetections);

        return [
            'success' => true,
            'message' => 'Camera logs fetched and stored successfully',
            'fetched' => $fetchResult['count'],
            'stored' => $storeResult['stored'],
            'skipped' => $storeResult['skipped'] + $skippedOld,
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
            'raw_data' => $detection,
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
            return DB::transaction(function () use (&$processed, &$errors) {
                $unprocessedDetections = CameraDetectionLog::where('processed', false)
                    ->where(function($query) {
                        $query->whereNull('processing_status')
                              ->orWhere('processing_status', 'pending');
                    })
                    ->orderBy('detection_timestamp', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
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

                $operatorId = $this->getSystemOperatorId();

                foreach ($unprocessedDetections as $detection) {
                    try {
                        $detection->refresh();

                        if ($detection->processed ||
                            ($detection->processing_status !== null &&
                             $detection->processing_status !== 'pending')) {
                            continue;
                        }

                        if (empty($detection->numberplate) || trim($detection->numberplate) === '') {
                            $detection->markAsProcessed('Skipped: Empty plate number');
                            $errors++;
                            continue;
                        }

                        $direction = $detection->direction;
                        $plateNumber = trim($detection->numberplate);
                        $gateId = $detection->gate_id ?? $this->gateId;

                        if (!$gateId) {
                            $detection->markAsFailed('Invalid gate ID');
                            $errors++;
                            Log::warning('Detection has invalid gate ID', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                            ]);
                            continue;
                        }

                        $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);

                        if (!$vehicle) {
                            $detection->update([
                                'processing_status' => 'pending_vehicle_type',
                                'processing_notes' => 'Vehicle not found - awaiting vehicle type selection',
                            ]);
                            Log::info('Detection marked as pending vehicle type (vehicle not found)', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                            ]);
                            continue;
                        }

                        $isEntryDirection = ($direction === 0 || $direction === null);

                        if ($isEntryDirection) {
                            $detection->update([
                                'processing_status' => 'pending_vehicle_type',
                                'processing_notes' => $vehicle
                                    ? 'Entry detection for existing vehicle - awaiting operator confirmation (body type not required)'
                                    : 'Entry detection - awaiting vehicle type selection',
                            ]);
                            Log::info('Detection marked as pending vehicle type (entry detection)', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                                'vehicle_exists' => (bool) $vehicle,
                            ]);
                            continue;
                        }

                        $lookupResult = $this->passageService->quickPlateLookup($plateNumber);
                        $hasActivePassage = $lookupResult['success'] &&
                                           isset($lookupResult['data']['active_passage']) &&
                                           $lookupResult['data']['active_passage'];

                        Log::debug('Active passage check for existing vehicle', [
                            'detection_id' => $detection->id,
                            'plate_number' => $plateNumber,
                            'has_active_passage' => $hasActivePassage,
                            'lookup_result' => $lookupResult,
                        ]);

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

                        if ($hasActivePassage && $gateSupportsExit) {
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
                            continue;
                        }

                        if (!$hasActivePassage) {
                            Log::info('Existing vehicle detected with no active passage - processing as entry', [
                                'detection_id' => $detection->id,
                                'plate_number' => $plateNumber,
                                'gate_id' => $gateId,
                                'direction' => $direction,
                            ]);
                        }

                        $additionalData = [
                            'make' => $detection->make_str,
                            'model' => $detection->model_str,
                            'color' => $detection->color_str,
                            'notes' => 'Automated camera detection',
                            'detection_timestamp' => $detection->detection_timestamp,
                            'camera_detection_log_id' => $detection->id,
                        ];

                        $result = null;

                        if ($direction === 0 || $direction === null) {
                            $result = $this->passageService->processVehicleEntry(
                                $plateNumber,
                                $gateId,
                                $operatorId,
                                $additionalData
                            );
                        } elseif ($direction === 1) {
                            if (!$hasActivePassage) {
                                $result = $this->passageService->processVehicleEntry(
                                    $plateNumber,
                                    $gateId,
                                    $operatorId,
                                    $additionalData
                                );
                            } else {
                                $detection->update([
                                    'processing_status' => 'pending_exit',
                                    'processing_notes' => 'Exit detection for parked vehicle - awaiting confirmation',
                                ]);
                                continue;
                            }
                        } else {
                            if ($hasActivePassage && $gateSupportsExit) {
                                $detection->update([
                                    'processing_status' => 'pending_exit',
                                    'processing_notes' => 'Vehicle has active passage - awaiting exit confirmation',
                                ]);
                                continue;
                            } else {
                                $result = $this->passageService->processVehicleEntry(
                                    $plateNumber,
                                    $gateId,
                                    $operatorId,
                                    $additionalData
                                );
                            }
                        }

                        if ($result && isset($result['success']) && $result['success']) {
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
                            $errorMessage = isset($result['message']) ? $result['message'] : 'Unknown error';

                            if (stripos($errorMessage, 'Vehicle already has an active passage') !== false) {
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
                                    continue;
                                } else {
                                    $detection->markAsProcessed("Vehicle already has active passage but gate doesn't support exit");
                                    Log::warning('Vehicle has active passage but gate does not support exit', [
                                        'detection_id' => $detection->id,
                                        'plate_number' => $plateNumber,
                                        'gate_id' => $gateId,
                                        'gate_type' => $gate->gate_type,
                                    ]);
                                }
                            } elseif (stripos($errorMessage, 'No pricing found') !== false || stripos($errorMessage, 'pricing') !== false) {
                                $detection->update([
                                    'processing_status' => 'pending_vehicle_type',
                                    'processing_notes' => "Pricing issue: {$errorMessage}",
                                ]);
                                Log::warning('Detection marked as pending due to pricing issue', [
                                    'detection_id' => $detection->id,
                                    'plate_number' => $plateNumber,
                                    'error' => $errorMessage,
                                ]);
                                continue;
                            } elseif (stripos($errorMessage, 'Vehicle not found') !== false) {
                                $detection->update([
                                    'processing_status' => 'pending_vehicle_type',
                                    'processing_notes' => "Vehicle lookup failed: {$errorMessage}",
                                ]);
                                Log::warning('Vehicle not found during processing (should have been caught earlier)', [
                                    'detection_id' => $detection->id,
                                    'plate_number' => $plateNumber,
                                    'error' => $errorMessage,
                                ]);
                                continue;
                            } else {
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
            }, 5);

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
        $operatorId = env('CAMERA_OPERATOR_ID');
        if ($operatorId && is_numeric($operatorId)) {
            $user = User::find($operatorId);
            if ($user && $user->is_active) {
                return (int) $operatorId;
            }
        }

        $adminUser = User::active()
            ->whereHas('role', function ($query) {
                $query->where('name', 'System Admin');
            })
            ->first();

        if ($adminUser) {
            return $adminUser->id;
        }

        $activeUser = User::active()->first();
        if ($activeUser) {
            return $activeUser->id;
        }

        return 1;
    }
}