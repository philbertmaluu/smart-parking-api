# Toll Service - Documentation

## 🎯 Overview

The Toll Service is a streamlined, independent service designed for basic toll fee collection without complex features like bundle subscriptions or exemptions. It provides a straightforward hourly-based toll system.

## 🔄 Service Flow

### Vehicle Entry Process
1. **Detect plate number** → Find or create vehicle
2. **Determine body type price** → Get hourly rate for vehicle type
3. **Open gate** → Allow vehicle entry
4. **Log passage entry** → Create passage record with entry time

### Vehicle Exit Process
5. **Detect plate number** → Find vehicle and active passage
6. **Calculate amount** → Based on time spent (hourly rate)
7. **Require payment** → If not confirmed, keep gate closed
8. **Open gate** → When payment is confirmed

## 📋 API Endpoints

### Base URL
```
/api/toll-v1/toll
```

### 1. Vehicle Entry
**POST** `/entry`

**Request Body:**
```json
{
    "plate_number": "ABC123",
    "gate_id": 1,
    "operator_id": 1,
    "body_type_id": 1,
    "make": "Toyota",
    "model": "Camry",
    "year": 2020,
    "color": "White",
    "owner_name": "John Doe",
    "notes": "Optional notes"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "passage_id": 123,
        "vehicle": {...},
        "entry_time": "2025-01-15 10:30:00",
        "price_per_hour": 5.00
    },
    "messages": "Vehicle entry processed successfully",
    "status": 200
}
```

### 2. Vehicle Exit
**POST** `/exit`

**Request Body:**
```json
{
    "plate_number": "ABC123",
    "gate_id": 2,
    "operator_id": 1,
    "payment_confirmed": false,
    "payment_method": "cash",
    "payment_amount": 15.00,
    "notes": "Optional notes"
}
```

**Response (Payment Required):**
```json
{
    "success": true,
    "message": "Payment required before exit",
    "gate_action": "require_payment",
    "data": {
        "passage_id": 123,
        "vehicle": {...},
        "total_amount": 15.00,
        "hours_charged": 3,
        "entry_time": "2025-01-15 10:30:00",
        "exit_time": "2025-01-15 13:30:00"
    }
}
```

**Response (Payment Confirmed):**
```json
{
    "success": true,
    "message": "Vehicle exit processed successfully",
    "gate_action": "open",
    "data": {
        "passage_id": 123,
        "vehicle": {...},
        "total_amount": 15.00,
        "hours_charged": 3,
        "entry_time": "2025-01-15 10:30:00",
        "exit_time": "2025-01-15 13:30:00",
        "receipt": {...}
    }
}
```

### 3. Confirm Payment
**POST** `/confirm-payment`

**Request Body:**
```json
{
    "passage_id": 123,
    "operator_id": 1,
    "payment_method": "cash",
    "payment_amount": 15.00,
    "receipt_notes": "Payment received"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment confirmed, gate will open",
    "gate_action": "open",
    "data": {
        "passage_id": 123,
        "receipt": {...}
    }
}
```

### 4. Get Active Passages
**GET** `/active-passages`

**Response:**
```json
{
    "success": true,
    "data": [...],
    "count": 5
}
```

### 5. Get Passage Details
**GET** `/passage/{passageId}`

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 123,
        "vehicle": {...},
        "entry_time": "2025-01-15 10:30:00",
        "exit_time": null,
        "total_amount": 0,
        "base_amount": 5.00,
        "receipts": [...]
    }
}
```

### 6. Calculate Toll Amount
**POST** `/calculate-toll`

**Request Body:**
```json
{
    "passage_id": 123
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "passage_id": 123,
        "entry_time": "2025-01-15 10:30:00",
        "current_time": "2025-01-15 13:30:00",
        "hours_spent": 3.0,
        "hours_to_charge": 3,
        "price_per_hour": 5.00,
        "total_amount": 15.00
    }
}
```

## 💰 Pricing Logic

### Hourly Rate Calculation
- **Base Rate**: Set per vehicle body type and station
- **Minimum Charge**: 1 hour minimum
- **Rounding**: Always round up to next hour
- **Formula**: `total_amount = base_amount × ceil(hours_spent)`

### Example Calculation
```
Entry Time: 10:30 AM
Exit Time: 1:45 PM
Hours Spent: 3.25 hours
Hours to Charge: 4 hours (rounded up)
Price per Hour: $5.00
Total Amount: $20.00
```

## 🚪 Gate Actions

| Action | Description | When Used |
|--------|-------------|-----------|
| `open` | Allow vehicle through | Entry successful, exit with payment |
| `deny` | Block vehicle | Invalid conditions, errors |
| `require_payment` | Keep gate closed | Exit without payment confirmation |

## 🗄️ Database Schema

### VehiclePassage Table
```sql
- id (Primary Key)
- vehicle_id (Foreign Key)
- entry_time (Timestamp)
- exit_time (Timestamp, nullable)
- entry_gate_id (Foreign Key)
- exit_gate_id (Foreign Key, nullable)
- entry_station_id (Foreign Key)
- exit_station_id (Foreign Key, nullable)
- passage_type (String: 'toll')
- base_amount (Decimal: price per hour)
- total_amount (Decimal: calculated total)
- entry_operator_id (Foreign Key)
- exit_operator_id (Foreign Key, nullable)
- notes (Text, nullable)
```

## 🔧 Service Methods

### TollService

#### `processVehicleEntry($plateNumber, $gateId, $operatorId, $additionalData)`
- Finds or creates vehicle
- Gets body type pricing
- Creates passage entry
- Returns gate action

#### `processVehicleExit($plateNumber, $gateId, $operatorId, $additionalData)`
- Finds vehicle and active passage
- Calculates time-based amount
- Handles payment confirmation
- Returns gate action

#### `confirmPayment($passageId, $operatorId, $paymentData)`
- Processes payment
- Generates receipt
- Returns gate action

#### `getActivePassages()`
- Returns all active passages
- Includes vehicle and gate information

#### `getPassageDetails($passageId)`
- Returns detailed passage information
- Includes receipts and related data

## 🔍 Usage Examples

### Complete Flow Example

```php
// 1. Vehicle Entry
$entryResult = $tollService->processVehicleEntry(
    'ABC123',
    1, // gate_id
    1, // operator_id
    ['body_type_id' => 1, 'make' => 'Toyota']
);

// 2. Calculate Toll (after some time)
$tollResult = $tollService->calculateTollAmount(123);

// 3. Vehicle Exit (without payment)
$exitResult = $tollService->processVehicleExit(
    'ABC123',
    2, // exit gate_id
    1, // operator_id
    ['payment_confirmed' => false]
);

// 4. Confirm Payment
$paymentResult = $tollService->confirmPayment(
    123, // passage_id
    1, // operator_id
    ['payment_method' => 'cash', 'payment_amount' => 15.00]
);
```

## 🛠️ Configuration

### Required Models
- **Vehicle**: Vehicle information
- **VehiclePassage**: Entry/exit records
- **Gate**: Physical gates
- **Station**: Toll stations
- **VehicleBodyTypePrice**: Pricing configuration
- **Receipt**: Payment receipts
- **PaymentType**: Payment methods

### Required Data
1. **Vehicle Body Types**: Configure vehicle categories
2. **Pricing**: Set hourly rates per body type and station
3. **Gates**: Configure entry/exit gates
4. **Stations**: Set up toll stations

## 🔒 Security

### Authentication
- All endpoints require `auth:sanctum` middleware
- Bearer token authentication
- Operator ID validation

### Validation
- Input validation for all parameters
- Database constraint validation
- Business logic validation

## 📊 Monitoring

### Logging
- Entry/exit processing logs
- Payment confirmation logs
- Error logging with context
- Performance monitoring

### Metrics
- Active passages count
- Revenue tracking
- Processing times
- Error rates

## 🚀 Deployment

### Requirements
- Laravel 10+
- PHP 8.1+
- MySQL/PostgreSQL
- Redis (for caching)

### Installation
1. Service is automatically loaded
2. Routes are registered
3. No additional configuration needed

## 🧪 Testing

### Unit Tests
```php
// Test vehicle entry
$result = $service->processVehicleEntry('TEST123', 1, 1);
$this->assertTrue($result['success']);

// Test payment calculation
$result = $service->calculateTollAmount(123);
$this->assertEquals(15.00, $result['data']['total_amount']);
```

### Integration Tests
- Complete entry/exit flow
- Payment processing
- Gate control integration
- Error handling

## 📚 Additional Resources

- [Main Architecture Guide](./SMART_PARKING_SYSTEM_ARCHITECTURE.md)
- [API Documentation](./API_OVERVIEW.md)
- [Database Schema](./DATABASE_SCHEMA.md)

---

**Service Version**: 1.0.0  
**Last Updated**: January 2025  
**Maintainer**: Smart Parking System Team
