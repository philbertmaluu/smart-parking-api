<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BundleType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'duration_days',
        'description',
        'is_active',
    ];

    protected $casts = [
        'duration_days' => 'integer',
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
            ->logOnly(['name', 'duration_days', 'description', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get all bundles of this type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bundles()
    {
        return $this->hasMany(Bundle::class);
    }

    /**
     * Scope a query to only include active bundle types.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include bundle types by duration.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $durationDays
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDuration($query, $durationDays)
    {
        return $query->where('duration_days', $durationDays);
    }

    /**
     * Get the duration description (e.g., "Daily", "Weekly", "Monthly", "Yearly").
     *
     * @return string
     */
    public function getDurationDescriptionAttribute()
    {
        return match ($this->duration_days) {
            1 => 'Daily',
            7 => 'Weekly',
            30 => 'Monthly',
            365 => 'Yearly',
            default => $this->duration_days . ' Days'
        };
    }
}
