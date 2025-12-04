<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BundleVehicle extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'bundle_id',
        'vehicle_body_type_id',
        'max_count',
    ];

    protected $casts = [
        'max_count' => 'integer',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['bundle_id', 'vehicle_body_type_id', 'max_count'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the bundle that owns this bundle vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * Get the vehicle body type that owns this bundle vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehicleBodyType()
    {
        return $this->belongsTo(VehicleBodyType::class, 'vehicle_body_type_id');
    }

    /**
     * Scope a query to only include bundle vehicles for a specific bundle.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $bundleId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByBundle($query, $bundleId)
    {
        return $query->where('bundle_id', $bundleId);
    }

    /**
     * Scope a query to only include bundle vehicles for a specific vehicle body type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $vehicleBodyTypeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByVehicleBodyType($query, $vehicleBodyTypeId)
    {
        return $query->where('vehicle_body_type_id', $vehicleBodyTypeId);
    }

    /**
     * Check if this bundle vehicle allows unlimited count.
     *
     * @return bool
     */
    public function hasUnlimitedCount()
    {
        return is_null($this->max_count) || $this->max_count <= 0;
    }
}
