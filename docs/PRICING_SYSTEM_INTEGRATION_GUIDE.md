# Pricing System Integration Guide

## Overview

This document summarizes the complete pricing system implementation for the Smart Parking System. It covers all components, API endpoints, and integration points needed for frontend development.

## 🎯 Core Concept

The pricing system supports **3 payment types**:
1. **Cash** - Direct payment at gate (toll fee)
2. **Bundle** - Subscription-based (free passage)
3. **Exemption** - Free passage (emergency, official vehicles)

## 📋 System Architecture

### Payment Type Priority
```
Vehicle Entry → Check Exemption → Check Bundle → Default to Cash
```

### Decision Flow
```
1. Is Vehicle Exempted? → Exemption (Free)
2. Has Active Bundle? → Bundle (Free)
3. Default → Cash (Toll Fee Required)
```

## 🗄️ Database Changes

### 1. Payment Types (Updated)
```sql
-- Only 3 payment types now
- Cash: Direct payment at gate
- Bundle: Subscription-based payment
- Exemption: Exempted from payment
```

### 2. Vehicle Table (Enhanced)
```sql
ALTER TABLE vehicles ADD COLUMN is_exempted BOOLEAN DEFAULT FALSE;
ALTER TABLE vehicles ADD COLUMN exemption_reason VARCHAR(255) NULL;
ALTER TABLE vehicles ADD COLUMN exemption_expires_at TIMESTAMP NULL;
```

### 3. Vehicle Body Type Prices (Existing)
```sql
-- Links vehicle body types to stations with pricing
CREATE TABLE vehicle_body_type_prices (
    id BIGINT PRIMARY KEY,
    body_type_id INT NOT NULL,
    station_id INT NOT NULL,
    base_price DECIMAL(8,2) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    is_active BOOLEAN DEFAULT TRUE
);
```

## 🔧 Backend Components

### 1. PricingService (`app/Services/PricingService.php`)
**Purpose**: Centralized pricing calculation logic

**Key Methods**:
```php
// Calculate pricing for vehicle entry
calculatePricing(Vehicle $vehicle, Station $station, ?Account $account = null): array

// Determine payment type
determinePaymentType(Vehicle $vehicle, ?Account $account = null): PaymentType

// Get base price for vehicle body type and station
getBasePrice(int $bodyTypeId, int $stationId): ?VehicleBodyTypePrice
```

**Returns**:
```php
[
    'amount' => 75.00,
    'payment_type' => 'Cash',
    'payment_type_id' => 1,
    'requires_payment' => true,
    'description' => 'Toll fee required',
    'base_amount' => 75.00,
    'discount_amount' => 0,
    'total_amount' => 75.00,
    'bundle_subscription_id' => null // if bundle
]
```

### 2. VehiclePassageService (`app/Services/VehiclePassageService.php`)
**Purpose**: Main service for processing vehicle entries

**Key Methods**:
```php
// Process vehicle entry
processVehicleEntry(string $plateNumber, int $gateId, int $operatorId, array $additionalData = []): array

// Quick plate lookup
quickPlateLookup(string $plateNumber): array
```

**Entry Processing Flow**:
```php
1. Find/Create Vehicle
2. Get Account (only for bundle subscribers)
3. Calculate Pricing using PricingService
4. Create Passage Entry
5. Determine Gate Action
6. Return Response
```

### 3. Vehicle Model (Enhanced)
**New Methods**:
```php
// Check if currently exempted
isCurrentlyExempted(): bool

// Set exemption
setExemption(string $reason, ?Carbon $expiresAt = null): bool

// Remove exemption
removeExemption(): bool
```

## 🌐 API Endpoints

### 1. Pricing Endpoints
```php
// Calculate pricing for vehicle entry
POST /api/toll-v1/pricing/calculate
{
    "vehicle_id": 1,
    "station_id": 1,
    "account_id": null // optional
}

// Calculate pricing by plate number
POST /api/toll-v1/pricing/calculate-by-plate
{
    "plate_number": "ABC123",
    "station_id": 1,
    "account_id": null // optional
}

// Get pricing summary for station
GET /api/toll-v1/pricing/station/{stationId}/summary

// Validate pricing configuration
GET /api/toll-v1/pricing/station/{stationId}/validate
```

### 2. Vehicle Body Type Pricing Management
```php
// CRUD operations
GET    /api/toll-v1/vehicle-body-type-prices
POST   /api/toll-v1/vehicle-body-type-prices
GET    /api/toll-v1/vehicle-body-type-prices/{id}
PUT    /api/toll-v1/vehicle-body-type-prices/{id}
DELETE /api/toll-v1/vehicle-body-type-prices/{id}

// Special endpoints
POST   /api/toll-v1/vehicle-body-type-prices/current-price
GET    /api/toll-v1/vehicle-body-type-prices/station/{stationId}
POST   /api/toll-v1/vehicle-body-type-prices/bulk-update
GET    /api/toll-v1/vehicle-body-type-prices/summary
```

### 3. Vehicle Exemption Management
```php
// Set vehicle exemption
POST /api/toll-v1/vehicles/{id}/exempt
{
    "reason": "Emergency vehicle",
    "expires_at": "2024-12-31 23:59:59" // optional
}

// Remove vehicle exemption
DELETE /api/toll-v1/vehicles/{id}/exempt
```

### 4. Gate Control Integration
```php
// Process plate detection (main entry point)
POST /api/toll-v1/gate-control/plate-detection
{
    "plate_number": "ABC123",
    "gate_id": 1,
    "operator_id": 1,
    "additional_data": {
        "account_id": null, // optional
        "notes": "Manual entry"
    }
}

// Quick lookup
POST /api/toll-v1/gate-control/quick-lookup
{
    "plate_number": "ABC123"
}
```

## 🎨 Frontend Integration Guide

### 1. Vehicle Entry Flow

#### Step 1: Plate Detection
```javascript
// When vehicle is detected at gate
const response = await fetch('/api/toll-v1/gate-control/plate-detection', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        plate_number: 'ABC123',
        gate_id: 1,
        operator_id: 1
    })
});

const result = await response.json();
```

#### Step 2: Handle Response
```javascript
if (result.success) {
    const { vehicle, pricing, gate_action } = result.data;
    
    // Display vehicle info
    displayVehicleInfo(vehicle);
    
    // Display pricing info
    displayPricingInfo(pricing);
    
    // Handle gate action
    handleGateAction(gate_action, pricing);
} else {
    // Handle error
    displayError(result.message);
}
```

### 2. Pricing Display Logic

#### Cash Payment
```javascript
if (pricing.payment_type === 'Cash') {
    if (pricing.requires_payment) {
        // Show payment interface
        showPaymentInterface(pricing.total_amount);
        // Gate action: 'require_payment'
    } else {
        // No pricing configured
        showMessage('No pricing configured for this vehicle type');
        // Gate action: 'allow'
    }
}
```

#### Bundle Payment
```javascript
if (pricing.payment_type === 'Bundle') {
    // Show bundle info
    showBundleInfo(pricing.description);
    // Gate action: 'allow'
}
```

#### Exemption
```javascript
if (pricing.payment_type === 'Exemption') {
    // Show exemption info
    showExemptionInfo(pricing.description);
    // Gate action: 'allow'
}
```

### 3. Payment Processing

#### Cash Payment Flow
```javascript
async function processCashPayment(amount) {
    // Collect payment (cash, card, etc.)
    const paymentData = await collectPayment(amount);
    
    // Process payment
    const response = await fetch('/api/toll-v1/vehicle-passages/entry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            plate_number: vehicle.plate_number,
            gate_id: gateId,
            operator_id: operatorId,
            payment_method: paymentData.method,
            payment_amount: paymentData.amount
        })
    });
    
    const result = await response.json();
    
    if (result.success) {
        // Generate receipt
        generateReceipt(result.data.receipt);
        // Open gate
        openGate();
    }
}
```

### 4. Real-time Pricing Calculation

#### Pre-entry Pricing Check
```javascript
async function calculatePricing(plateNumber, stationId) {
    const response = await fetch('/api/toll-v1/pricing/calculate-by-plate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            plate_number: plateNumber,
            station_id: stationId
        })
    });
    
    const result = await response.json();
    
    if (result.success) {
        return result.data.pricing;
    }
    
    return null;
}
```

## 📊 Data Models

### Vehicle Passage Response
```json
{
    "success": true,
    "message": "Vehicle entry processed successfully",
    "data": {
        "id": 1,
        "passage_number": "PASS20241222123456",
        "vehicle_id": 1,
        "account_id": null,
        "bundle_subscription_id": null,
        "payment_type_id": 1,
        "entry_time": "2024-12-22 12:34:56",
        "entry_station_id": 1,
        "entry_gate_id": 1,
        "passage_type": "toll",
        "base_amount": 75.00,
        "discount_amount": 0.00,
        "total_amount": 75.00,
        "status": "active"
    },
    "pricing": {
        "amount": 75.00,
        "payment_type": "Cash",
        "payment_type_id": 1,
        "requires_payment": true,
        "description": "Toll fee required",
        "base_amount": 75.00,
        "discount_amount": 0,
        "total_amount": 75.00
    },
    "gate_action": "require_payment",
    "receipt": null
}
```

### Pricing Response
```json
{
    "success": true,
    "data": {
        "amount": 75.00,
        "payment_type": "Cash",
        "payment_type_id": 1,
        "requires_payment": true,
        "description": "Toll fee required",
        "base_amount": 75.00,
        "discount_amount": 0,
        "total_amount": 75.00
    }
}
```

## 🔄 Integration Workflow

### 1. Entry Gate Process
```
1. Camera detects vehicle
2. Plate recognition captures plate number
3. Frontend calls /api/toll-v1/gate-control/plate-detection
4. Backend processes:
   - Find/create vehicle
   - Check exemption status
   - Check bundle subscription
   - Calculate pricing
   - Determine gate action
5. Frontend receives response with pricing and gate action
6. Frontend displays appropriate interface based on payment type
7. If payment required, collect payment
8. Open gate
```

### 2. Payment Type Handling
```
Cash Payment:
- Display amount
- Collect payment
- Generate receipt
- Open gate

Bundle Payment:
- Display bundle info
- Open gate immediately

Exemption:
- Display exemption reason
- Open gate immediately
```

## 🛠️ Configuration

### 1. Pricing Setup
```php
// Create pricing for vehicle body type and station
POST /api/toll-v1/vehicle-body-type-prices
{
    "body_type_id": 2,
    "station_id": 1,
    "base_price": 75.00,
    "effective_from": "2024-01-01",
    "is_active": true
}
```

### 2. Vehicle Exemption
```php
// Set vehicle exemption
POST /api/toll-v1/vehicles/{id}/exempt
{
    "reason": "Emergency vehicle",
    "expires_at": "2024-12-31 23:59:59"
}
```

## 🧪 Testing

### Test Scenarios
1. **Regular Vehicle (Cash)**: Should show pricing and require payment
2. **Exempted Vehicle**: Should show exemption reason and allow free passage
3. **Bundle Subscriber**: Should show bundle info and allow free passage
4. **No Pricing Configured**: Should show message and allow passage

### Test Commands
```bash
# Test pricing calculation
php artisan tinker --execute="
\$vehicle = \App\Models\Vehicle::first();
\$station = \App\Models\Station::first();
\$pricingService = new \App\Services\PricingService(new \App\Repositories\VehicleBodyTypePriceRepository(new \App\Models\VehicleBodyTypePrice()));
\$pricing = \$pricingService->calculatePricing(\$vehicle, \$station);
echo 'Payment Type: ' . \$pricing['payment_type'] . PHP_EOL;
echo 'Amount: ' . \$pricing['amount'] . PHP_EOL;
"
```

## 📝 Notes

1. **Account Logic**: Only bundle subscribers need accounts. Regular vehicles (cash payment) don't require accounts.
2. **Exemption Priority**: Vehicle exemptions override all other payment types.
3. **Bundle Priority**: Active bundle subscriptions override cash payments.
4. **Pricing Configuration**: Each vehicle body type must have pricing configured per station.
5. **Gate Actions**: 
   - `allow`: Open gate immediately
   - `require_payment`: Wait for payment before opening
   - `deny`: Keep gate closed

## 🚀 Next Steps

1. **Frontend Development**: Implement the UI components based on this guide
2. **Payment Integration**: Add actual payment processing (cash, card, mobile money)
3. **Receipt Generation**: Implement receipt printing/display
4. **Bundle Management**: Add bundle subscription management interface
5. **Reporting**: Add pricing and revenue reports

---

**Last Updated**: December 22, 2024
**Version**: 1.0
**Status**: Production Ready ✅
