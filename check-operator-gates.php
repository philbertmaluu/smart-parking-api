<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Models\User;
use App\Models\Gate;

echo "=== Operator Gate Assignments ===\n\n";

// Get all operators
$operators = User::whereHas('role', function($q) {
    $q->where('name', 'Gate Operator');
})->get();

foreach ($operators as $operator) {
    echo "Operator: {$operator->name} (ID: {$operator->id})\n";
    
    $assignedStations = \DB::table('operator_station')
        ->where('user_id', $operator->id)
        ->where('is_active', true)
        ->get();
    
    if ($assignedStations->isEmpty()) {
        echo "  No stations assigned\n";
    } else {
        foreach ($assignedStations as $assignment) {
            $station = \App\Models\Station::find($assignment->station_id);
            $gates = Gate::where('station_id', $assignment->station_id)->get();
            
            echo "  Station: {$station->name} (ID: {$station->id})\n";
            echo "    Gates: ";
            foreach ($gates as $gate) {
                echo "{$gate->name} (ID: {$gate->id}) ";
            }
            echo "\n";
        }
    }
    echo "\n";
}

echo "=== Pending Detection ===\n";
$pending = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
    ->first();

if ($pending) {
    echo "Detection ID: {$pending->id}\n";
    echo "Plate: {$pending->numberplate}\n";
    echo "Gate ID: {$pending->gate_id}\n";
    $gate = Gate::find($pending->gate_id);
    if ($gate) {
        echo "Gate Name: {$gate->name}\n";
        echo "Station ID: {$gate->station_id}\n";
        $station = $gate->station;
        if ($station) {
            echo "Station Name: {$station->name}\n";
        }
    }
} else {
    echo "No pending detections\n";
}

