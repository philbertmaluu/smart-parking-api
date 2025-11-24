# Camera Detection API - Postman Guide

This guide will help you test the Camera Detection Service endpoints using Postman.

## Base URL

```
http://your-domain.com/api/toll-v1
```

**Note:** Replace `your-domain.com` with your actual domain (e.g., `localhost:8000` for local development)

## Authentication

Most endpoints require authentication. You need to:
1. First login to get an access token
2. Include the token in the `Authorization` header for protected routes

### Step 1: Login to Get Token

**Request:**
- **Method:** `POST`
- **URL:** `http://your-domain.com/api/toll-v1/login`
- **Headers:**
  ```
  Content-Type: application/json
  ```
- **Body (JSON):**
  ```json
  {
    "email": "your-email@example.com",
    "password": "your-password"
  }
  ```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {...},
    "token": "your-access-token-here"
  },
  "messages": "Login successful"
}
```

### Step 2: Use Token in Requests

For all protected endpoints, add this header:
```
Authorization: Bearer your-access-token-here
```

---

## Camera Detection Endpoints

### 1. Get Camera Detection Configuration

Get the current camera configuration (IP, computer ID).

**Request:**
- **Method:** `GET`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/config`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Accept: application/json
  ```

**Response:**
```json
{
  "success": true,
  "data": {
    "camera_ip": "192.168.0.109",
    "computer_id": 1
  },
  "messages": "Camera detection configuration retrieved successfully"
}
```

---

### 2. Fetch Camera Logs (Without Storing)

Fetch plate number detections from the camera API without storing them in the database.

**Request:**
- **Method:** `GET`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/fetch`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Accept: application/json
  ```
- **Query Parameters (Optional):**
  - `date` - Date/time to query (format: `YYYY-MM-DD` or `YYYY-MM-DD HH:mm:ss`)
    - Example: `?date=2025-11-24`
    - Example: `?date=2025-11-24 14:30:00`
    - If not provided, uses current date/time

**Example URLs:**
```
http://your-domain.com/api/toll-v1/camera-detection/fetch
http://your-domain.com/api/toll-v1/camera-detection/fetch?date=2025-11-24
http://your-domain.com/api/toll-v1/camera-detection/fetch?date=2025-11-24 14:30:00
```

**Response:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "message": "Camera logs fetched successfully",
    "data": [
      {
        "id": 12345,
        "numberplate": "ABC123",
        "timestamp": "2025-11-24T14:30:00",
        "utctime": "2025-11-24T12:30:00Z",
        "locatedPlate": true,
        "globalconfidence": 95.5,
        "speed": 45.2,
        "direction": 1,
        "make": "Toyota",
        "model": "Camry",
        "color": "White",
        ...
      }
    ],
    "count": 1
  },
  "messages": "Camera logs fetched successfully"
}
```

---

### 3. Store Camera Logs

Store detection logs in the database. You need to provide the detection data array.

**Request:**
- **Method:** `POST`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/store`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON):**
  ```json
  {
    "detections": [
      {
        "id": 12345,
        "numberplate": "ABC123",
        "timestamp": "2025-11-24T14:30:00",
        "utctime": "2025-11-24T12:30:00Z",
        "locatedPlate": true,
        "globalconfidence": 95.5,
        "speed": 45.2,
        "direction": 1,
        "make": "Toyota",
        "model": "Camry",
        "color": "White"
      }
    ]
  }
  ```

**Response:**
```json
{
  "success": true,
  "data": {
    "stored": 1,
    "skipped": 0,
    "errors": 0,
    "total": 1
  },
  "messages": "Camera logs stored successfully"
}
```

---

### 4. Fetch and Store Camera Logs (One Operation)

Fetch logs from the camera API and automatically store them in the database.

**Request:**
- **Method:** `POST`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/fetch-and-store`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON - Optional):**
  ```json
  {
    "date": "2025-11-24"
  }
  ```
  - If `date` is not provided, uses current date/time

**Response:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "message": "Camera logs fetched and stored successfully",
    "fetched": 5,
    "stored": 5,
    "skipped": 0,
    "errors": 0
  },
  "messages": "Camera logs fetched and stored successfully"
}
```

---

### 5. Get Stored Detection Logs

Retrieve stored detection logs from the database with filtering options.

**Request:**
- **Method:** `GET`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/logs`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Accept: application/json
  ```
- **Query Parameters (All Optional):**
  - `per_page` - Number of results per page (default: 15)
  - `page` - Page number (default: 1)
  - `plate_number` - Filter by plate number
  - `start_date` - Start date for date range filter (format: `YYYY-MM-DD`)
  - `end_date` - End date for date range filter (format: `YYYY-MM-DD`)
  - `processed` - Filter by processed status (`true`, `false`, or omit for all)

**Example URLs:**
```
# Get all logs (paginated)
http://your-domain.com/api/toll-v1/camera-detection/logs

# Get logs for specific plate number
http://your-domain.com/api/toll-v1/camera-detection/logs?plate_number=ABC123

# Get unprocessed logs
http://your-domain.com/api/toll-v1/camera-detection/logs?processed=false

# Get logs by date range
http://your-domain.com/api/toll-v1/camera-detection/logs?start_date=2025-11-24&end_date=2025-11-25

# Combined filters
http://your-domain.com/api/toll-v1/camera-detection/logs?plate_number=ABC123&processed=false&per_page=20
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "camera_detection_id": 12345,
        "numberplate": "ABC123",
        "detection_timestamp": "2025-11-24T14:30:00.000000Z",
        "global_confidence": 95.5,
        "speed": 45.2,
        "processed": false,
        ...
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 50,
    "last_page": 4
  },
  "messages": "Detection logs retrieved successfully"
}
```

---

### 6. Get Unprocessed Detection Logs

Get only the detection logs that haven't been processed yet.

**Request:**
- **Method:** `GET`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/logs/unprocessed`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Accept: application/json
  ```
- **Query Parameters (Optional):**
  - `per_page` - Number of results per page (default: 15)
  - `page` - Page number (default: 1)

**Example URL:**
```
http://your-domain.com/api/toll-v1/camera-detection/logs/unprocessed?per_page=20&page=1
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "numberplate": "ABC123",
        "detection_timestamp": "2025-11-24T14:30:00.000000Z",
        "processed": false,
        ...
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 10,
    "last_page": 1
  },
  "messages": "Unprocessed detection logs retrieved successfully"
}
```

---

### 7. Get Detection Logs by Plate Number

Get all detection logs for a specific plate number.

**Request:**
- **Method:** `GET`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/logs/plate/{plateNumber}`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Accept: application/json
  ```

**Example URL:**
```
http://your-domain.com/api/toll-v1/camera-detection/logs/plate/ABC123
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "numberplate": "ABC123",
      "detection_timestamp": "2025-11-24T14:30:00.000000Z",
      ...
    },
    {
      "id": 2,
      "numberplate": "ABC123",
      "detection_timestamp": "2025-11-24T15:45:00.000000Z",
      ...
    }
  ],
  "messages": "Detection logs for plate number ABC123 retrieved successfully"
}
```

---

### 8. Mark Detection as Processed

Mark a specific detection log as processed (useful after processing it for vehicle entry/exit).

**Request:**
- **Method:** `PUT`
- **URL:** `http://your-domain.com/api/toll-v1/camera-detection/logs/{id}/mark-processed`
- **Headers:**
  ```
  Authorization: Bearer your-access-token-here
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (JSON - Optional):**
  ```json
  {
    "notes": "Processed for vehicle entry at Gate 1"
  }
  ```

**Example URL:**
```
http://your-domain.com/api/toll-v1/camera-detection/logs/1/mark-processed
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "numberplate": "ABC123",
    "processed": true,
    "processed_at": "2025-11-24T16:00:00.000000Z",
    "processing_notes": "Processed for vehicle entry at Gate 1",
    ...
  },
  "messages": "Detection log marked as processed"
}
```

---

## Postman Collection Setup

### Quick Setup Steps:

1. **Create a new Postman Collection** named "Smart Parking API - Camera Detection"

2. **Set Collection Variables:**
   - Go to Collection → Variables
   - Add:
     - `base_url`: `http://localhost:8000/api/toll-v1` (or your domain)
     - `token`: (leave empty, will be set after login)

3. **Create Environment Variables (Optional):**
   - Create a new Environment
   - Add:
     - `base_url`: `http://localhost:8000/api/toll-v1`
     - `token`: (will be set after login)
     - `email`: `your-email@example.com`
     - `password`: `your-password`

4. **Create Requests:**

   **a. Login Request:**
   - Method: `POST`
   - URL: `{{base_url}}/login`
   - Body: 
     ```json
     {
       "email": "{{email}}",
       "password": "{{password}}"
     }
     ```
   - Add Test Script to save token:
     ```javascript
     if (pm.response.code === 200) {
         var jsonData = pm.response.json();
         if (jsonData.data && jsonData.data.token) {
             pm.environment.set("token", jsonData.data.token);
             pm.collectionVariables.set("token", jsonData.data.token);
         }
     }
     ```

   **b. Camera Detection Requests:**
   - Use `{{base_url}}/camera-detection/...` for URLs
   - Add header: `Authorization: Bearer {{token}}`

---

## Common Workflows

### Workflow 1: Fetch and Store Latest Detections

1. **POST** `/camera-detection/fetch-and-store`
   - This will fetch the latest detections and store them automatically

### Workflow 2: Manual Fetch and Store

1. **GET** `/camera-detection/fetch` - Get detections from camera
2. **POST** `/camera-detection/store` - Store the detections manually

### Workflow 3: Process Unprocessed Detections

1. **GET** `/camera-detection/logs/unprocessed` - Get unprocessed logs
2. Process each detection (e.g., create vehicle entry)
3. **PUT** `/camera-detection/logs/{id}/mark-processed` - Mark as processed

### Workflow 4: Query Specific Plate Number

1. **GET** `/camera-detection/logs/plate/ABC123` - Get all detections for plate

---

## Error Responses

All endpoints return errors in this format:

```json
{
  "success": false,
  "message": "Error message here",
  "data": {
    "error": "Detailed error information"
  }
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized (missing or invalid token)
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Testing Tips

1. **Start with Login** - Always login first to get your token
2. **Check Config** - Use `/camera-detection/config` to verify camera settings
3. **Test Fetch** - Use `/camera-detection/fetch` to see what data the camera returns
4. **Store Data** - Use `/camera-detection/fetch-and-store` to save detections
5. **Query Logs** - Use `/camera-detection/logs` to view stored detections

---

## Troubleshooting

### Issue: "Unauthenticated" Error
- **Solution:** Make sure you're logged in and the token is included in the Authorization header

### Issue: "Camera API request failed"
- **Solution:** 
  - Check if camera is accessible at the configured IP
  - Verify `CAMERA_IP` and `CAMERA_COMPUTER_ID` in your `.env` file
  - Test camera connection directly: `http://192.168.0.109/edge/cgi-bin/vparcgi.cgi?computerid=1&oper=jsonlastresults&dd=2025-11-24T02:10:23.230&_=1763978860999`

### Issue: Empty Response
- **Solution:** The camera might not have any detections for the specified date. Try using current date or check camera logs.

---

## Environment Variables

Make sure these are set in your `.env` file:

```env
CAMERA_IP=192.168.0.109
CAMERA_COMPUTER_ID=1
```

---

## Need Help?

If you encounter any issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify camera connectivity
3. Check API response format matches expected structure

