<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Repositories\CameraDetectionLogRepository;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Testing API Response ===\n\n";

// Simulate operator 4
$operatorId = 4;
$user = User::find($operatorId);

if (!$user) {
    echo "Operator not found\n";
    exit;
}

echo "Operator: {$user->name} (ID: {$user->id}, Role ID: {$user->role_id})\n\n";

// Get operator's assigned stations
$assignedStations = DB::table('operator_station')
    ->where('user_id', $operatorId)
    ->where('is_active', true)
    ->pluck('station_id')
    ->toArray();

echo "Assigned stations: " . implode(', ', $assignedStations) . "\n";

if (!empty($assignedStations)) {
    $operatorGates = \App\Models\Gate::whereIn('station_id', $assignedStations)
        ->where('is_active', true)
        ->pluck('id')
        ->toArray();
    
    echo "Operator gates: " . implode(', ', $operatorGates) . "\n\n";
} else {
    echo "No stations assigned\n";
    exit;
}

// Get pending detections (simulating API logic)
$repository = new CameraDetectionLogRepository();
$allPending = $repository->getPendingVehicleTypeDetections();

echo "All pending detections: " . $allPending->count() . "\n";
foreach ($allPending as $det) {
    echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Gate: {$det->gate_id}\n";
}

// Filter by operator gates (simulating API logic)
$filtered = $allPending->whereIn('gate_id', $operatorGates);

echo "\nFiltered detections (operator gates): " . $filtered->count() . "\n";
foreach ($filtered as $det) {
    echo "  - ID: {$det->id}, Plate: {$det->numberplate}, Gate: {$det->gate_id}\n";
}

// Check if detection 293 is included
$det293 = $filtered->firstWhere('id', 293);
if ($det293) {
    echo "\n✓ Detection 293 IS in the filtered results - API should return it!\n";
} else {
    echo "\n✗ Detection 293 is NOT in the filtered results\n";
    if ($allPending->contains('id', 293)) {
        echo "  But it IS in all pending detections\n";
        $det293 = $allPending->firstWhere('id', 293);
        echo "  Detection 293 gate_id: {$det293->gate_id}\n";
        echo "  Operator gates: " . implode(', ', $operatorGates) . "\n";
        echo "  Is gate 293 in operator gates? " . (in_array($det293->gate_id, $operatorGates) ? 'YES' : 'NO') . "\n";
    }
}

