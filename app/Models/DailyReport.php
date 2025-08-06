<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DailyReport extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'report_date',
        'station_id',
        'total_passages',
        'total_revenue',
        'cash_payments',
        'card_payments',
        'account_payments',
        'bundle_passages',
        'exempted_passages',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'total_passages' => 'integer',
        'total_revenue' => 'decimal:2',
        'cash_payments' => 'decimal:2',
        'card_payments' => 'decimal:2',
        'account_payments' => 'decimal:2',
        'bundle_passages' => 'integer',
        'exempted_passages' => 'integer',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['report_date', 'station_id', 'total_passages', 'total_revenue', 'cash_payments', 'card_payments', 'account_payments', 'bundle_passages', 'exempted_passages', 'generated_by', 'generated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the station that owns this daily report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * Get the user that generated this daily report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Scope a query to only include reports by date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDate($query, $date)
    {
        return $query->where('report_date', $date);
    }

    /**
     * Scope a query to only include reports by station.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $stationId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStation($query, $stationId)
    {
        return $query->where('station_id', $stationId);
    }

    /**
     * Scope a query to only include reports within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('report_date', [$startDate, $endDate]);
    }

    /**
     * Get the total non-bundle passages.
     *
     * @return int
     */
    public function getNonBundlePassagesAttribute()
    {
        return $this->total_passages - $this->bundle_passages;
    }

    /**
     * Get the total non-exempted passages.
     *
     * @return int
     */
    public function getNonExemptedPassagesAttribute()
    {
        return $this->total_passages - $this->exempted_passages;
    }

    /**
     * Get the average revenue per passage.
     *
     * @return float
     */
    public function getAverageRevenuePerPassageAttribute()
    {
        if ($this->total_passages == 0) {
            return 0;
        }

        return $this->total_revenue / $this->total_passages;
    }

    /**
     * Get the payment method breakdown as percentage.
     *
     * @return array
     */
    public function getPaymentMethodBreakdownAttribute()
    {
        if ($this->total_revenue == 0) {
            return [
                'cash' => 0,
                'card' => 0,
                'account' => 0,
            ];
        }

        return [
            'cash' => ($this->cash_payments / $this->total_revenue) * 100,
            'card' => ($this->card_payments / $this->total_revenue) * 100,
            'account' => ($this->account_payments / $this->total_revenue) * 100,
        ];
    }
}
