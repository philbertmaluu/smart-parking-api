<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'invoice_number',
        'account_id',
        'bundle_subscription_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'status' => 'string',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['invoice_number', 'account_id', 'bundle_subscription_id', 'invoice_date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'paid_amount', 'status', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the account that owns this invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the bundle subscription that owns this invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bundleSubscription()
    {
        return $this->belongsTo(BundleSubscription::class);
    }

    /**
     * Get all receipts for this invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Scope a query to only include pending invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include paid invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope a query to only include overdue invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope a query to only include cancelled invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include invoices by status.
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
     * Scope a query to only include invoices by invoice number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $invoiceNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByInvoiceNumber($query, $invoiceNumber)
    {
        return $query->where('invoice_number', $invoiceNumber);
    }

    /**
     * Scope a query to only include invoices within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include overdue invoices (due date passed).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdueByDate($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
            ->whereIn('status', ['pending', 'overdue']);
    }

    /**
     * Check if the invoice is pending.
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the invoice is paid.
     *
     * @return bool
     */
    public function isPaid()
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the invoice is overdue.
     *
     * @return bool
     */
    public function isOverdue()
    {
        return $this->status === 'overdue' || ($this->due_date < now()->toDateString() && $this->status === 'pending');
    }

    /**
     * Check if the invoice is cancelled.
     *
     * @return bool
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the invoice is fully paid.
     *
     * @return bool
     */
    public function isFullyPaid()
    {
        return $this->paid_amount >= $this->total_amount;
    }

    /**
     * Get the outstanding amount (total - paid).
     *
     * @return float
     */
    public function getOutstandingAmountAttribute()
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    /**
     * Get the payment percentage.
     *
     * @return float
     */
    public function getPaymentPercentageAttribute()
    {
        if ($this->total_amount == 0) {
            return 0;
        }

        return ($this->paid_amount / $this->total_amount) * 100;
    }

    /**
     * Get the days overdue.
     *
     * @return int
     */
    public function getDaysOverdueAttribute()
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return now()->diffInDays($this->due_date);
    }
}
