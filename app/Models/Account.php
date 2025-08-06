<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Account extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'customer_id',
        'account_number',
        'name',
        'account_type',
        'balance',
        'credit_limit',
        'is_active',
    ];

    protected $casts = [
        'account_type' => 'string',
        'balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
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
            ->logOnly(['customer_id', 'account_number', 'name', 'account_type', 'balance', 'credit_limit', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the customer that owns this account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all account vehicles associated with this account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accountVehicles()
    {
        return $this->hasMany(AccountVehicle::class);
    }

    /**
     * Get all bundle subscriptions for this account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bundleSubscriptions()
    {
        return $this->hasMany(BundleSubscription::class);
    }

    /**
     * Get all vehicle passages for this account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vehiclePassages()
    {
        return $this->hasMany(VehiclePassage::class);
    }

    /**
     * Get all transactions for this account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all invoices for this account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all vehicles registered to this account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'account_vehicles')
            ->withPivot('is_primary', 'registered_at')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include active accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include prepaid accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePrepaid($query)
    {
        return $query->where('account_type', 'prepaid');
    }

    /**
     * Scope a query to only include postpaid accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePostpaid($query)
    {
        return $query->where('account_type', 'postpaid');
    }

    /**
     * Scope a query to only include accounts by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope a query to only include accounts by account number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $accountNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAccountNumber($query, $accountNumber)
    {
        return $query->where('account_number', $accountNumber);
    }

    /**
     * Check if the account is prepaid.
     *
     * @return bool
     */
    public function isPrepaid()
    {
        return $this->account_type === 'prepaid';
    }

    /**
     * Check if the account is postpaid.
     *
     * @return bool
     */
    public function isPostpaid()
    {
        return $this->account_type === 'postpaid';
    }

    /**
     * Check if the account has sufficient balance for a transaction.
     *
     * @param float $amount
     * @return bool
     */
    public function hasSufficientBalance($amount)
    {
        if ($this->isPrepaid()) {
            return $this->balance >= $amount;
        }

        return ($this->balance + $this->credit_limit) >= $amount;
    }

    /**
     * Get the available credit (balance + credit limit).
     *
     * @return float
     */
    public function getAvailableCreditAttribute()
    {
        return $this->balance + $this->credit_limit;
    }
}
