<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleBodyTypePrice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'body_type_id',
        'station_id',
        'base_price',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
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
            ->logOnly(['body_type_id', 'station_id', 'base_price', 'effective_from', 'effective_to', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the vehicle body type that owns this price.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bodyType()
    {
        return $this->belongsTo(VehicleBodyType::class, 'body_type_id');
    }

    /**
     * Get the station that owns this price.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * Scope a query to only include active prices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include prices for a specific body type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $bodyTypeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByBodyType($query, $bodyTypeId)
    {
        return $query->where('body_type_id', $bodyTypeId);
    }

    /**
     * Scope a query to only include prices for a specific station.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $stationId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStation($query, $stationId)
    {
        return $query->where('station_id', $stationId);
    }

    /**
     * Scope a query to only include currently effective prices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEffective($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }
}
