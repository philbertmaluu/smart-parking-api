# Debugging: Why No Detections Are Showing

## Quick Diagnostic Steps

### 1. Check if Scheduler is Running

```bash
# In backend terminal, you should see:
php artisan schedule:work

# Output should show:
Running scheduled command: fetch:camera-data
Running scheduled command: fetch:camera-data
...
```

### 2. Check if Detections Are Being Stored

```bash
php artisan tinker
```

Then in tinker:
```php
// Check total detections
\App\Models\CameraDetectionLog::count()

// Check pending detections
\App\Models\CameraDetectionLog::where('processing_status', 'pending_vehicle_type')->count()

// Check recent detections (last 5 minutes)
\App\Models\CameraDetectionLog::where('created_at', '>', now()->subMinutes(5))->count()

// See latest detection
\App\Models\CameraDetectionLog::latest('detection_timestamp')->first()

// Check all processing statuses
\App\Models\CameraDetectionLog::select('processing_status', DB::raw('count(*) as count'))
    ->groupBy('processing_status')
    ->get()
```

### 3. Check Operator Gate Assignment

```php
// In tinker, replace YOUR_USER_ID with your actual user ID
$userId = YOUR_USER_ID; // Get from auth()->id() when logged in

// Check operator stations
DB::table('operator_station')
    ->where('user_id', $userId)
    ->where('is_active', true)
    ->get()

// Check gates for those stations
$stations = DB::table('operator_station')
    ->where('user_id', $userId)
    ->where('is_active', true)
    ->pluck('station_id')
    ->toArray();

\App\Models\Gate::whereIn('station_id', $stations)
    ->where('is_active', true)
    ->get(['id', 'name', 'station_id'])
```

### 4. Check if Detections Match Operator Gates

```php
// Get operator gates
$userId = YOUR_USER_ID;
$stations = DB::table('operator_station')
    ->where('user_id', $userId)
    ->where('is_active', true)
    ->pluck('station_id')
    ->toArray();

$operatorGates = \App\Models\Gate::whereIn('station_id', $stations)
    ->where('is_active', true)
    ->pluck('id')
    ->toArray();

// Check pending detections and their gates
\App\Models\CameraDetectionLog::where('processing_status', 'pending_vehicle_type')
    ->select('id', 'numberplate', 'gate_id', 'detection_timestamp')
    ->get()
    ->map(function($d) use ($operatorGates) {
        return [
            'id' => $d->id,
            'plate' => $d->numberplate,
            'gate_id' => $d->gate_id,
            'matches_operator_gates' => in_array($d->gate_id, $operatorGates),
            'timestamp' => $d->detection_timestamp,
        ];
    })
```

### 5. Check Scheduler Logs

```bash
# Check if scheduler is actually running
tail -f storage/logs/schedule-camera.log

# Check Laravel logs for errors
tail -f storage/logs/laravel.log | grep -i "camera\|detection"
```

### 6. Test Camera Connection Manually

```bash
php artisan fetch:camera-data
```

This should show:
```
Fetching camera data...
✅ Fetched: X detections
✅ Stored: Y new detections
⏭️ Skipped: Z duplicates
```

### 7. Check API Response Directly

```bash
# Get your auth token first, then:
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://157.245.191.111/api/toll-v1/camera-detection/logs/pending-vehicle-type
```

## Common Issues

### Issue 1: Scheduler Not Running
**Symptom**: No output from `php artisan schedule:work`
**Fix**: Make sure you're running it in the backend directory and it's not stopped

### Issue 2: No Detections Stored
**Symptom**: `CameraDetectionLog::count()` returns 0 or very low
**Possible Causes**:
- Camera not accessible from server
- Camera IP wrong in `gate_devices` table
- All detections being skipped as duplicates
- Camera returning empty results

**Fix**: Check camera connection and `gate_devices` table

### Issue 3: Detections Stored But Not Pending
**Symptom**: Detections exist but `pending_vehicle_type` count is 0
**Possible Causes**:
- Detections being auto-processed
- Wrong `processing_status` being set
- Detections marked as `failed` or `processed`

**Fix**: Check `processing_status` values in database

### Issue 4: Operator Has No Gates
**Symptom**: Operator has no assigned stations/gates
**Fix**: Assign operator to stations in `operator_station` table

### Issue 5: Detections Don't Match Operator Gates
**Symptom**: Detections exist with `pending_vehicle_type` but have different `gate_id` than operator's gates
**Fix**: Either assign operator to correct stations, or check if detections have correct `gate_id`

## Quick Test Script

Create `test-detections.php` in backend root:

```php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CameraDetectionLog;
use Illuminate\Support\Facades\DB;

echo "=== Detection Status ===\n";
echo "Total detections: " . CameraDetectionLog::count() . "\n";
echo "Pending vehicle type: " . CameraDetectionLog::where('processing_status', 'pending_vehicle_type')->count() . "\n";
echo "Recent (last 5 min): " . CameraDetectionLog::where('created_at', '>', now()->subMinutes(5))->count() . "\n";

echo "\n=== Latest Detection ===\n";
$latest = CameraDetectionLog::latest('detection_timestamp')->first();
if ($latest) {
    echo "ID: {$latest->id}\n";
    echo "Plate: {$latest->numberplate}\n";
    echo "Gate ID: {$latest->gate_id}\n";
    echo "Status: {$latest->processing_status}\n";
    echo "Timestamp: {$latest->detection_timestamp}\n";
} else {
    echo "No detections found\n";
}

echo "\n=== Processing Status Breakdown ===\n";
$statuses = CameraDetectionLog::select('processing_status', DB::raw('count(*) as count'))
    ->groupBy('processing_status')
    ->get();
foreach ($statuses as $status) {
    echo "{$status->processing_status}: {$status->count}\n";
}
```

Run it:
```bash
php test-detections.php
```

