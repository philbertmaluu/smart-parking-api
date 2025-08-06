<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'body_type_id',
        'plate_number',
        'make',
        'model',
        'year',
        'color',
        'owner_name',
        'is_registered',
    ];

    protected $casts = [
        'year' => 'integer',
        'is_registered' => 'boolean',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['body_type_id', 'plate_number', 'make', 'model', 'year', 'color', 'owner_name', 'is_registered'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the vehicle body type that owns this vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bodyType()
    {
        return $this->belongsTo(VehicleBodyType::class, 'body_type_id');
    }

    /**
     * Get all account vehicles associated with this vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accountVehicles()
    {
        return $this->hasMany(AccountVehicle::class);
    }

    /**
     * Get all vehicle passages for this vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehiclePassages()
    {
        return $this->hasMany(VehiclePassage::class);
    }

    /**
     * Get all accounts that have registered this vehicle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_vehicles')
            ->withPivot('is_primary', 'registered_at')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include registered vehicles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRegistered($query)
    {
        return $query->where('is_registered', true);
    }

    /**
     * Scope a query to only include vehicles of a specific body type.
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
     * Scope a query to only include vehicles by make.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $make
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMake($query, $make)
    {
        return $query->where('make', $make);
    }

    /**
     * Scope a query to only include vehicles by model.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByModel($query, $model)
    {
        return $query->where('model', $model);
    }

    /**
     * Get the full vehicle name (make + model + year).
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        $parts = array_filter([$this->make, $this->model, $this->year]);
        return implode(' ', $parts);
    }
}
