<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gate_devices', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to gates table
            $table->foreignId('gate_id')->constrained('gates')->onDelete('cascade');
            
            // Device type: camera or boom gate
            $table->enum('device_type', ['camera', 'boom_gate'])->default('camera');
            
            // Device identification
            $table->string('name')->nullable(); // Device name/identifier (e.g., "Entry Camera 1", "Exit Boom Gate")
            $table->string('device_id')->nullable(); // e.g., "ZKTeco", "Hikvision", "Axis"
            $table->string('serial_number')->nullable(); // Device serial number
            $table->string('mac_address')->nullable(); // MAC address for network identification
            
            // Network configuration
            $table->string('ip_address'); // IP address (e.g., "192.168.0.109")
            $table->integer('http_port')->default(80); // HTTP port for web interface/API
            $table->integer('rtsp_port')->nullable()->default(554); // RTSP port for video streaming (mainly cameras)
            $table->boolean('use_https')->default(false); // Whether to use HTTPS
            $table->string('subnet_mask')->nullable(); // Network subnet mask
            $table->string('gateway')->nullable(); // Default gateway
            $table->string('dns_server')->nullable(); // DNS server
            
            // Authentication credentials
            $table->string('username'); // Username for device authentication
            $table->string('password'); // Password (should be encrypted in application layer)
            
            // Device location/position
            $table->enum('direction', ['entry', 'exit', 'both'])->default('both'); // Direction (e.g., "incoming", "outgoing", "both")
            
            // Device status and monitoring
            $table->enum('status', ['active', 'inactive', 'maintenance', 'error'])->default('active');
            $table->boolean('is_online')->default(false); // Current online status
            $table->timestamp('last_connected_at')->nullable(); // Last successful connection
            $table->timestamp('last_ping_at')->nullable(); // Last ping/health check
            $table->integer('connection_timeout')->default(30); // Connection timeout in seconds
            $table->integer('ping_interval')->default(60); // Ping interval in seconds for health checks
            
            
            // Device capabilities and features
            $table->boolean('supports_rtsp')->default(false); // Whether device supports RTSP streaming
            $table->boolean('supports_snapshot')->default(false); // Whether device supports snapshot capture
            $table->boolean('supports_motion_detection')->default(false); // Motion detection capability
            $table->boolean('supports_audio')->default(false); // Audio support
            $table->boolean('supports_ptz')->default(false); // Pan-Tilt-Zoom support (for cameras)
            
            // Boom gate specific fields
            $table->integer('open_duration')->nullable(); // Duration in seconds for boom gate to stay open
            $table->integer('close_duration')->nullable(); // Duration in seconds for boom gate to close
            $table->boolean('auto_close')->default(true); // Whether boom gate auto-closes after opening
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for better query performance
            $table->index('gate_id');
            $table->index('device_type');
            $table->index('status');
            $table->index('is_online');
            $table->index('ip_address');
            $table->index(['gate_id', 'device_type']); // Composite index for common queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_devices');
    }
};
