<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Services\CameraDetectionService;
use App\Repositories\CameraDetectionLogRepository;
use App\Models\CameraDetectionLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CameraDetectionController extends BaseController
{
    protected $cameraDetectionService;
    protected $repository;

    public function __construct(
        CameraDetectionService $cameraDetectionService,
        CameraDetectionLogRepository $repository
    ) {
        $this->cameraDetectionService = $cameraDetectionService;
        $this->repository = $repository;
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
            $perPage = $request->get('per_page', 100);
            $plateNumber = $request->get('plate_number');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $processed = $request->get('processed');
            $gateId = $request->get('gate_id');

            $query = CameraDetectionLog::with('gate:id,name,station_id');

            // Filter by gate - operators see only their gates
            $user = auth()->user();
            if ($user && $user->role_id === 3) { // Operator role
                // Get operator's assigned gates
                $operatorGates = \DB::table('operator_station')
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

            // Get total count
            $count = $query->count();

            // Get detections ordered by most recent
            $detections = $query->orderBy('detection_timestamp', 'desc')
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

            $result = $this->cameraDetectionService->fetchAndStoreLogs($dateTime);

            if ($result['success']) {
                return $this->sendResponse([
                    'fetched' => $result['fetched'],
                    'stored' => $result['stored'],
                    'skipped' => $result['skipped'],
                    'errors' => $result['errors']
                ], 'Camera logs fetched and stored successfully');
            }

            return $this->sendError($result['message'], [], 500);

        } catch (\Exception $e) {
            return $this->sendError('Error fetching and storing camera logs', ['error' => $e->getMessage()], 500);
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
}

