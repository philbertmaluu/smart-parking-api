# Camera Detection System

This system automatically fetches vehicle plate detection data from the ZKTeco camera and stores it in the database.

## Architecture

### Backend (Laravel)

1. **Artisan Command**: `fetch:camera-data`
   - Location: `app/Console/Commands/FetchCameraData.php`
   - Fetches data from ZKTeco camera API
   - Stores detections in `camera_detection_logs` table
   - Skips duplicate entries automatically

2. **Scheduled Task**: 
   - Location: `routes/console.php`
   - Runs every second
   - Configured in Laravel scheduler

3. **API Controller**: `CameraDetectionController`
   - Location: `app/Http/Controllers/API/CameraDetectionController.php`
   - Endpoint: `GET /api/toll-v1/camera-detection/fetch`
   - Returns stored detection logs from database

4. **Service**: `CameraDetectionService`
   - Location: `app/Services/CameraDetectionService.php`
   - Handles camera API communication
   - Maps camera response to database structure

5. **Repository**: `CameraDetectionLogRepository`
   - Location: `app/Repositories/CameraDetectionLogRepository.php`
   - Database operations for detection logs

6. **Model**: `CameraDetectionLog`
   - Location: `app/Models/CameraDetectionLog.php`
   - Table: `camera_detection_logs`

### Frontend (Next.js)

1. **Page**: Detection Logs Manager
   - Location: `app/manager/detection-logs/page.tsx`
   - Displays detection logs from database
   - Real-time stats and filtering

2. **Hook**: `useDetectionLogs`
   - Location: `hooks/use-detection-logs.ts`
   - Fetches detection logs from API
   - Manages loading and error states

## Configuration

### Environment Variables (.env)

```env
CAMERA_IP=192.168.0.109
CAMERA_COMPUTER_ID=1
```

## Usage

### Manual Command Execution

Fetch camera data once:
```bash
php artisan fetch:camera-data
```

Fetch camera data for a specific date:
```bash
php artisan fetch:camera-data --date="2025-11-26"
php artisan fetch:camera-data --date="2025-11-26 14:30:00"
```

### Running the Scheduler

**Option 1: Using the helper script (Development)**
```bash
./run-scheduler.sh
```

**Option 2: Using Laravel scheduler directly**
```bash
php artisan schedule:run
```

**Option 3: Production - Add to crontab**
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Testing

1. **Test the command**:
   ```bash
   php artisan fetch:camera-data
   ```

2. **Check stored data**:
   ```bash
   php artisan tinker
   >>> App\Models\CameraDetectionLog::count()
   >>> App\Models\CameraDetectionLog::latest('detection_timestamp')->first()
   ```

3. **Test API endpoint**:
   ```bash
   curl http://127.0.0.1:8000/api/toll-v1/camera-detection/fetch
   ```

4. **View in frontend**:
   - Navigate to: Manager > Detection Logs
   - Should display stored detections from database

## Database Schema

The `camera_detection_logs` table stores:
- Basic info: ID, plate number, timestamp
- Detection metrics: confidence, process time
- Vehicle details: make, model, color, speed
- Position data: bounding boxes
- Image paths: full image and cropped plate
- Processing status: processed flag and notes

## Features

- ✅ Automatic data fetching every second
- ✅ Duplicate detection prevention
- ✅ Complete vehicle metadata storage
- ✅ Processing status tracking
- ✅ Date range filtering
- ✅ Plate number search
- ✅ Real-time statistics
- ✅ Export to CSV/JSON
- ✅ Detailed view dialog

## Troubleshooting

### Camera not responding
- Check camera IP in `.env`
- Verify network connectivity
- Test camera URL directly: `http://192.168.0.109/edge/cgi-bin/vparcgi.cgi?computerid=1&oper=jsonlastresults&dd=2025-11-26T00:00:00.000&_=1234567890`

### No data appearing in frontend
- Verify scheduler is running
- Check command output: `php artisan fetch:camera-data`
- Check logs: `tail -f storage/logs/laravel.log`
- Verify API returns data: `curl http://127.0.0.1:8000/api/toll-v1/camera-detection/fetch`

### Scheduler not running
- Ensure cron is set up (production)
- Use `./run-scheduler.sh` for development
- Check schedule list: `php artisan schedule:list`

## Maintenance

### Clear old logs (optional)
```bash
php artisan tinker
>>> App\Models\CameraDetectionLog::where('detection_timestamp', '<', now()->subMonths(3))->delete()
```

### Mark detections as processed
```bash
PUT /api/toll-v1/camera-detection/logs/{id}/mark-processed
Body: { "notes": "Processed by system" }
```
