<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Transaction extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'transaction_number',
        'account_id',
        'vehicle_passage_id',
        'bundle_subscription_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'payment_method',
        'reference_number',
        'description',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_type' => 'string',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['transaction_number', 'account_id', 'vehicle_passage_id', 'bundle_subscription_id', 'transaction_type', 'amount', 'balance_before', 'balance_after', 'payment_method', 'reference_number', 'description', 'processed_by', 'processed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the account that owns this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the vehicle passage that owns this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehiclePassage()
    {
        return $this->belongsTo(VehiclePassage::class);
    }

    /**
     * Get the bundle subscription that owns this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bundleSubscription()
    {
        return $this->belongsTo(BundleSubscription::class);
    }

    /**
     * Get the user that processed this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope a query to only include debit transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDebit($query)
    {
        return $query->where('transaction_type', 'debit');
    }

    /**
     * Scope a query to only include credit transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCredit($query)
    {
        return $query->where('transaction_type', 'credit');
    }

    /**
     * Scope a query to only include refund transactions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRefund($query)
    {
        return $query->where('transaction_type', 'refund');
    }

    /**
     * Scope a query to only include transactions by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope a query to only include transactions by transaction number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $transactionNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTransactionNumber($query, $transactionNumber)
    {
        return $query->where('transaction_number', $transactionNumber);
    }

    /**
     * Scope a query to only include transactions within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('processed_at', [$startDate, $endDate]);
    }

    /**
     * Check if the transaction is a debit.
     *
     * @return bool
     */
    public function isDebit()
    {
        return $this->transaction_type === 'debit';
    }

    /**
     * Check if the transaction is a credit.
     *
     * @return bool
     */
    public function isCredit()
    {
        return $this->transaction_type === 'credit';
    }

    /**
     * Check if the transaction is a refund.
     *
     * @return bool
     */
    public function isRefund()
    {
        return $this->transaction_type === 'refund';
    }

    /**
     * Get the transaction amount with sign (negative for debit/refund, positive for credit).
     *
     * @return float
     */
    public function getSignedAmountAttribute()
    {
        if ($this->isDebit() || $this->isRefund()) {
            return -$this->amount;
        }

        return $this->amount;
    }

    /**
     * Get the balance change (balance_after - balance_before).
     *
     * @return float
     */
    public function getBalanceChangeAttribute()
    {
        return $this->balance_after - $this->balance_before;
    }
}
