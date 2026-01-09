<?php

/**
 * Quick Diagnostic Script for Camera Detections
 * 
 * Run this to check why detections aren't showing:
 * php check-detection-status.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use App\Models\Gate;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Camera Detection Status Check\n";
echo "========================================\n\n";

// 1. Check total detections
$total = CameraDetectionLog::count();
echo "1. Total detections in database: {$total}\n";

// 2. Check pending detections
$pending = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')->count();
echo "2. Pending vehicle type detections: {$pending}\n";

// 3. Check recent detections (last 5 minutes)
$recent = CameraDetectionLog::where('created_at', '>', now()->subMinutes(5))->count();
echo "3. Detections created in last 5 minutes: {$recent}\n";

// 4. Check latest detection
echo "\n4. Latest detection:\n";
$latest = CameraDetectionLog::latest('detection_timestamp')->first();
if ($latest) {
    echo "   ID: {$latest->id}\n";
    echo "   Plate: {$latest->numberplate}\n";
    echo "   Gate ID: {$latest->gate_id}\n";
    echo "   Status: {$latest->processing_status}\n";
    echo "   Timestamp: {$latest->detection_timestamp}\n";
    echo "   Created: {$latest->created_at}\n";
} else {
    echo "   ❌ No detections found\n";
}

// 5. Processing status breakdown
echo "\n5. Processing status breakdown:\n";
$statuses = CameraDetectionLog::select('processing_status', DB::raw('count(*) as count'))
    ->groupBy('processing_status')
    ->get();
if ($statuses->isEmpty()) {
    echo "   ❌ No detections with any status\n";
} else {
    foreach ($statuses as $status) {
        $statusName = $status->processing_status ?: '(null)';
        echo "   {$statusName}: {$status->count}\n";
    }
}

// 6. Check gate assignments for detections
echo "\n6. Gate IDs in detections:\n";
$gateIds = CameraDetectionLog::select('gate_id', DB::raw('count(*) as count'))
    ->whereNotNull('gate_id')
    ->groupBy('gate_id')
    ->get();
if ($gateIds->isEmpty()) {
    echo "   ⚠️  No detections have gate_id assigned\n";
} else {
    foreach ($gateIds as $gateInfo) {
        $gate = Gate::find($gateInfo->gate_id);
        $gateName = $gate ? $gate->name : "Gate ID {$gateInfo->gate_id} (not found)";
        echo "   Gate {$gateInfo->gate_id} ({$gateName}): {$gateInfo->count} detections\n";
    }
}

// 7. Check pending detections by gate
echo "\n7. Pending detections by gate:\n";
$pendingByGate = CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
    ->select('gate_id', DB::raw('count(*) as count'))
    ->groupBy('gate_id')
    ->get();
if ($pendingByGate->isEmpty()) {
    echo "   ❌ No pending detections found\n";
} else {
    foreach ($pendingByGate as $gateInfo) {
        $gate = Gate::find($gateInfo->gate_id);
        $gateName = $gate ? $gate->name : "Gate ID {$gateInfo->gate_id} (not found)";
        echo "   Gate {$gateInfo->gate_id} ({$gateName}): {$gateInfo->count} pending\n";
    }
}

// 8. Check if scheduler is working (recent detections)
echo "\n8. Scheduler activity check:\n";
$last10Minutes = CameraDetectionLog::where('created_at', '>', now()->subMinutes(10))->count();
$last1Hour = CameraDetectionLog::where('created_at', '>', now()->subHours(1))->count();
echo "   Last 10 minutes: {$last10Minutes} detections\n";
echo "   Last 1 hour: {$last1Hour} detections\n";

if ($last10Minutes === 0 && $last1Hour === 0) {
    echo "   ⚠️  WARNING: No detections in last hour - scheduler may not be running!\n";
} elseif ($last10Minutes === 0 && $last1Hour > 0) {
    echo "   ⚠️  WARNING: No recent detections - scheduler may have stopped!\n";
} else {
    echo "   ✅ Scheduler appears to be working\n";
}

echo "\n========================================\n";
echo "Next Steps:\n";
echo "========================================\n";

if ($total === 0) {
    echo "❌ No detections in database at all.\n";
    echo "   → Check if scheduler is running: php artisan schedule:work\n";
    echo "   → Test camera connection: php artisan fetch:camera-data\n";
    echo "   → Check camera IP in gate_devices table\n";
} elseif ($pending === 0) {
    echo "⚠️  Detections exist but none are pending.\n";
    echo "   → Check processing_status values above\n";
    echo "   → Detections may be auto-processed or marked as failed\n";
} else {
    echo "✅ {$pending} pending detections found.\n";
    echo "   → If not showing in frontend, check operator gate assignments\n";
    echo "   → Run: php artisan tinker\n";
    echo "   → Then check operator gates (see DEBUG_DETECTIONS.md)\n";
}

echo "\n";

