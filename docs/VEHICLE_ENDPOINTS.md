# Vehicle Management Endpoints

This document outlines all the available vehicle management endpoints in the Smart Parking System API.

## Base URL
All endpoints are prefixed with `/api/toll-v1`

## Authentication
All endpoints require authentication using Laravel Sanctum. Include the Bearer token in the Authorization header.

## Endpoints

### 1. List All Vehicles
- **GET** `/vehicles`
- **Description**: Get a paginated list of all vehicles
- **Query Parameters**:
  - `per_page` (optional): Number of items per page (default: 15)
  - `search` (optional): Search by plate number, make, or model
  - `body_type_id` (optional): Filter by vehicle body type
- **Response**: Paginated list of vehicles with body type and passages

### 2. Create Vehicle
- **POST** `/vehicles`
- **Description**: Create a new vehicle
- **Request Body**: Vehicle data (plate_number, body_type_id, make, model, year, color, owner_name, is_registered)
- **Response**: Created vehicle object

### 3. Get Vehicle Details
- **GET** `/vehicles/{id}`
- **Description**: Get detailed information about a specific vehicle
- **Response**: Vehicle object with body type and passages

### 4. Update Vehicle
- **PUT/PATCH** `/vehicles/{id}`
- **Description**: Update an existing vehicle
- **Request Body**: Vehicle data to update
- **Response**: Updated vehicle object

### 5. Delete Vehicle
- **DELETE** `/vehicles/{id}`
- **Description**: Soft delete a vehicle
- **Response**: Success message

### 6. Search Vehicle by Plate Number
- **GET** `/vehicles/search/plate/{plateNumber}`
- **Description**: Search for vehicles by partial plate number match
- **Response**: Vehicle object if found

### 7. Lookup Vehicle by Exact Plate Number
- **GET** `/vehicles/lookup/{plateNumber}`
- **Description**: Lookup vehicle by exact plate number match
- **Response**: Vehicle object if found

### 8. Get Active Vehicles List
- **GET** `/vehicles/active/list`
- **Description**: Get all active vehicles for dropdown/select purposes
- **Response**: List of vehicles with basic information

### 9. Get Vehicles by Body Type
- **GET** `/vehicles/body-type/{bodyTypeId}`
- **Description**: Get all vehicles of a specific body type
- **Response**: List of vehicles with the specified body type

### 10. Get Registered Vehicles
- **GET** `/vehicles/registered/list`
- **Description**: Get all registered vehicles (is_registered = true)
- **Response**: List of registered vehicles

### 11. Get Unregistered Vehicles
- **GET** `/vehicles/unregistered/list`
- **Description**: Get all unregistered vehicles (is_registered = false)
- **Response**: List of unregistered vehicles

### 12. Get Vehicle Statistics
- **GET** `/vehicles/{id}/statistics`
- **Description**: Get statistics for a specific vehicle
- **Query Parameters**:
  - `start_date` (optional): Start date for statistics (default: 30 days ago)
  - `end_date` (optional): End date for statistics (default: today)
- **Response**: Statistics object with passage counts and amounts

## Vehicle Model Fields

The vehicle model includes the following fields:

- `id`: Primary key
- `body_type_id`: Foreign key to vehicle body type
- `plate_number`: Vehicle plate number (unique)
- `make`: Vehicle make/brand
- `model`: Vehicle model
- `year`: Manufacturing year
- `color`: Vehicle color
- `owner_name`: Vehicle owner name
- `is_registered`: Boolean flag for frequent users
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp
- `deleted_at`: Soft delete timestamp

## Relationships

- `bodyType`: Belongs to VehicleBodyType
- `vehiclePassages`: Has many VehiclePassage
- `accountVehicles`: Has many AccountVehicle
- `accounts`: Belongs to many Account through AccountVehicle

## Error Responses

All endpoints return consistent error responses:

```json
{
    "success": false,
    "message": "Error message",
    "data": null
}
```

## Success Responses

All endpoints return consistent success responses:

```json
{
    "success": true,
    "message": "Success message",
    "data": {
        // Response data
    }
}
```

## Usage Examples

### Creating a Vehicle
```bash
curl -X POST /api/toll-v1/vehicles \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "plate_number": "ABC123",
    "body_type_id": 1,
    "make": "Toyota",
    "model": "Camry",
    "year": 2020,
    "color": "White",
    "owner_name": "John Doe",
    "is_registered": true
  }'
```

### Searching for a Vehicle
```bash
curl -X GET /api/toll-v1/vehicles/search/plate/ABC \
  -H "Authorization: Bearer {token}"
```

### Getting Vehicle Statistics
```bash
curl -X GET "/api/toll-v1/vehicles/1/statistics?start_date=2024-01-01&end_date=2024-12-31" \
  -H "Authorization: Bearer {token}"
```
