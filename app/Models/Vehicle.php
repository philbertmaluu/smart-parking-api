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
        'paid_until',
        'is_exempted',
        'exemption_reason',
        'exemption_expires_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'is_registered' => 'boolean',
        'paid_until' => 'datetime',
        'is_exempted' => 'boolean',
        'exemption_expires_at' => 'datetime',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['body_type_id', 'plate_number', 'make', 'model', 'year', 'color', 'owner_name', 'is_registered', 'is_exempted', 'exemption_reason', 'exemption_expires_at'])
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

    /**
     * Scope a query to only include exempted vehicles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExempted($query)
    {
        return $query->where('is_exempted', true);
    }

    /**
     * Scope a query to only include non-exempted vehicles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNonExempted($query)
    {
        return $query->where('is_exempted', false);
    }

    /**
     * Check if the vehicle is currently exempted.
     *
     * @return bool
     */
    public function isCurrentlyExempted(): bool
    {
        if (!$this->is_exempted) {
            return false;
        }

        // If no expiration date, exemption is permanent
        if (is_null($this->exemption_expires_at)) {
            return true;
        }

        // Check if exemption has expired
        return $this->exemption_expires_at->isFuture();
    }

    /**
     * Set vehicle exemption.
     *
     * @param string $reason
     * @param \Carbon\Carbon|null $expiresAt
     * @return bool
     */
    public function setExemption(string $reason, $expiresAt = null): bool
    {
        return $this->update([
            'is_exempted' => true,
            'exemption_reason' => $reason,
            'exemption_expires_at' => $expiresAt,
        ]);
    }

    /**
     * Remove vehicle exemption.
     *
     * @return bool
     */
    public function removeExemption(): bool
    {
        return $this->update([
            'is_exempted' => false,
            'exemption_reason' => null,
            'exemption_expires_at' => null,
        ]);
    }
}
