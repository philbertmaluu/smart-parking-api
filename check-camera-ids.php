<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Gate;
use App\Models\GateDevice;

echo "=== Checking Camera Device IDs ===\n\n";

$gates = Gate::where('is_active', true)
    ->whereHas('devices', function ($query) {
        $query->where('device_type', 'camera')
            ->where('status', 'active');
    })
    ->with(['devices' => function ($query) {
        $query->where('device_type', 'camera')
            ->where('status', 'active');
    }])
    ->get();

foreach ($gates as $gate) {
    echo "Gate: {$gate->name} (ID: {$gate->id})\n";
    foreach ($gate->devices as $device) {
        if ($device->device_type === 'camera' && $device->status === 'active') {
            echo "  Camera Device:\n";
            echo "    IP: {$device->ip_address}\n";
            echo "    Device ID: " . ($device->device_id ?? 'NULL') . "\n";
            echo "    Gate ID: {$device->gate_id}\n";
            echo "    Status: {$device->status}\n";
            
            // Check if device_id is set
            if (empty($device->device_id)) {
                echo "    ⚠ WARNING: device_id is empty! This will cause issues.\n";
                echo "    Setting device_id to 1 (default)...\n";
                $device->device_id = 1;
                $device->save();
                echo "    ✓ Updated to device_id = 1\n";
            }
            echo "\n";
        }
    }
}

// Check for new detections
echo "=== Checking for new camera detections ===\n";
$newIds = [50, 51, 52, 53];
foreach ($newIds as $id) {
    $exists = \App\Models\CameraDetectionLog::where('camera_detection_id', $id)->exists();
    echo "Camera Detection ID {$id}: " . ($exists ? 'EXISTS (duplicate)' : 'NEW (should be stored)') . "\n";
}

