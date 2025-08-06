<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserHasPermission extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'permission_id',
        'assigned_at',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'permission_id', 'assigned_at', 'assigned_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the user that owns this permission assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the permission that owns this assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Get the user that assigned this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope a query to only include assignments by user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include assignments by permission.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $permissionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPermission($query, $permissionId)
    {
        return $query->where('permission_id', $permissionId);
    }

    /**
     * Scope a query to only include assignments by assigned by user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $assignedByUserId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAssignedBy($query, $assignedByUserId)
    {
        return $query->where('assigned_by', $assignedByUserId);
    }

    /**
     * Scope a query to only include assignments within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('assigned_at', [$startDate, $endDate]);
    }

    /**
     * Check if the assignment is recent (within the last 30 days).
     *
     * @return bool
     */
    public function isRecent()
    {
        return $this->assigned_at->isAfter(now()->subDays(30));
    }

    /**
     * Get the assignment duration in days.
     *
     * @return int
     */
    public function getAssignmentDurationDaysAttribute()
    {
        return $this->assigned_at->diffInDays(now());
    }
}
