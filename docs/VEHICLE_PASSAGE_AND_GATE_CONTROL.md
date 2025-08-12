# Vehicle Passage and Gate Control System

This document outlines the comprehensive vehicle passage management and gate control system for the Smart Parking System.

## Overview

The system provides a complete solution for:
- Logging vehicle passages (entry/exit)
- Automatic gate control based on plate number detection
- Real-time monitoring and management
- Emergency gate control capabilities

## Architecture

The system follows a service-based architecture with the following components:

### 1. Models
- `VehiclePassage` - Core passage tracking model
- `Vehicle` - Vehicle information
- `Gate` - Gate configuration and status
- `Station` - Station information

### 2. Repositories
- `VehiclePassageRepository` - Data access layer for passages
- `VehicleRepository` - Vehicle data operations

### 3. Services
- `VehiclePassageService` - Business logic for passage management
- `GateControlService` - Gate control and plate detection logic

### 4. Controllers
- `VehiclePassageController` - REST API for passage management
- `GateControlController` - Gate control operations

## Vehicle Passage Management

### Core Features

#### 1. Entry Processing with Payment
- Automatic vehicle creation if not exists
- Account and bundle subscription detection
- Pricing calculation
- Payment processing at entry
- Receipt generation
- Passage type determination (toll/free/exempted)
- Gate action determination

#### 2. Exit Processing
- Active passage validation
- Duration calculation
- No payment required (already paid at entry)
- Gate action based on existing receipt

#### 3. Passage Types
- **Toll**: Standard paid passage
- **Free**: No charge (bundle subscriptions)
- **Exempted**: Special exemptions

### API Endpoints

#### Vehicle Passages

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/vehicle-passages` | List all passages with filters |
| POST | `/vehicle-passages` | Create new passage entry |
| GET | `/vehicle-passages/{id}` | Get passage details |
| PUT | `/vehicle-passages/{id}` | Update passage |
| DELETE | `/vehicle-passages/{id}` | Delete passage |
| POST | `/vehicle-passages/entry` | Process vehicle entry |
| POST | `/vehicle-passages/exit` | Process vehicle exit |
| POST | `/vehicle-passages/quick-lookup` | Quick plate lookup |
| GET | `/vehicle-passages/passage/{number}` | Get by passage number |
| GET | `/vehicle-passages/vehicle/{id}` | Get passages by vehicle |
| GET | `/vehicle-passages/station/{id}` | Get passages by station |
| GET | `/vehicle-passages/active/list` | Get active passages |
| GET | `/vehicle-passages/completed/list` | Get completed passages |
| GET | `/vehicle-passages/statistics` | Get passage statistics |
| PUT | `/vehicle-passages/{id}/status` | Update passage status |
| GET | `/vehicle-passages/search` | Search passages |

## Receipt Management System

### Core Features

#### 1. Automatic Receipt Generation
- Generated automatically when payment is processed at entry
- Unique receipt numbers (RCPT + date + random string)
- Links to vehicle passage and payment details
- Printable format for physical receipts

#### 2. Receipt Operations
- View receipt details
- Search receipts by number or vehicle
- Get receipts by date range
- Get receipts by payment method
- Receipt statistics and reporting
- Print receipt functionality

### API Endpoints

#### Receipts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/receipts` | List all receipts with filters |
| GET | `/receipts/{id}` | Get receipt details |
| PUT | `/receipts/{id}` | Update receipt |
| DELETE | `/receipts/{id}` | Delete receipt |
| GET | `/receipts/number/{number}` | Get by receipt number |
| GET | `/receipts/vehicle-passage/{id}` | Get receipts by vehicle passage |
| GET | `/receipts/statistics` | Get receipt statistics |
| GET | `/receipts/recent` | Get recent receipts |
| GET | `/receipts/total-revenue` | Get total revenue |
| GET | `/receipts/search` | Search receipts |
| GET | `/receipts/print/{id}` | Get printable receipt data |
| GET | `/receipts/by-date-range` | Get receipts by date range |
| GET | `/receipts/by-payment-method` | Get receipts by payment method |

## Gate Control System

### Core Features

#### 1. Plate Detection Processing
- Real-time plate number validation
- Vehicle lookup and creation
- Account and bundle verification
- Gate action determination (open/close/deny)

#### 2. Gate Control Actions
- **Open**: Allow vehicle passage
- **Close**: Deny vehicle passage
- **Deny**: Keep gate closed

#### 3. Gate Types
- **Entry**: Entry-only gates
- **Exit**: Exit-only gates
- **Both**: Entry and exit gates

### API Endpoints

#### Gate Control

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/gate-control/plate-detection` | Process plate detection |
| POST | `/gate-control/quick-lookup` | Quick plate lookup |
| POST | `/gate-control/manual` | Manual gate control |
| POST | `/gate-control/emergency` | Emergency gate control |
| GET | `/gate-control/gates/{id}/status` | Get gate status |
| GET | `/gate-control/gates/active` | Get active gates |
| GET | `/gate-control/gates/{id}/history` | Get gate control history |
| GET | `/gate-control/gates/{id}/test-connection` | Test gate connection |
| GET | `/gate-control/monitoring-dashboard` | Get monitoring dashboard |

## Usage Examples

### 1. Vehicle Entry Processing with Payment

```bash
curl -X POST /api/toll-v1/vehicle-passages/entry \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "plate_number": "ABC123",
    "gate_id": 1,
    "body_type_id": 1,
    "make": "Toyota",
    "model": "Camry",
    "year": 2020,
    "color": "White",
    "owner_name": "John Doe",
    "account_id": 1,
    "payment_type_id": 1,
    "passage_type": "toll",
    "payment_method": "cash",
    "payment_amount": 10.00,
    "receipt_notes": "Regular customer",
    "notes": "Entry with payment"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Vehicle entry processed successfully",
  "data": {
    "id": 1,
    "passage_number": "PASS20241201123456",
    "vehicle_id": 1,
    "account_id": 1,
    "payment_type_id": 1,
    "entry_time": "2024-12-01T12:34:56Z",
    "entry_gate_id": 1,
    "entry_station_id": 1,
    "base_amount": 10.00,
    "discount_amount": 0.00,
    "total_amount": 10.00,
    "passage_type": "toll",
    "status": "active"
  },
  "gate_action": "open",
  "receipt": {
    "id": 1,
    "receipt_number": "RCPT20241201123456",
    "vehicle_passage_id": 1,
    "amount": 10.00,
    "payment_method": "cash",
    "issued_by": 1,
    "issued_at": "2024-12-01T12:34:56Z",
    "notes": "Regular customer"
  }
}
```

### 2. Gate Control with Plate Detection

```bash
curl -X POST /api/toll-v1/gate-control/plate-detection \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "plate_number": "ABC123",
    "gate_id": 1,
    "direction": "entry",
    "account_id": 1,
    "payment_type_id": 1
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Vehicle entry processed successfully",
  "gate_action": "open",
  "data": {
    "passage": {
      "id": 1,
      "passage_number": "PASS20241201123456",
      "vehicle": {
        "id": 1,
        "plate_number": "ABC123",
        "make": "Toyota",
        "model": "Camry"
      },
      "account": {
        "id": 1,
        "name": "John Doe"
      }
    }
  },
  "timestamp": "2024-12-01T12:34:56Z"
}
```

### 3. Manual Gate Control

```bash
curl -X POST /api/toll-v1/gate-control/manual \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "gate_id": 1,
    "action": "open",
    "reason": "Maintenance access"
  }'
```

### 4. Emergency Gate Control

```bash
curl -X POST /api/toll-v1/gate-control/emergency \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "gate_id": 1,
    "action": "open",
    "emergency_reason": "Fire emergency evacuation"
  }'
```

### 5. Receipt Operations

#### Get Receipt by Number
```bash
curl -X GET /api/toll-v1/receipts/number/RCPT20241201123456 \
  -H "Authorization: Bearer {token}"
```

#### Print Receipt
```bash
curl -X GET /api/toll-v1/receipts/print/1 \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Printable receipt data generated successfully",
  "data": {
    "receipt_number": "RCPT20241201123456",
    "issued_at": "2024-12-01 12:34:56",
    "amount": 10.00,
    "payment_method": "cash",
    "vehicle": {
      "plate_number": "ABC123",
      "make": "Toyota",
      "model": "Camry",
      "body_type": "Sedan"
    },
    "station": {
      "name": "Main Station",
      "gate": "Entry Gate 1"
    },
    "operator": "John Operator",
    "notes": "Regular customer",
    "passage_number": "PASS20241201123456"
  }
}
```

#### Get Receipt Statistics
```bash
curl -X GET "/api/toll-v1/receipts/statistics?start_date=2024-12-01&end_date=2024-12-31" \
  -H "Authorization: Bearer {token}"
```

## Gate Control Logic

### Entry Gate Logic

1. **Plate Detection** → Vehicle lookup
2. **Active Passage Check** → Deny if already has active passage
3. **Account/Bundle Check** → Determine pricing and passage type
4. **Gate Action** → Open if allowed, deny if not

### Exit Gate Logic

1. **Plate Detection** → Vehicle lookup
2. **Active Passage Check** → Deny if no active passage
3. **Receipt Verification** → Check if receipt exists (payment already made at entry)
4. **Gate Action** → Open if receipt exists, deny if no receipt

### Pricing Logic

1. **Base Price** → Vehicle body type × Station pricing
2. **Account Discount** → Apply account discount percentage
3. **Bundle Check** → Free if active bundle subscription
4. **Exemption Check** → Free if exempted

## Monitoring and Dashboard

### Real-time Monitoring

- Active passages count
- Gate status monitoring
- Vehicle queue management
- System health status

### Dashboard Features

- Passage statistics
- Revenue tracking
- Gate utilization
- Error monitoring
- Emergency alerts

## Security Features

### Authentication
- All endpoints require authentication
- Operator tracking for all actions
- Audit logging for compliance

### Authorization
- Role-based access control
- Gate-specific permissions
- Emergency override capabilities

### Data Integrity
- Transaction-based operations
- Validation at multiple levels
- Error handling and recovery

## Integration Points

### Hardware Integration
- Gate control signals via cache
- Status monitoring
- Connection testing
- Emergency protocols

### External Systems
- Payment gateways
- Camera systems
- Traffic management
- Emergency services

## Error Handling

### Common Scenarios
- Vehicle not found → Create new vehicle
- Active passage exists → Deny entry
- No active passage → Deny exit
- Gate inactive → Deny all operations
- Payment required → Keep gate closed

### Recovery Procedures
- Manual override capabilities
- Emergency protocols
- System restart procedures
- Data recovery processes

## Performance Considerations

### Optimization
- Caching for frequent lookups
- Database indexing
- Connection pooling
- Response time monitoring

### Scalability
- Horizontal scaling support
- Load balancing
- Database sharding
- Microservice architecture ready

## Testing

### Unit Tests
- Service layer testing
- Repository testing
- Validation testing

### Integration Tests
- API endpoint testing
- Database integration
- Gate control simulation

### Load Testing
- High-volume scenarios
- Concurrent access
- Performance benchmarks

## Deployment

### Requirements
- Laravel 10+
- MySQL/PostgreSQL
- Redis for caching
- Queue system for background jobs

### Configuration
- Environment variables
- Database connections
- Cache settings
- Logging configuration

## Maintenance

### Regular Tasks
- Database optimization
- Log rotation
- Cache clearing
- Performance monitoring

### Emergency Procedures
- System shutdown
- Manual gate control
- Data backup
- Recovery procedures

This system provides a robust, scalable solution for vehicle passage management and gate control, suitable for production deployment in smart parking environments.
