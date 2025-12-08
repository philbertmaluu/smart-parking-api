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
        'last_preview_path',
        'last_preview_at',
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
        'last_preview_at' => 'datetime',
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
     * Encode password when setting (using base64 for portability across PCs)
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (!$value) {
                    return null;
                }
                
                // Use base64 encoding for portability (works without APP_KEY)
                return base64_encode($value);
            },
            get: function ($value) {
                if (!$value) {
                    return null;
                }
                
                // Check if it's base64 encoded
                $decoded = base64_decode($value, true);
                if ($decoded !== false && base64_encode($decoded) === $value) {
                    return $decoded;
                }
                
                // If not base64, try to decrypt (for backwards compatibility with old encrypted values)
                try {
                    return decrypt($value);
                } catch (\Exception $e) {
                    // Return as-is if neither works
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
