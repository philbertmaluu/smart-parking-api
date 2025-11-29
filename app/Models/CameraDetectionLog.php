<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CameraDetectionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'camera_detection_id',
        'gate_id',
        'numberplate',
        'originalplate',
        'detection_timestamp',
        'utc_time',
        'located_plate',
        'global_confidence',
        'average_char_height',
        'process_time',
        'plate_format',
        'country',
        'country_str',
        'vehicle_left',
        'vehicle_top',
        'vehicle_right',
        'vehicle_bottom',
        'result_left',
        'result_top',
        'result_right',
        'result_bottom',
        'speed',
        'lane_id',
        'direction',
        'make',
        'model',
        'color',
        'make_str',
        'model_str',
        'color_str',
        'veclass_str',
        'image_path',
        'image_retail_path',
        'width',
        'height',
        'list_id',
        'name_list_id',
        'evidences',
        'br_ocurr',
        'br_time',
        'raw_data',
        'processed',
        'processed_at',
        'processing_notes',
        'processing_status',
    ];

    protected $casts = [
        'detection_timestamp' => 'datetime',
        'utc_time' => 'datetime',
        'processed_at' => 'datetime',
        'located_plate' => 'boolean',
        'processed' => 'boolean',
        'global_confidence' => 'decimal:2',
        'average_char_height' => 'decimal:2',
        'speed' => 'decimal:2',
        'raw_data' => 'array',
        'process_time' => 'integer',
        'plate_format' => 'integer',
        'country' => 'integer',
        'vehicle_left' => 'integer',
        'vehicle_top' => 'integer',
        'vehicle_right' => 'integer',
        'vehicle_bottom' => 'integer',
        'result_left' => 'integer',
        'result_top' => 'integer',
        'result_right' => 'integer',
        'result_bottom' => 'integer',
        'lane_id' => 'integer',
        'direction' => 'integer',
        'make' => 'integer',
        'model' => 'integer',
        'color' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'evidences' => 'integer',
        'br_ocurr' => 'integer',
        'br_time' => 'integer',
    ];

    /**
     * Scope a query to only include unprocessed detections.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope a query to only include processed detections.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessed($query)
    {
        return $query->where('processed', true);
    }

    /**
     * Scope a query to only include detections pending vehicle type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendingVehicleType($query)
    {
        return $query->where('processing_status', 'pending_vehicle_type');
    }

    /**
     * Scope a query to only include detections pending exit.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePendingExit($query)
    {
        return $query->where('processing_status', 'pending_exit');
    }

    /**
     * Scope a query to filter by processing status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByProcessingStatus($query, string $status)
    {
        return $query->where('processing_status', $status);
    }

    /**
     * Scope a query to filter by plate number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $plateNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPlateNumber($query, string $plateNumber)
    {
        return $query->where('numberplate', $plateNumber);
    }

    /**
     * Scope a query to filter by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('detection_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by gate.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $gateId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByGate($query, int $gateId)
    {
        return $query->where('gate_id', $gateId);
    }

    /**
     * Mark detection as processed.
     *
     * @param string|null $notes
     * @return bool
     */
    public function markAsProcessed(?string $notes = null): bool
    {
        return $this->update([
            'processed' => true,
            'processing_status' => 'processed',
            'processed_at' => now(),
            'processing_notes' => $notes,
        ]);
    }

    /**
     * Mark detection as pending vehicle type.
     *
     * @param string|null $notes
     * @return bool
     */
    public function markAsPendingVehicleType(?string $notes = null): bool
    {
        return $this->update([
            'processing_status' => 'pending_vehicle_type',
            'processing_notes' => $notes,
        ]);
    }

    /**
     * Mark detection as pending exit.
     *
     * @param string|null $notes
     * @return bool
     */
    public function markAsPendingExit(?string $notes = null): bool
    {
        return $this->update([
            'processing_status' => 'pending_exit',
            'processing_notes' => $notes,
        ]);
    }

    /**
     * Mark detection as failed.
     *
     * @param string|null $notes
     * @return bool
     */
    public function markAsFailed(?string $notes = null): bool
    {
        return $this->update([
            'processed' => true,
            'processing_status' => 'failed',
            'processed_at' => now(),
            'processing_notes' => $notes,
        ]);
    }

    /**
     * Get the gate that owns this detection.
     */
    public function gate()
    {
        return $this->belongsTo(\App\Models\Gate::class);
    }
}

