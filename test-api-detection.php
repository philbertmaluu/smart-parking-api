<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Models\User;
use App\Models\Gate;
use App\Repositories\CameraDetectionLogRepository;

echo "=== Testing API Detection Filtering ===\n\n";

// Simulate operator 4 (or 5)
$operatorId = 4;
$user = User::find($operatorId);

if (!$user) {
    echo "Operator not found\n";
    exit;
}

echo "Operator: {$user->name} (ID: {$user->id}, Role ID: {$user->role_id})\n\n";

// Get operator's assigned stations
$assignedStations = \DB::table('operator_station')
    ->where('user_id', $operatorId)
    ->where('is_active', true)
    ->pluck('station_id')
    ->toArray();

echo "Assigned stations: " . implode(', ', $assignedStations) . "\n";

if (!empty($assignedStations)) {
    $operatorGates = Gate::whereIn('station_id', $assignedStations)
        ->where('is_active', true)
        ->pluck('id')
        ->toArray();
    
    echo "Operator gates: " . implode(', ', $operatorGates) . "\n\n";
} else {
    echo "No stations assigned\n";
    exit;
}

// Get pending detections
$repository = new CameraDetectionLogRepository();
$allPending = $repository->getPendingVehicleTypeDetections();

echo "All pending detections: " . $allPending->count() . "\n";
foreach ($allPending as $det) {
    echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Gate: {$det->gate_id}\n";
}

echo "\n";

// Filter by operator gates (simulating API logic)
$filtered = $allPending->whereIn('gate_id', $operatorGates);

echo "Filtered detections (operator gates): " . $filtered->count() . "\n";
foreach ($filtered as $det) {
    echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Gate: {$det->gate_id}\n";
}

