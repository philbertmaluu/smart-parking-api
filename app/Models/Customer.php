<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'user_id',
        'customer_number',
        'name',
        'company_name',
        'customer_type',
    ];

    protected $casts = [
        'customer_type' => 'string',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'customer_number', 'name', 'company_name', 'customer_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the user that owns this customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all accounts that belong to this customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Scope a query to only include individual customers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIndividual($query)
    {
        return $query->where('customer_type', 'individual');
    }

    /**
     * Scope a query to only include corporate customers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCorporate($query)
    {
        return $query->where('customer_type', 'corporate');
    }

    /**
     * Scope a query to only include customers by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('customer_type', $type);
    }

    /**
     * Scope a query to only include customers by customer number.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $customerNumber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCustomerNumber($query, $customerNumber)
    {
        return $query->where('customer_number', $customerNumber);
    }

    /**
     * Check if the customer is an individual.
     *
     * @return bool
     */
    public function isIndividual()
    {
        return $this->customer_type === 'individual';
    }

    /**
     * Check if the customer is a corporate.
     *
     * @return bool
     */
    public function isCorporate()
    {
        return $this->customer_type === 'corporate';
    }

    /**
     * Get the display name (company name for corporate, name for individual).
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->isCorporate() ? $this->company_name : $this->name;
    }
}
