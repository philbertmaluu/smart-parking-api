# Backend Setup Instructions - Camera Detection

## ✅ Quick Answer

**For Development (Testing):**
```bash
php artisan schedule:work
```

**For Production (Server):**
Set up a cron job (see below)

## 🔧 Development Setup

### Option 1: Use `schedule:work` (Recommended for Testing)

This runs the scheduler continuously in development:

```bash
cd smart-parking-api
php artisan schedule:work
```

**What it does:**
- Runs `fetch:camera-data` every 2 seconds automatically
- Shows real-time output in terminal
- Stops when you press Ctrl+C

**You should see:**
```
Running scheduled command: fetch:camera-data
Running scheduled command: fetch:camera-data
Running scheduled command: fetch:camera-data
...
```

### Option 2: Manual Testing

Test the command once:
```bash
php artisan fetch:camera-data
```

## 🚀 Production Setup

### Step 1: Set Up Cron Job

The Laravel scheduler needs to be called every minute by your system's cron.

**On Linux/Ubuntu Server:**

1. Open crontab:
```bash
crontab -e
```

2. Add this line (replace `/path/to/smart-parking-api` with your actual path):
```bash
* * * * * cd /path/to/smart-parking-api && php artisan schedule:run >> /dev/null 2>&1
```

3. Save and exit

**On Windows Server:**

Use Task Scheduler to run:
```bash
php artisan schedule:run
```
Every minute.

### Step 2: Verify It's Working

Check the logs:
```bash
tail -f storage/logs/schedule-camera.log
```

You should see output every 2 seconds showing camera fetches.

## 🔍 Verification Steps

### 1. Check if Command Works
```bash
php artisan fetch:camera-data
```

Expected output:
```
Fetching camera data...
✅ Fetched: X detections
✅ Stored: Y new detections
⏭️ Skipped: Z duplicates
```

### 2. Check Database
```bash
php artisan tinker
```

Then in tinker:
```php
// Count pending detections
\App\Models\CameraDetectionLog::where('processing_status', 'pending_vehicle_type')->count()

// See latest detection
\App\Models\CameraDetectionLog::latest('detection_timestamp')->first()

// Check if scheduler is working
\App\Models\CameraDetectionLog::where('created_at', '>', now()->subMinutes(5))->count()
```

### 3. Check Scheduler Status
```bash
php artisan schedule:list
```

Should show:
```
fetch:camera-data  .......... Every two seconds
```

## ⚠️ Common Issues

### Issue: "No scheduled commands are ready to run"
**Fix:** Make sure you're running `php artisan schedule:work` (not `schedule:run` once)

### Issue: Command runs but no detections stored
**Check:**
1. Camera IP is correct in `gate_devices` table
2. Camera is accessible from server
3. Check logs: `tail -f storage/logs/laravel.log`

### Issue: Duplicate detections
**This is normal** - the system skips duplicates automatically. Check logs to see "Skipped: X duplicates"

## 📝 Summary

**For Development (Right Now):**
1. Open a new terminal
2. `cd smart-parking-api`
3. Run: `php artisan schedule:work`
4. Keep it running - it will fetch every 2 seconds

**For Production (Later):**
1. Set up cron job to run `php artisan schedule:run` every minute
2. Laravel will handle the 2-second intervals internally

## 🎯 What Happens

1. **Every 2 seconds**: `fetch:camera-data` runs
2. **Fetches from camera**: Gets last 10 detections
3. **Filters new ones**: Only stores detections newer than last processed
4. **Stores in database**: Marks as `pending_vehicle_type`
5. **Frontend polls**: Every 2.5 seconds, frontend checks for new detections
6. **Shows modal**: When new detection found, modal appears

That's it! Just run `php artisan schedule:work` and keep it running.

