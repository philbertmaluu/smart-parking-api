<?php

namespace App\Repositories;

use App\Models\CameraDetectionLog;
use Illuminate\Database\Eloquent\Collection;

class CameraDetectionLogRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new CameraDetectionLog());
    }

    /**
     * Create a new camera detection log entry.
     *
     * @param array $data
     * @return CameraDetectionLog
     */
    public function createDetectionLog(array $data): CameraDetectionLog
    {
        return $this->create($data);
    }

    /**
     * Get unprocessed detections.
     *
     * @return Collection
     */
    public function getUnprocessedDetections(): Collection
    {
        return $this->model->unprocessed()->orderBy('detection_timestamp', 'asc')->get();
    }

    /**
     * Get detections pending vehicle type selection.
     * Ordered by oldest first (FIFO queue) to ensure first-come-first-served processing.
     *
     * @return Collection
     */
    public function getPendingVehicleTypeDetections(): Collection
    {
        return $this->model->pendingVehicleType()
            ->orderBy('detection_timestamp', 'asc')  // Oldest first for FIFO queue
            ->orderBy('id', 'asc')  // Secondary sort for consistency
            ->get();
    }

    /**
     * Get detections pending exit confirmation.
     * Ordered by oldest first (FIFO queue) to ensure first-come-first-served processing.
     *
     * @return Collection
     */
    public function getPendingExitDetections(): Collection
    {
        return $this->model->pendingExit()
            ->orderBy('detection_timestamp', 'asc')  // Oldest first for FIFO queue
            ->orderBy('id', 'asc')  // Secondary sort for consistency
            ->get();
    }

    /**
     * Get detections by plate number.
     *
     * @param string $plateNumber
     * @return Collection
     */
    public function getDetectionsByPlateNumber(string $plateNumber): Collection
    {
        return $this->model->byPlateNumber($plateNumber)->orderBy('detection_timestamp', 'desc')->get();
    }

    /**
     * Get detections by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getDetectionsByDateRange(string $startDate, string $endDate): Collection
    {
        return $this->model->byDateRange($startDate, $endDate)->orderBy('detection_timestamp', 'desc')->get();
    }

    /**
     * Check if detection with camera_detection_id already exists.
     *
     * @param int $cameraDetectionId
     * @return bool
     */
    public function detectionExists(int $cameraDetectionId): bool
    {
        return $this->model->where('camera_detection_id', $cameraDetectionId)->exists();
    }

    /**
     * Fallback duplicate check by plate + timestamp + gate.
     */
    public function detectionExistsByComposite(?string $plateNumber, ?string $timestamp, ?int $gateId): bool
    {
        if (!$plateNumber || !$timestamp || !$gateId) {
            return false;
        }

        try {
            $ts = \Carbon\Carbon::parse($timestamp);
        } catch (\Exception $e) {
            return false;
        }

        return $this->model
            ->where('gate_id', $gateId)
            ->where('numberplate', $plateNumber)
            ->where('detection_timestamp', $ts)
            ->exists();
    }

    /**
     * Get latest detection timestamp.
     *
     * @return \Carbon\Carbon|null
     */
    public function getLatestDetectionTimestamp(): ?\Carbon\Carbon
    {
        $latest = $this->model->orderBy('detection_timestamp', 'desc')->first();
        return $latest ? $latest->detection_timestamp : null;
    }

    /**
     * Get latest detection timestamp for a specific gate.
     * This helps filter out old detections and only process new ones.
     * IMPORTANT: Only consider processed detections to ensure new detections aren't blocked
     * by pending ones that haven't been processed yet.
     *
     * @param int $gateId
     * @return \Carbon\Carbon|null
     */
    public function getLatestDetectionTimestampForGate(int $gateId): ?\Carbon\Carbon
    {
        // Only get the latest PROCESSED detection timestamp
        // This ensures that new detections aren't blocked by pending ones
        $latest = $this->model
            ->where('gate_id', $gateId)
            ->where('processed', true) // Only consider processed detections
            ->orderBy('detection_timestamp', 'desc')
            ->first();
        return $latest ? $latest->detection_timestamp : null;
    }
}

