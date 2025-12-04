# Vehicle Detection and Payment Flow Documentation

This document outlines the complete flow from vehicle detection to gate opening, payment processing, and receipt generation. Use this documentation to create flow diagrams for different scenarios.

## System Overview

The Smart Parking System processes vehicles through the following main flows:
1. **Regular Vehicle Entry** - Payment at entry with receipt
2. **Bundle Customer Entry** - Free entry with bundle validation
3. **Exempted Vehicle Entry** - Free entry for exempted vehicles
4. **Vehicle Exit** - Simple exit validation

---

## Flow 1: Regular Vehicle Entry (Payment at Entry)

### Start: Vehicle Detection
```
1. Camera detects vehicle approaching gate
2. Plate number recognition system captures plate number
3. System triggers plate detection API call
```

### Step 1: Plate Detection Processing
```
Input: Plate number, Gate ID, Direction (entry)
Action: POST /api/toll-v1/gate-control/plate-detection
```

**System Actions:**
- Validate gate exists and is active
- Check if gate is configured for entry
- Lookup vehicle in database
- If vehicle not found → Create new vehicle record
- Check for active passages (deny if already has active passage)

### Step 2: Vehicle and Account Validation
```
Decision Point: Does vehicle have an account?
├─ YES: Use account information
│  ├─ Check for active bundle subscription
│  ├─ Apply account discounts
│  └─ Determine payment type
└─ NO: Use default settings
    ├─ Set as unregistered vehicle
    ├─ Use cash payment type
    └─ No discounts applied
```

### Step 3: Pricing Calculation
```
Action: Calculate passage pricing
├─ Base Price: Vehicle body type × Station pricing
├─ Account Discount: Apply account discount percentage
├─ Bundle Check: Free if active bundle subscription
├─ Exemption Check: Free if exempted
└─ Final Amount: Base - Discount (minimum 0)
```

### Step 4: Payment Processing
```
Decision Point: Is payment required?
├─ YES (Toll passage with amount > 0):
│  ├─ Validate payment method provided
│  ├─ Validate payment amount >= total amount
│  ├─ Process payment
│  ├─ Generate receipt
│  └─ Log payment transaction
└─ NO (Free/Exempted):
    ├─ Set passage type to 'free' or 'exempted'
    └─ No payment processing needed
```

### Step 5: Receipt Generation
```
Action: Generate receipt (if payment made)
├─ Create unique receipt number (RCPT + date + random)
├─ Link receipt to vehicle passage
├─ Record payment method and amount
├─ Set issued by operator
├─ Set issued timestamp
└─ Store receipt in database
```

### Step 6: Gate Control Decision
```
Decision Point: Should gate open?
├─ YES (Allow entry):
│  ├─ Passage created successfully
│  ├─ Payment processed (if required)
│  ├─ Receipt generated (if payment made)
│  ├─ Send "open" command to gate hardware
│  └─ Log gate action
└─ NO (Deny entry):
    ├─ Send "deny" command to gate hardware
    ├─ Log denial reason
    └─ Return error message
```

### Step 7: Response to Client
```
Output: API Response
├─ Success: true/false
├─ Message: Status description
├─ Gate Action: "open" / "deny"
├─ Data: Passage details
├─ Receipt: Receipt details (if generated)
└─ Vehicle: Vehicle information
```

---

## Flow 2: Bundle Customer Entry (Free Entry)

### Start: Vehicle Detection
```
1. Camera detects vehicle approaching gate
2. Plate number recognition system captures plate number
3. System triggers plate detection API call
```

### Step 1: Plate Detection Processing
```
Input: Plate number, Gate ID, Direction (entry)
Action: POST /api/toll-v1/gate-control/plate-detection
```

### Step 2: Account and Bundle Validation
```
Action: Check account and bundle status
├─ Find vehicle's primary account
├─ Check for active bundle subscription
│  ├─ Bundle start date <= current date
│  ├─ Bundle end date >= current date
│  └─ Bundle status = 'active'
└─ If active bundle found:
    ├─ Set passage type to 'free'
    ├─ Set total amount to 0
    └─ No payment required
```

### Step 3: Passage Creation
```
Action: Create free passage
├─ Generate passage number
├─ Set passage type to 'free'
├─ Set amounts to 0
├─ Link to bundle subscription
└─ No receipt generation needed
```

### Step 4: Gate Control
```
Action: Open gate
├─ Send "open" command to gate hardware
├─ Log bundle usage
└─ Return success response
```

---

## Flow 3: Exempted Vehicle Entry

### Start: Vehicle Detection
```
1. Camera detects vehicle approaching gate
2. Plate number recognition system captures plate number
3. System triggers plate detection API call
```

### Step 1: Exemption Check
```
Action: Check exemption status
├─ Check if vehicle is exempted
├─ Check if account is exempted
├─ Check manual exemption flag
└─ If exempted:
    ├─ Set passage type to 'exempted'
    ├─ Set total amount to 0
    └─ Record exemption reason
```

### Step 2: Passage Creation
```
Action: Create exempted passage
├─ Generate passage number
├─ Set passage type to 'exempted'
├─ Set amounts to 0
├─ Record exemption reason
└─ No payment or receipt needed
```

### Step 3: Gate Control
```
Action: Open gate
├─ Send "open" command to gate hardware
├─ Log exemption usage
└─ Return success response
```

---

## Flow 4: Vehicle Exit

### Start: Vehicle Detection
```
1. Camera detects vehicle approaching exit gate
2. Plate number recognition system captures plate number
3. System triggers plate detection API call
```

### Step 1: Exit Validation
```
Input: Plate number, Gate ID, Direction (exit)
Action: POST /api/toll-v1/gate-control/plate-detection
```

### Step 2: Active Passage Check
```
Action: Find active passage
├─ Lookup vehicle in database
├─ Find active passage (no exit time)
├─ If no active passage found:
│  ├─ Deny exit
│  └─ Return error message
└─ If active passage found:
    ├─ Continue to exit processing
    └─ Validate passage details
```

### Step 3: Exit Processing
```
Action: Complete passage exit
├─ Set exit time to current timestamp
├─ Set exit operator ID
├─ Set exit gate ID
├─ Set exit station ID
├─ Calculate duration (exit - entry time)
└─ Update passage status
```

### Step 4: Receipt Verification
```
Action: Check receipt exists
├─ Look for receipt linked to passage
├─ If receipt exists:
│  ├─ Payment already made at entry
│  └─ Allow exit
└─ If no receipt:
    ├─ Deny exit (payment required)
    └─ Return error message
```

### Step 5: Gate Control
```
Decision Point: Should gate open?
├─ YES (Allow exit):
│  ├─ Passage completed successfully
│  ├─ Send "open" command to gate hardware
│  └─ Log exit action
└─ NO (Deny exit):
    ├─ Send "deny" command to gate hardware
    ├─ Log denial reason
    └─ Return error message
```

---

## Error Handling Flows

### Error 1: Vehicle Already Has Active Passage
```
Condition: Vehicle detected at entry with existing active passage
Action:
├─ Deny entry
├─ Send "deny" command to gate
├─ Log error: "Vehicle already has active passage"
└─ Return error response
```

### Error 2: No Active Passage for Exit
```
Condition: Vehicle detected at exit with no active passage
Action:
├─ Deny exit
├─ Send "deny" command to gate
├─ Log error: "No active passage found for vehicle"
└─ Return error response
```

### Error 3: Insufficient Payment
```
Condition: Payment amount less than required amount
Action:
├─ Deny entry
├─ Send "deny" command to gate
├─ Log error: "Payment amount is insufficient"
└─ Return error response
```

### Error 4: Gate Not Active
```
Condition: Gate is inactive or not found
Action:
├─ Deny entry/exit
├─ Send "deny" command to gate
├─ Log error: "Gate is not active"
└─ Return error response
```

---

## Data Flow Summary

### Input Data
```
Vehicle Detection:
├─ Plate number (string)
├─ Gate ID (integer)
├─ Direction (entry/exit)
├─ Timestamp
└─ Optional: Vehicle details (make, model, color)

Payment Data (if required):
├─ Payment method (string)
├─ Payment amount (decimal)
├─ Receipt notes (string)
└─ Operator ID (integer)
```

### Output Data
```
Success Response:
├─ Success flag (boolean)
├─ Message (string)
├─ Gate action (open/deny)
├─ Passage data (object)
├─ Receipt data (object, if generated)
├─ Vehicle data (object)
└─ Timestamp

Error Response:
├─ Success flag (false)
├─ Error message (string)
├─ Gate action (deny)
├─ Error details (object)
└─ Timestamp
```

### Database Operations
```
Vehicle Operations:
├─ Vehicle lookup by plate number
├─ Vehicle creation (if not exists)
└─ Vehicle update

Passage Operations:
├─ Passage creation
├─ Passage completion (exit)
├─ Passage status update
└─ Passage lookup

Receipt Operations:
├─ Receipt generation
├─ Receipt lookup
└─ Receipt statistics

Account Operations:
├─ Account lookup
├─ Bundle subscription check
└─ Discount calculation
```

---

## Hardware Integration Points

### Gate Control
```
Gate Commands:
├─ "open" - Open gate barrier
├─ "close" - Close gate barrier
├─ "deny" - Keep gate closed
└─ "emergency" - Emergency override

Gate Status:
├─ Gate active/inactive
├─ Gate type (entry/exit/both)
├─ Connection status
└─ Hardware status
```

### Camera Integration
```
Plate Recognition:
├─ Image capture
├─ OCR processing
├─ Plate number extraction
└─ Confidence score
```

### Payment Terminal Integration
```
Payment Processing:
├─ Payment method validation
├─ Amount validation
├─ Transaction processing
└─ Receipt printing
```

---

## Monitoring and Logging

### System Logs
```
Entry Logs:
├─ Vehicle detection timestamp
├─ Plate number recognized
├─ Gate ID and direction
├─ Processing steps
├─ Final decision
└─ Response time

Payment Logs:
├─ Payment method used
├─ Amount processed
├─ Receipt generated
├─ Operator ID
└─ Transaction status

Error Logs:
├─ Error type and message
├─ Vehicle and gate details
├─ Processing step where error occurred
├─ Stack trace
└─ Resolution action
```

### Performance Metrics
```
Response Times:
├─ Plate detection to response
├─ Payment processing time
├─ Receipt generation time
└─ Gate command execution time

Throughput:
├─ Vehicles per hour
├─ Successful entries/exits
├─ Error rates
└─ Payment success rates
```

This documentation provides a comprehensive overview of all the flows in the vehicle detection and payment system, making it easy to create detailed flow diagrams for different scenarios.
