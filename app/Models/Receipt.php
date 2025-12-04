<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Receipt extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'receipt_number',
        'vehicle_passage_id',
        'invoice_id',
        'amount',
        'payment_method',
        'issued_by',
        'issued_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['receipt_number', 'vehicle_passage_id', 'invoice_id', 'amount', 'payment_method', 'issued_by', 'issued_at', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the vehicle passage that owns this receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vehiclePassage()
    {
        return $this->belongsTo(VehiclePassage::class);
    }

    /**
     * Get the invoice that owns this receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user that issued this receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Scope a query to only include receipts by receipt number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $receiptNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByReceiptNumber($query, $receiptNumber)
    {
        return $query->where('receipt_number', $receiptNumber);
    }

    /**
     * Scope a query to only include receipts by payment method.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $paymentMethod
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPaymentMethod($query, $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    /**
     * Scope a query to only include receipts within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('issued_at', [$startDate, $endDate]);
    }

    /**
     * Check if the receipt is for a vehicle passage.
     *
     * @return bool
     */
    public function isForVehiclePassage()
    {
        return !is_null($this->vehicle_passage_id);
    }

    /**
     * Check if the receipt is for an invoice.
     *
     * @return bool
     */
    public function isForInvoice()
    {
        return !is_null($this->invoice_id);
    }
}
