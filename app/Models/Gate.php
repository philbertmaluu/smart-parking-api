<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Gate extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'station_id',
        'name',
        'gate_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['station_id', 'name', 'gate_type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the station that owns this gate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * Get all vehicle passages that entered through this gate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehiclePassagesAsEntry()
    {
        return $this->hasMany(VehiclePassage::class, 'entry_gate_id');
    }

    /**
     * Get all vehicle passages that exited through this gate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehiclePassagesAsExit()
    {
        return $this->hasMany(VehiclePassage::class, 'exit_gate_id');
    }

    /**
     * Scope a query to only include active gates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include gates of a specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('gate_type', $type);
    }

    /**
     * Scope a query to only include gates for a specific station.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $stationId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStation($query, $stationId)
    {
        return $query->where('station_id', $stationId);
    }
}
