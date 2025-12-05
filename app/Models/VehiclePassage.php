<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehiclePassage extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'passage_number',
        'vehicle_id',
        'account_id',
        'bundle_subscription_id',
        'payment_type_id',
        'entry_time',
        'entry_operator_id',
        'entry_gate_id',
        'entry_station_id',
        'exit_time',
        'exit_operator_id',
        'exit_gate_id',
        'exit_station_id',
        'base_amount',
        'discount_amount',
        'total_amount',
        'is_paid',
        'paid_at',
        'passage_type',
        'is_exempted',
        'exemption_reason',
        'status',
        'duration_minutes',
        'notes',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'paid_at' => 'datetime',
        'base_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'passage_type' => 'string',
        'is_exempted' => 'boolean',
        'is_paid' => 'boolean',
        'status' => 'string',
        'duration_minutes' => 'integer',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['passage_number', 'vehicle_id', 'account_id', 'bundle_subscription_id', 'payment_type_id', 'entry_time', 'exit_time', 'base_amount', 'discount_amount', 'total_amount', 'passage_type', 'is_exempted', 'exemption_reason', 'status', 'duration_minutes', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the vehicle that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the account that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the bundle subscription that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bundleSubscription()
    {
        return $this->belongsTo(BundleSubscription::class);
    }

    /**
     * Get the payment type that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentType()
    {
        return $this->belongsTo(PaymentType::class);
    }

    /**
     * Get the entry operator that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entryOperator()
    {
        return $this->belongsTo(User::class, 'entry_operator_id');
    }

    /**
     * Get the exit operator that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function exitOperator()
    {
        return $this->belongsTo(User::class, 'exit_operator_id');
    }

    /**
     * Get the entry gate that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entryGate()
    {
        return $this->belongsTo(Gate::class, 'entry_gate_id');
    }

    /**
     * Get the exit gate that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function exitGate()
    {
        return $this->belongsTo(Gate::class, 'exit_gate_id');
    }

    /**
     * Get the entry station that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entryStation()
    {
        return $this->belongsTo(Station::class, 'entry_station_id');
    }

    /**
     * Get the exit station that owns this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function exitStation()
    {
        return $this->belongsTo(Station::class, 'exit_station_id');
    }

    /**
     * Get all transactions for this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all receipts for this vehicle passage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Scope a query to only include active passages.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include completed passages (with exit time).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('exit_time');
    }

    /**
     * Scope a query to only include passages by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('passage_type', $type);
    }

    /**
     * Scope a query to only include passages by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include passages by passage number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $passageNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPassageNumber($query, $passageNumber)
    {
        return $query->where('passage_number', $passageNumber);
    }

    /**
     * Scope a query to only include passages within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('entry_time', [$startDate, $endDate]);
    }

    /**
     * Check if the passage is completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return !is_null($this->exit_time);
    }

    /**
     * Check if the passage is exempted.
     *
     * @return bool
     */
    public function isExempted()
    {
        return $this->is_exempted;
    }

    /**
     * Check if the passage is free.
     *
     * @return bool
     */
    public function isFree()
    {
        return $this->passage_type === 'free';
    }

    /**
     * Check if the passage is a toll passage.
     *
     * @return bool
     */
    public function isToll()
    {
        return $this->passage_type === 'toll';
    }

    /**
     * Check if the passage is paid.
     *
     * @return bool
     */
    public function isPaid()
    {
        return $this->is_paid;
    }

    /**
     * Check if the passage is a same-day re-entry (free).
     *
     * @return bool
     */
    public function isReentry()
    {
        return $this->passage_type === 'reentry';
    }

    /**
     * Calculate the duration in minutes.
     *
     * @return int|null
     */
    public function calculateDuration()
    {
        if (!$this->isCompleted()) {
            return null;
        }

        return $this->entry_time->diffInMinutes($this->exit_time);
    }

    /**
     * Get the net amount (total - discount).
     *
     * @return float
     */
    public function getNetAmountAttribute()
    {
        return $this->total_amount - $this->discount_amount;
    }
}
