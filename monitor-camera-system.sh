#!/bin/bash

# Monitor Camera Detection System
# Shows real-time status of the detection system

echo "🎥 Camera Detection System Monitor"
echo "=================================="
echo ""

cd "$(dirname "$0")"

# Check if scheduler is running
if pgrep -f "run-scheduler.sh" > /dev/null; then
    echo "✅ Scheduler: RUNNING"
else
    echo "❌ Scheduler: NOT RUNNING"
    echo "   Start with: ./run-scheduler.sh &"
fi

echo ""

# Show database stats
echo "📊 Database Statistics:"
php artisan tinker --execute="
    echo '   Total Detections: ' . App\Models\CameraDetectionLog::count() . PHP_EOL;
    echo '   Processed: ' . App\Models\CameraDetectionLog::where('processed', true)->count() . PHP_EOL;
    echo '   Unprocessed: ' . App\Models\CameraDetectionLog::where('processed', false)->count() . PHP_EOL;
"

echo ""

# Show latest detections
echo "🚗 Latest 5 Detections:"
php artisan tinker --execute="
    App\Models\CameraDetectionLog::orderBy('created_at', 'desc')
        ->take(5)
        ->get(['numberplate', 'detection_timestamp', 'global_confidence', 'created_at'])
        ->each(function(\$d) {
            echo '   ' . \$d->numberplate . ' | ' . \$d->detection_timestamp . ' | Confidence: ' . \$d->global_confidence . '% | Stored: ' . \$d->created_at->diffForHumans() . PHP_EOL;
        });
"

echo ""
echo "✅ System is operational"
echo "   Refresh: Run this script again"
echo "   Logs: tail -f storage/logs/laravel.log"
