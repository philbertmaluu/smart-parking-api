<?php

namespace App\Models;

use Illuminate\Container\Attributes\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class GateDevice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gate_id',
        'device_type',
        'name',
        'device_id',
        'serial_number',
        'mac_address',
        'ip_address',
        'http_port',
        'rtsp_port',
        'use_https',
        'subnet_mask',
        'gateway',
        'dns_server',
        'username',
        'password',
        'direction',
        'status',
        'is_online',
        'last_connected_at',
        'last_ping_at',
        'connection_timeout',
        'ping_interval',
        'supports_rtsp',
        'supports_snapshot',
        'supports_motion_detection',
        'supports_audio',
        'supports_ptz',
        'open_duration',
        'close_duration',
        'auto_close',
    ];

    protected $casts = [
        'use_https' => 'boolean',
        'is_online' => 'boolean',
        'supports_rtsp' => 'boolean',
        'supports_snapshot' => 'boolean',
        'supports_motion_detection' => 'boolean',
        'supports_audio' => 'boolean',
        'supports_ptz' => 'boolean',
        'auto_close' => 'boolean',
        'last_connected_at' => 'datetime',
        'last_ping_at' => 'datetime',
        'http_port' => 'integer',
        'rtsp_port' => 'integer',
        'connection_timeout' => 'integer',
        'ping_interval' => 'integer',
        'open_duration' => 'integer',
        'close_duration' => 'integer',
    ];

    /**
     * Get the gate that owns this device.
     */
    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    /**
     * Scope a query to only include active devices.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include devices by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Scope a query to only include devices for a specific gate.
     */
    public function scopeByGate($query, int $gateId)
    {
        return $query->where('gate_id', $gateId);
    }

    /**
     * Scope a query to only include online devices.
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Automatically encrypt password when setting
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (!$value) {
                    return null;
                }
                
                // Check if encryption key is available
                if (empty(config('app.key'))) {
                    throw new \RuntimeException('Application encryption key is not set. Please run: php artisan key:generate');
                }
                
                try {
                    return encrypt($value);
                } catch (\Exception $e) {
                    // If encryption fails, store as plain text (not recommended but allows operation)
                    return $value;
                }
            },
            get: function ($value) {
                if (!$value) {
                    return null;
                }
                
                try {
                    // Try to decrypt - if it's already decrypted or fails, return as-is
                    return decrypt($value);
                } catch (\Exception $e) {
                    // If decryption fails, assume it's already plain text or encrypted with different key
                    return $value;
                }
            },
        );
    }

    /**
     * Get the camera configuration in a format suitable for the frontend.
     */
    public function getCameraConfig()
    {
        return [
            'ip' => $this->ip_address,
            'httpPort' => $this->http_port,
            'rtspPort' => $this->rtsp_port,
            'username' => $this->username,
            'password' => $this->password,
            'useHttps' => $this->use_https,
            'name' => $this->name,
            'deviceId' => $this->device_id,
            'supportsRtsp' => $this->supports_rtsp,
            'supportsSnapshot' => $this->supports_snapshot,
        ];
    }
}
