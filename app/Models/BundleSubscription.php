<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BundleSubscription extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'subscription_number',
        'account_id',
        'bundle_id',
        'start_datetime',
        'end_datetime',
        'amount',
        'passages_used',
        'status',
        'auto_renew',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'amount' => 'decimal:2',
        'passages_used' => 'integer',
        'status' => 'string',
        'auto_renew' => 'boolean',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subscription_number', 'account_id', 'bundle_id', 'start_datetime', 'end_datetime', 'amount', 'passages_used', 'status', 'auto_renew'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the account that owns this bundle subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the bundle that owns this bundle subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * Get all vehicle passages that used this bundle subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehiclePassages()
    {
        return $this->hasMany(VehiclePassage::class);
    }

    /**
     * Get all transactions for this bundle subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all invoices for this bundle subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope a query to only include active subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include pending subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include expired subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope a query to only include subscriptions by status.
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
     * Scope a query to only include subscriptions by subscription number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $subscriptionNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySubscriptionNumber($query, $subscriptionNumber)
    {
        return $query->where('subscription_number', $subscriptionNumber);
    }

    /**
     * Scope a query to only include current subscriptions (not expired).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        return $query->where('end_datetime', '>', now());
    }

    /**
     * Check if the subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if the subscription is expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->status === 'expired' || $this->end_datetime < now();
    }

    /**
     * Check if the subscription has unlimited passages.
     *
     * @return bool
     */
    public function hasUnlimitedPassages()
    {
        return $this->bundle->hasUnlimitedPassages();
    }

    /**
     * Check if the subscription has remaining passages.
     *
     * @return bool
     */
    public function hasRemainingPassages()
    {
        if ($this->hasUnlimitedPassages()) {
            return true;
        }

        return $this->passages_used < $this->bundle->max_passages;
    }

    /**
     * Get the remaining passages count.
     *
     * @return int|null
     */
    public function getRemainingPassagesAttribute()
    {
        if ($this->hasUnlimitedPassages()) {
            return null;
        }

        return max(0, $this->bundle->max_passages - $this->passages_used);
    }

    /**
     * Get the subscription duration in days.
     *
     * @return int
     */
    public function getDurationDaysAttribute()
    {
        return $this->start_datetime->diffInDays($this->end_datetime);
    }
}
