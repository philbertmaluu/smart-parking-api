<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Services\CameraDetectionService;
use App\Services\VehiclePassageService;
use App\Repositories\CameraDetectionLogRepository;
use App\Repositories\VehicleRepository;
use App\Models\CameraDetectionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CameraDetectionController extends BaseController
{
    protected $cameraDetectionService;
    protected $repository;
    protected $vehiclePassageService;
    protected $vehicleRepository;

    public function __construct(
        CameraDetectionService $cameraDetectionService,
        CameraDetectionLogRepository $repository,
        VehiclePassageService $vehiclePassageService,
        VehicleRepository $vehicleRepository
    ) {
        $this->cameraDetectionService = $cameraDetectionService;
        $this->repository = $repository;
        $this->vehiclePassageService = $vehiclePassageService;
        $this->vehicleRepository = $vehicleRepository;
    }

    /**
     * Fetch camera logs from database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchLogs(Request $request)
    {
        try {
            // Reduced default per_page from 100 to 15 for faster initial load
            $perPage = min((int)$request->get('per_page', 15), 100); // Max 100, default 15
            $plateNumber = $request->get('plate_number');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $processed = $request->get('processed');
            $gateId = $request->get('gate_id');

            // Optimize query: only load necessary relationships and columns
            $query = CameraDetectionLog::with('gate:id,name,station_id')
                ->select([
                    'id', 'camera_detection_id', 'gate_id', 'numberplate', 'originalplate',
                    'detection_timestamp', 'utc_time', 'located_plate', 'global_confidence',
                    'processed', 'processed_at', 'processing_status', 'created_at', 'updated_at'
                ]);

            // Filter by gate - operators see only their gates
            $user = auth()->user();
            if ($user && $user->role_id === 3) { // Operator role
                // Get operator's assigned gates
                $operatorGates = DB::table('operator_station')
                    ->where('user_id', $user->id)
                    ->pluck('gate_id')
                    ->toArray();
                
                if (!empty($operatorGates)) {
                    $query->whereIn('gate_id', $operatorGates);
                } else {
                    // Operator has no assigned gates
                    $query->whereRaw('1 = 0'); // Return empty
                }
            } elseif ($gateId) {
                // Admin can filter by specific gate
                $query->byGate($gateId);
            }

            // Filter by plate number
            if ($plateNumber) {
                $query->byPlateNumber($plateNumber);
            }

            // Filter by date range
            if ($startDate && $endDate) {
                $query->byDateRange($startDate, $endDate);
            }

            // Filter by processed status
            if ($processed !== null) {
                if ($processed === 'true' || $processed === true) {
                    $query->processed();
                } elseif ($processed === 'false' || $processed === false) {
                    $query->unprocessed();
                }
            }

            // Get total count (optimized: count before pagination)
            $count = $query->count();

            // Get detections ordered by most recent with limit
            $detections = $query->orderBy('detection_timestamp', 'desc')
                ->orderBy('id', 'desc') // Secondary sort for consistency
                ->limit($perPage)
                ->get();

            return $this->sendResponse([
                'detections' => $detections,
                'count' => $count
            ], 'Camera logs fetched successfully from database');

        } catch (\Exception $e) {
            return $this->sendError('Error fetching camera logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get latest detection info (lightweight check for polling)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLatestDetectionInfo(Request $request)
    {
        try {
            $gateId = $request->get('gate_id');

            $query = CameraDetectionLog::query();

            // Filter by gate - operators see only their gates
            $user = auth()->user();
            if ($user && $user->role_id === 3) { // Operator role
                // Get operator's assigned gates
                $operatorGates = DB::table('operator_station')
                    ->where('user_id', $user->id)
                    ->pluck('gate_id')
                    ->toArray();
                
                if (!empty($operatorGates)) {
                    $query->whereIn('gate_id', $operatorGates);
                } else {
                    // Operator has no assigned gates
                    return $this->sendResponse([
                        'latest_id' => 0,
                        'total_count' => 0,
                        'latest_timestamp' => null,
                    ], 'Latest detection info retrieved successfully');
                }
            } elseif ($gateId) {
                // Admin can filter by specific gate
                $query->byGate($gateId);
            }

            // Get total count
            $count = $query->count();

            // Get latest detection (only ID and timestamp)
            $latestDetection = $query->orderBy('detection_timestamp', 'desc')
                ->orderBy('id', 'desc')
                ->select('id', 'detection_timestamp')
                ->first();

            return $this->sendResponse([
                'latest_id' => $latestDetection ? $latestDetection->id : 0,
                'total_count' => $count,
                'latest_timestamp' => $latestDetection ? $latestDetection->detection_timestamp : null,
            ], 'Latest detection info retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error retrieving latest detection info', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Error retrieving latest detection info', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store camera logs in database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeLogs(Request $request)
    {
        try {
            $request->validate([
                'detections' => 'required|array',
                'detections.*' => 'required|array',
            ]);

            $result = $this->cameraDetectionService->storeCameraLogs($request->input('detections'));

            return $this->sendResponse($result, 'Camera logs stored successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error storing camera logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch and store camera logs in one operation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchAndStoreLogs(Request $request)
    {
        try {
            $dateTime = null;
            
            // Optional date parameter
            if ($request->has('date')) {
                try {
                    $dateTime = Carbon::parse($request->input('date'));
                } catch (\Exception $e) {
                    return $this->sendError('Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:mm:ss', [], 400);
                }
            }

            // Ensure passage service is set for auto-processing
            $this->cameraDetectionService->setPassageService($this->vehiclePassageService);

            $result = $this->cameraDetectionService->fetchAndStoreLogs($dateTime);

            if ($result['success']) {
                // Auto-process unprocessed detections after storing
                // This ensures new detections are immediately converted to pending_vehicle_type or pending_exit status
                $processResult = $this->cameraDetectionService->processUnprocessedDetections();
                
                Log::info('Auto-processed detections after fetch-and-store', [
                    'stored' => $result['stored'],
                    'processed' => $processResult['processed'] ?? 0,
                    'errors' => $processResult['errors'] ?? 0,
                ]);

                return $this->sendResponse([
                    'fetched' => $result['fetched'],
                    'stored' => $result['stored'],
                    'skipped' => $result['skipped'],
                    'errors' => $result['errors'],
                    'processed' => $processResult['processed'] ?? 0,
                    'processing_errors' => $processResult['errors'] ?? 0,
                ], 'Camera logs fetched, stored, and processed successfully');
            }

            // Handle camera connection failures gracefully - return 200 with error message
            // instead of 500 to prevent frontend errors
            if (str_contains($result['message'], 'unreachable') || str_contains($result['message'], 'timeout')) {
                return $this->sendResponse([
                    'fetched' => 0,
                    'stored' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                    'camera_unavailable' => true,
                ], $result['message']);
            }

            // For other errors, still return 500
            return $this->sendError($result['message'], [], 500);

        } catch (\Exception $e) {
            Log::error('Error in fetchAndStoreLogs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Error fetching and storing camera logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Quick capture - optimized for operator real-time vehicle capture
     * Only fetches recent detections (last 2 minutes) for faster response
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function quickCapture(Request $request)
    {
        try {
            // Only fetch detections from the last 2 minutes for quick capture
            // This is much faster than fetching all historical data
            $recentDateTime = Carbon::now()->subMinutes(2);
            
            // Ensure passage service is set for auto-processing
            $this->cameraDetectionService->setPassageService($this->vehiclePassageService);

            // Fetch only recent logs
            $result = $this->cameraDetectionService->fetchAndStoreLogs($recentDateTime);

            if ($result['success']) {
                // Process only newly stored detections (not all unprocessed)
                $processResult = $this->cameraDetectionService->processUnprocessedDetections();
                
                Log::info('Quick capture completed', [
                    'fetched' => $result['fetched'],
                    'stored' => $result['stored'],
                    'processed' => $processResult['processed'] ?? 0,
                ]);

                return $this->sendResponse([
                    'fetched' => $result['fetched'],
                    'stored' => $result['stored'],
                    'skipped' => $result['skipped'],
                    'errors' => $result['errors'],
                    'processed' => $processResult['processed'] ?? 0,
                    'processing_errors' => $processResult['errors'] ?? 0,
                ], $result['stored'] > 0 
                    ? 'Vehicle captured successfully!' 
                    : 'No new vehicle detected. Ensure vehicle is in camera view.');
            }

            // Handle camera connection failures gracefully
            if (str_contains($result['message'], 'unreachable') || str_contains($result['message'], 'timeout')) {
                return $this->sendResponse([
                    'fetched' => 0,
                    'stored' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                    'camera_unavailable' => true,
                ], 'Camera is not responding. Please check connection.');
            }

            return $this->sendError($result['message'], [], 500);

        } catch (\Exception $e) {
            Log::error('Error in quickCapture', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Capture failed. Please try again.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stored detection logs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStoredLogs(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $plateNumber = $request->get('plate_number');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $processed = $request->get('processed'); // 'true', 'false', or null for all

            $query = CameraDetectionLog::query();

            // Filter by plate number
            if ($plateNumber) {
                $query->byPlateNumber($plateNumber);
            }

            // Filter by date range
            if ($startDate && $endDate) {
                $query->byDateRange($startDate, $endDate);
            }

            // Filter by processed status
            if ($processed !== null) {
                if ($processed === 'true' || $processed === true) {
                    $query->processed();
                } elseif ($processed === 'false' || $processed === false) {
                    $query->unprocessed();
                }
            }

            $logs = $query->orderBy('detection_timestamp', 'desc')->paginate($perPage);

            return $this->sendResponse($logs, 'Detection logs retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('Error retrieving detection logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get unprocessed detection logs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnprocessedLogs(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $logs = $this->repository->getUnprocessedDetections();

            // Manual pagination for collection
            $page = $request->get('page', 1);
            $perPage = (int) $perPage;
            $offset = ($page - 1) * $perPage;
            $items = $logs->slice($offset, $perPage)->values();
            $total = $logs->count();

            $paginated = [
                'data' => $items,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ];

            return $this->sendResponse($paginated, 'Unprocessed detection logs retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('Error retrieving unprocessed logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get detection logs by plate number
     *
     * @param Request $request
     * @param string $plateNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLogsByPlateNumber(Request $request, string $plateNumber)
    {
        try {
            $logs = $this->repository->getDetectionsByPlateNumber($plateNumber);

            return $this->sendResponse($logs, "Detection logs for plate number {$plateNumber} retrieved successfully");

        } catch (\Exception $e) {
            return $this->sendError('Error retrieving detection logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark detection as processed
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsProcessed(Request $request, int $id)
    {
        try {
            $log = $this->repository->findById($id);

            if (!$log) {
                return $this->sendError('Detection log not found', [], 404);
            }

            $notes = $request->input('notes');
            $log->markAsProcessed($notes);

            return $this->sendResponse($log, 'Detection log marked as processed');

        } catch (\Exception $e) {
            return $this->sendError('Error marking detection as processed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get camera detection service configuration
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfig()
    {
        try {
            $config = $this->cameraDetectionService->getConfig();

            return $this->sendResponse($config, 'Camera detection configuration retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('Error retrieving configuration', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get detections pending vehicle type selection
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingVehicleTypeDetections(Request $request)
    {
        try {
            $detections = $this->repository->getPendingVehicleTypeDetections();

            // Filter by gate if operator
            $user = auth()->user();
            if ($user && $user->role_id === 3) { // Operator role
                // Get operator's assigned stations
                $assignedStations = DB::table('operator_station')
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('station_id')
                    ->toArray();
                
                if (!empty($assignedStations)) {
                    // Get all gates for the operator's assigned stations
                    $operatorGates = \App\Models\Gate::whereIn('station_id', $assignedStations)
                        ->where('is_active', true)
                        ->pluck('id')
                        ->toArray();
                    
                    if (!empty($operatorGates)) {
                        $detections = $detections->whereIn('gate_id', $operatorGates);
                    } else {
                        // No gates found for operator's stations
                        $detections = collect([]);
                    }
                } else {
                    // Operator has no assigned stations
                    $detections = collect([]);
                }
            }

            $count = $detections->count();
            $detectionsArray = $detections->values()->toArray();
            
            // Log queue status for debugging
            Log::info('Pending vehicle type detections retrieved', [
                'count' => $count,
                'user_id' => $user?->id,
                'user_role' => $user?->role_id,
                'oldest_detection_id' => $count > 0 ? $detectionsArray[0]['id'] ?? null : null,
                'oldest_detection_plate' => $count > 0 ? $detectionsArray[0]['numberplate'] ?? null : null,
                'oldest_detection_timestamp' => $count > 0 ? $detectionsArray[0]['detection_timestamp'] ?? null : null,
            ]);

            return $this->sendResponse($detectionsArray, 'Pending vehicle type detections retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('Error retrieving pending detections', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get detections pending exit confirmation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingExitDetections(Request $request)
    {
        try {
            $detections = $this->repository->getPendingExitDetections();

            // Filter by gate if operator
            $user = auth()->user();
            if ($user && $user->role_id === 3) { // Operator role
                // Get operator's assigned stations
                $assignedStations = DB::table('operator_station')
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('station_id')
                    ->toArray();
                
                if (!empty($assignedStations)) {
                    // Get all gates for the operator's assigned stations
                    $operatorGates = \App\Models\Gate::whereIn('station_id', $assignedStations)
                        ->where('is_active', true)
                        ->pluck('id')
                        ->toArray();
                    
                    if (!empty($operatorGates)) {
                        $detections = $detections->whereIn('gate_id', $operatorGates);
                    } else {
                        // No gates found for operator's stations
                        $detections = collect([]);
                    }
                } else {
                    // Operator has no assigned stations
                    $detections = collect([]);
                }
            }

            // Load vehicle and active passage information for each detection
            $detectionsWithPassage = $detections->map(function ($detection) {
                $plateNumber = trim($detection->numberplate);
                if (empty($plateNumber)) {
                    return null;
                }

                // Get vehicle
                $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);
                if (!$vehicle) {
                    return null;
                }

                // Get active passage
                $lookupResult = $this->vehiclePassageService->quickPlateLookup($plateNumber);
                $activePassage = null;
                if ($lookupResult['success'] && isset($lookupResult['data']['active_passage'])) {
                    $activePassage = $lookupResult['data']['active_passage'];
                }

                return [
                    'id' => $detection->id,
                    'numberplate' => $detection->numberplate,
                    'detection_timestamp' => $detection->detection_timestamp,
                    'gate_id' => $detection->gate_id,
                    'direction' => $detection->direction,
                    'make_str' => $detection->make_str,
                    'model_str' => $detection->model_str,
                    'color_str' => $detection->color_str,
                    'processing_status' => $detection->processing_status,
                    'processing_notes' => $detection->processing_notes,
                    'vehicle' => $vehicle,
                    'active_passage' => $activePassage,
                ];
            })->filter()->values();

            $count = $detectionsWithPassage->count();
            $detectionsArray = $detectionsWithPassage->toArray();
            
            // Log queue status for debugging
            Log::info('Pending exit detections retrieved', [
                'count' => $count,
                'user_id' => $user?->id,
                'user_role' => $user?->role_id,
                'oldest_detection_id' => $count > 0 ? $detectionsArray[0]['id'] ?? null : null,
                'oldest_detection_plate' => $count > 0 ? $detectionsArray[0]['numberplate'] ?? null : null,
                'oldest_detection_timestamp' => $count > 0 ? $detectionsArray[0]['detection_timestamp'] ?? null : null,
            ]);

            return $this->sendResponse($detectionsArray, 'Pending exit detections retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error retrieving pending exit detections', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Error retrieving pending exit detections', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Process detection with vehicle type
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function processWithVehicleType(Request $request, int $id)
    {
        try {
            $request->validate([
                'body_type_id' => 'required|integer|exists:vehicle_body_types,id',
            ]);

            $detection = $this->repository->findById($id);

            if (!$detection) {
                return $this->sendError('Detection not found', [], 404);
            }

            // Allow processing if status is pending_vehicle_type OR if vehicle already exists (reprocess)
            $plateNumber = trim($detection->numberplate);
            if (empty($plateNumber)) {
                return $this->sendError('Plate number is empty', [], 400);
            }

            // Check if vehicle already exists
            $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);
            
            if (!$vehicle) {
                // Check status before creating vehicle
                if ($detection->processing_status !== 'pending_vehicle_type') {
                    return $this->sendError('Detection is not pending vehicle type selection', [], 400);
                }

                // Create vehicle with provided body type
                $vehicleData = [
                    'plate_number' => $plateNumber,
                    'body_type_id' => $request->input('body_type_id'),
                    'make' => $detection->make_str,
                    'model' => $detection->model_str,
                    'color' => $detection->color_str,
                    'is_registered' => false,
                ];

                $vehicle = $this->vehicleRepository->createVehicle($vehicleData);
                Log::info('Vehicle created from pending detection', [
                    'detection_id' => $detection->id,
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $plateNumber,
                ]);
            } else {
                // Vehicle already exists - update status if needed and proceed with processing
                Log::info('Vehicle already exists, processing detection', [
                    'detection_id' => $detection->id,
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $plateNumber,
                    'current_status' => $detection->processing_status,
                ]);
            }

            // Get operator ID
            $operatorId = auth()->id() ?? $this->getSystemOperatorId();
            $gateId = $detection->gate_id;

            // Prepare additional data
            // Get default payment method (Cash) for camera detections
            $defaultPaymentType = \App\Models\PaymentType::where('name', 'Cash')->first();
            if (!$defaultPaymentType) {
                // Create default payment type if it doesn't exist
                $defaultPaymentType = \App\Models\PaymentType::create([
                    'name' => 'Cash',
                    'description' => 'Cash payment',
                    'is_active' => true
                ]);
            }

            $additionalData = [
                'make' => $detection->make_str,
                'model' => $detection->model_str,
                'color' => $detection->color_str,
                'notes' => 'Processed from camera detection with vehicle type selection',
                'detection_timestamp' => $detection->detection_timestamp,
                'camera_detection_log_id' => $detection->id,
                'payment_method' => 'cash', // Default payment method for camera detections
            ];

            // Process based on direction
            $direction = $detection->direction;
            $result = null;

            if ($direction === 0 || $direction === null) {
                // Entry detection
                $result = $this->vehiclePassageService->processVehicleEntry(
                    $plateNumber,
                    $gateId,
                    $operatorId,
                    $additionalData
                );
            } elseif ($direction === 1) {
                // Exit detection
                $result = $this->vehiclePassageService->processVehicleExit(
                    $plateNumber,
                    $gateId,
                    $operatorId,
                    $additionalData
                );
            } else {
                // Unknown direction - determine from active passage
                $lookupResult = $this->vehiclePassageService->quickPlateLookup($plateNumber);
                
                if ($lookupResult['success'] && isset($lookupResult['data']['active_passage']) && $lookupResult['data']['active_passage']) {
                    // Has active passage, treat as exit
                    $result = $this->vehiclePassageService->processVehicleExit(
                        $plateNumber,
                        $gateId,
                        $operatorId,
                        $additionalData
                    );
                } else {
                    // No active passage, treat as entry
                    $result = $this->vehiclePassageService->processVehicleEntry(
                        $plateNumber,
                        $gateId,
                        $operatorId,
                        $additionalData
                    );
                }
            }

            // Mark detection as processed
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
                    "Processed as {$directionLabel} with vehicle type selection. Passage ID: {$passageId}"
                );

                return $this->sendResponse([
                    'detection' => $detection,
                    'vehicle' => $vehicle,
                    'passage' => $result['data'] ?? null,
                ], 'Detection processed successfully with vehicle type');
            } else {
                $errorMessage = isset($result['message']) ? $result['message'] : 'Unknown error';
                $detection->markAsFailed("Failed to process: {$errorMessage}");
                
                return $this->sendError('Failed to process detection', [
                    'error' => $errorMessage,
                    'result' => $result,
                ], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error processing detection with vehicle type', [
                'detection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Error processing detection', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Process exit detection with operator confirmation
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function processExitDetection(Request $request, int $id)
    {
        try {
            $detection = $this->repository->findById($id);

            if (!$detection) {
                return $this->sendError('Detection not found', [], 404);
            }

            // Check if detection is pending exit
            if ($detection->processing_status !== 'pending_exit') {
                return $this->sendError('Detection is not pending exit confirmation', [], 400);
            }

            $plateNumber = trim($detection->numberplate);
            if (empty($plateNumber)) {
                return $this->sendError('Plate number is empty', [], 400);
            }

            // Get operator ID
            $operatorId = auth()->id() ?? $this->getSystemOperatorId();
            $gateId = $detection->gate_id;

            // Prepare additional data
            $additionalData = [
                'make' => $detection->make_str,
                'model' => $detection->model_str,
                'color' => $detection->color_str,
                'notes' => 'Processed from camera detection - exit confirmation',
                'detection_timestamp' => $detection->detection_timestamp,
                'camera_detection_log_id' => $detection->id,
                'payment_confirmed' => $request->input('payment_confirmed', false),
            ];

            // Process exit
            $result = $this->vehiclePassageService->processVehicleExit(
                $plateNumber,
                $gateId,
                $operatorId,
                $additionalData
            );

            // Mark detection as processed if successful
            if ($result && isset($result['success']) && $result['success']) {
                $passageId = 'N/A';
                if (isset($result['data'])) {
                    if (is_object($result['data']) && isset($result['data']->id)) {
                        $passageId = $result['data']->id;
                    } elseif (is_array($result['data']) && isset($result['data']['id'])) {
                        $passageId = $result['data']['id'];
                    }
                }
                
                $detection->markAsProcessed(
                    "Processed as exit with operator confirmation. Passage ID: {$passageId}"
                );

                return $this->sendResponse([
                    'detection' => $detection,
                    'passage' => $result['data'] ?? null,
                    'result' => $result,
                ], 'Exit detection processed successfully');
            } else {
                $errorMessage = isset($result['message']) ? $result['message'] : 'Unknown error';
                $detection->markAsFailed("Failed to process exit: {$errorMessage}");
                
                return $this->sendError('Failed to process exit detection', [
                    'error' => $errorMessage,
                    'result' => $result,
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error processing exit detection', [
                'detection_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Error processing exit detection', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get queue status - pending detection counts
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getQueueStatus(Request $request)
    {
        try {
            // Get unprocessed detections (stuck in pending status)
            $unprocessedCount = CameraDetectionLog::where('processed', false)
                ->where(function($query) {
                    $query->whereNull('processing_status')
                          ->orWhere('processing_status', 'pending');
                })
                ->count();
            
            // Get pending vehicle type detections
            $pendingVehicleTypeCount = $this->repository->getPendingVehicleTypeDetections()->count();
            
            // Get pending exit detections
            $pendingExitCount = $this->repository->getPendingExitDetections()->count();
            
            // Get total detections
            $totalDetections = CameraDetectionLog::count();
            
            // Get processed detections
            $processedCount = CameraDetectionLog::where('processed', true)->count();
            
            // Get oldest unprocessed detection
            $oldestUnprocessed = CameraDetectionLog::where('processed', false)
                ->where(function($query) {
                    $query->whereNull('processing_status')
                          ->orWhere('processing_status', 'pending');
                })
                ->orderBy('detection_timestamp', 'asc')
                ->orderBy('id', 'asc')
                ->first();
            
            // Get oldest pending vehicle type detection
            $oldestPendingVehicleType = $this->repository->getPendingVehicleTypeDetections()->first();
            
            // Get oldest pending exit detection
            $oldestPendingExit = $this->repository->getPendingExitDetections()->first();
            
            return $this->sendResponse([
                'unprocessed' => $unprocessedCount,
                'pending_vehicle_type' => $pendingVehicleTypeCount,
                'pending_exit' => $pendingExitCount,
                'total' => $totalDetections,
                'processed' => $processedCount,
                'oldest_unprocessed' => $oldestUnprocessed ? [
                    'id' => $oldestUnprocessed->id,
                    'plate_number' => $oldestUnprocessed->numberplate,
                    'detection_timestamp' => $oldestUnprocessed->detection_timestamp,
                ] : null,
                'oldest_pending_vehicle_type' => $oldestPendingVehicleType ? [
                    'id' => $oldestPendingVehicleType->id,
                    'plate_number' => $oldestPendingVehicleType->numberplate,
                    'detection_timestamp' => $oldestPendingVehicleType->detection_timestamp,
                ] : null,
                'oldest_pending_exit' => $oldestPendingExit ? [
                    'id' => $oldestPendingExit->id,
                    'plate_number' => $oldestPendingExit->numberplate,
                    'detection_timestamp' => $oldestPendingExit->detection_timestamp,
                ] : null,
            ], 'Queue status retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving queue status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Error retrieving queue status', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get system operator ID for automated processes
     *
     * @return int
     */
    private function getSystemOperatorId(): int
    {
        // Check for configured operator ID in environment
        $operatorId = env('CAMERA_OPERATOR_ID');
        if ($operatorId && is_numeric($operatorId)) {
            $user = \App\Models\User::find($operatorId);
            if ($user && $user->is_active) {
                return (int) $operatorId;
            }
        }

        // Try to get first active admin user
        $adminUser = \App\Models\User::active()
            ->whereHas('role', function ($query) {
                $query->where('name', 'System Admin');
            })
            ->first();

        if ($adminUser) {
            return $adminUser->id;
        }

        // Fallback: get first active user
        $activeUser = \App\Models\User::active()->first();
        if ($activeUser) {
            return $activeUser->id;
        }

        // Last resort: return 1 (should exist as system user)
        return 1;
    }
}

