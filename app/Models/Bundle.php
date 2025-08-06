<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Bundle extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'bundle_type_id',
        'name',
        'amount',
        'max_vehicles',
        'max_passages',
        'description',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'max_vehicles' => 'integer',
        'max_passages' => 'integer',
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
            ->logOnly(['bundle_type_id', 'name', 'amount', 'max_vehicles', 'max_passages', 'description', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the bundle type that owns this bundle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bundleType()
    {
        return $this->belongsTo(BundleType::class);
    }

    /**
     * Get all bundle vehicles associated with this bundle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bundleVehicles()
    {
        return $this->hasMany(BundleVehicle::class);
    }

    /**
     * Get all bundle subscriptions for this bundle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bundleSubscriptions()
    {
        return $this->hasMany(BundleSubscription::class);
    }

    /**
     * Get all vehicle body types allowed in this bundle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function vehicleBodyTypes()
    {
        return $this->belongsToMany(VehicleBodyType::class, 'bundle_vehicles', 'bundle_id', 'vehicle_body_type_id')
            ->withPivot('max_count')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active bundles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include bundles by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $bundleTypeId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $bundleTypeId)
    {
        return $query->where('bundle_type_id', $bundleTypeId);
    }

    /**
     * Scope a query to only include bundles within a price range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $minAmount
     * @param float $maxAmount
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPriceRange($query, $minAmount, $maxAmount)
    {
        return $query->whereBetween('amount', [$minAmount, $maxAmount]);
    }

    /**
     * Check if the bundle allows unlimited passages.
     *
     * @return bool
     */
    public function hasUnlimitedPassages()
    {
        return is_null($this->max_passages);
    }

    /**
     * Check if the bundle allows unlimited vehicles.
     *
     * @return bool
     */
    public function hasUnlimitedVehicles()
    {
        return is_null($this->max_vehicles);
    }
}
