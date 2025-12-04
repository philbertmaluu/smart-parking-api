<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SystemSetting extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'data_type',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'data_type' => 'string',
    ];

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['setting_key', 'setting_value', 'data_type', 'description', 'updated_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the user that updated this system setting.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include settings by key.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('setting_key', $key);
    }

    /**
     * Scope a query to only include settings by data type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $dataType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDataType($query, $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    /**
     * Get the setting value cast to the appropriate data type.
     *
     * @return mixed
     */
    public function getTypedValueAttribute()
    {
        return match ($this->data_type) {
            'integer' => (int) $this->setting_value,
            'decimal' => (float) $this->setting_value,
            'boolean' => filter_var($this->setting_value, FILTER_VALIDATE_BOOLEAN),
            'array' => json_decode($this->setting_value, true),
            'json' => json_decode($this->setting_value, true),
            default => $this->setting_value,
        };
    }

    /**
     * Set the setting value with proper type casting.
     *
     * @param mixed $value
     * @return void
     */
    public function setTypedValue($value)
    {
        $this->setting_value = match ($this->data_type) {
            'array', 'json' => json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    /**
     * Check if the setting is a boolean type.
     *
     * @return bool
     */
    public function isBoolean()
    {
        return $this->data_type === 'boolean';
    }

    /**
     * Check if the setting is a numeric type.
     *
     * @return bool
     */
    public function isNumeric()
    {
        return in_array($this->data_type, ['integer', 'decimal']);
    }

    /**
     * Check if the setting is a JSON/array type.
     *
     * @return bool
     */
    public function isJson()
    {
        return in_array($this->data_type, ['array', 'json']);
    }
}
