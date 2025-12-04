<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Station extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'location',
        'code',
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
            ->logOnly(['name', 'location', 'code', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get all gates that belong to this station.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function gates()
    {
        return $this->hasMany(Gate::class);
    }

    /**
     * Get all vehicle body type prices for this station.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehicleBodyTypePrices()
    {
        return $this->hasMany(VehicleBodyTypePrice::class);
    }

    /**
     * Get all vehicle passages that entered through this station.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehiclePassagesAsEntry()
    {
        return $this->hasMany(VehiclePassage::class, 'entry_station_id');
    }

    /**
     * Get all vehicle passages that exited through this station.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehiclePassagesAsExit()
    {
        return $this->hasMany(VehiclePassage::class, 'exit_station_id');
    }

    /**
     * Get all daily reports for this station.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function dailyReports()
    {
        return $this->hasMany(DailyReport::class);
    }

    /**
     * Get all operators assigned to this station.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function operators()
    {
        return $this->belongsToMany(User::class, 'operator_station')
            ->withPivot('is_active', 'assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active stations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include stations with a specific code.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $code
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }
}
