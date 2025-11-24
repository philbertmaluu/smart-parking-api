<?php

namespace App\Services;

use App\Repositories\CameraDetectionLogRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    private CameraDetectionLogRepository $repository;

    public function __construct(CameraDetectionLogRepository $repository)
    {
        $this->cameraIp = env('CAMERA_IP', '192.168.0.109');
        $this->computerId = (int) env('CAMERA_COMPUTER_ID', 1);
        $this->repository = $repository;
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
            $dateTime = $dateTime ?? now();
            
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
            $response = Http::timeout(30)
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
        return [
            'camera_detection_id' => $detection['id'] ?? null,
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
        ];
    }
}

