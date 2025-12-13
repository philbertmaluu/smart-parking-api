<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;

echo "=== Camera Detection Logs Status ===\n\n";
echo "Total detections: " . CameraDetectionLog::count() . "\n";
echo "Unprocessed: " . CameraDetectionLog::where('processed', false)->count() . "\n";
echo "Pending vehicle type: " . CameraDetectionLog::where('processing_status', 'pending_vehicle_type')->count() . "\n";
echo "Pending status: " . CameraDetectionLog::where('processing_status', 'pending')->count() . "\n";
echo "Pending exit: " . CameraDetectionLog::where('processing_status', 'pending_exit')->count() . "\n\n";

echo "Recent detections (last 10):\n";
CameraDetectionLog::orderBy('id', 'desc')
    ->limit(10)
    ->get(['id', 'numberplate', 'gate_id', 'processing_status', 'processed', 'processing_notes', 'detection_timestamp'])
    ->each(function($d) {
        echo sprintf(
            "  ID: %d, Plate: %s, Gate: %s, Status: %s, Processed: %s, Notes: %s, Time: %s\n",
            $d->id,
            $d->numberplate ?? 'N/A',
            $d->gate_id ?? 'NULL',
            $d->processing_status ?? 'NULL',
            $d->processed ? 'yes' : 'no',
            $d->processing_notes ?? 'N/A',
            $d->detection_timestamp
        );
    });

echo "\n=== Detections by status ===\n";
$statuses = CameraDetectionLog::selectRaw('processing_status, COUNT(*) as count')
    ->groupBy('processing_status')
    ->get();
    
foreach ($statuses as $status) {
    echo sprintf("  %s: %d\n", $status->processing_status ?? 'NULL', $status->count);
}

