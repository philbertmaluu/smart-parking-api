<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Gate;
use App\Models\GateDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "=== Debugging Camera Fetch ===\n\n";

// Get gate 2's camera
$gate = Gate::find(2);
if (!$gate) {
    echo "Gate 2 not found\n";
    exit;
}

$cameraDevice = $gate->devices()
    ->where('device_type', 'camera')
    ->where('status', 'active')
    ->first();

if (!$cameraDevice) {
    echo "No active camera found for gate 2\n";
    exit;
}

echo "Camera: {$cameraDevice->ip_address}\n";
echo "Device ID: {$cameraDevice->device_id}\n\n";

// Fetch from camera
$cameraIp = $cameraDevice->ip_address;
$computerId = (int) $cameraDevice->device_id;
$timestamp = time() * 1000;
$dateParam = date('Y-m-d\TH:i:s.000');

$url = sprintf(
    'http://%s/edge/cgi-bin/vparcgi.cgi?computerid=%d&oper=jsonlastresults&dd=%s&_=%d',
    $cameraIp,
    $computerId,
    $dateParam,
    $timestamp
);

echo "Fetching from: {$url}\n\n";

try {
    $response = Http::timeout(10)->get($url);
    
    if (!$response->successful()) {
        echo "Error: HTTP {$response->status()}\n";
        echo "Response: {$response->body()}\n";
        exit;
    }
    
    $data = $response->json();
    
    if (!isset($data['data']) || !is_array($data['data'])) {
        echo "Invalid response format\n";
        echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        exit;
    }
    
    $detections = $data['data'];
    echo "Fetched " . count($detections) . " detections from camera\n\n";
    
    // Check which ones are duplicates
    echo "Checking for duplicates...\n";
    $newCount = 0;
    $duplicateCount = 0;
    
    foreach ($detections as $det) {
        $cameraDetectionId = $det['id'] ?? null;
        $plate = $det['numberplate'] ?? 'N/A';
        $timestamp = $det['timestamp'] ?? 'N/A';
        
        // Check if exists in database
        $exists = \App\Models\CameraDetectionLog::where('camera_detection_id', $cameraDetectionId)->exists();
        
        if ($exists) {
            $duplicateCount++;
            echo "  [DUPLICATE] ID: {$cameraDetectionId}, Plate: {$plate}, Time: {$timestamp}\n";
        } else {
            $newCount++;
            echo "  [NEW] ID: {$cameraDetectionId}, Plate: {$plate}, Time: {$timestamp}\n";
        }
    }
    
    echo "\nSummary:\n";
    echo "  Total: " . count($detections) . "\n";
    echo "  New: {$newCount}\n";
    echo "  Duplicates: {$duplicateCount}\n";
    
    // Show most recent detection timestamp
    if (!empty($detections)) {
        $mostRecent = $detections[0];
        echo "\nMost recent detection:\n";
        echo "  ID: " . ($mostRecent['id'] ?? 'N/A') . "\n";
        echo "  Plate: " . ($mostRecent['numberplate'] ?? 'N/A') . "\n";
        echo "  Timestamp: " . ($mostRecent['timestamp'] ?? 'N/A') . "\n";
        echo "  UTC Time: " . ($mostRecent['utctime'] ?? 'N/A') . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
}

