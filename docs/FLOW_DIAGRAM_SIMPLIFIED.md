# Simplified Flow Diagram Documentation

This document provides simplified flow diagrams for creating visual representations of the vehicle detection and payment system.

## Flow 1: Vehicle Entry - Main Decision Tree

```
START: Vehicle Detected
    ↓
[Plate Number Captured]
    ↓
[Lookup Vehicle in Database]
    ↓
[Vehicle Found?]
    ├─ NO → [Create New Vehicle]
    └─ YES → [Use Existing Vehicle]
    ↓
[Check for Active Passage]
    ↓
[Has Active Passage?]
    ├─ YES → [DENY ENTRY] → END
    └─ NO → [Continue Processing]
    ↓
[Check Account Status]
    ↓
[Has Account?]
    ├─ NO → [Set as Unregistered]
    │   ├─ Payment Type: Cash
    │   └─ No Discounts
    └─ YES → [Use Account Settings]
        ├─ Check Bundle Subscription
        ├─ Apply Account Discounts
        └─ Use Default Payment Type
    ↓
[Calculate Pricing]
    ↓
[Passage Type Determination]
    ├─ Bundle Active? → [FREE PASSAGE]
    ├─ Exempted? → [EXEMPTED PASSAGE]
    └─ Regular → [TOLL PASSAGE]
    ↓
[Payment Required?]
    ├─ NO (Free/Exempted) → [Create Passage] → [OPEN GATE]
    └─ YES (Toll) → [Payment Processing]
        ↓
        [Payment Method Provided?]
        ├─ NO → [DENY ENTRY] → END
        └─ YES → [Validate Payment Amount]
            ↓
            [Amount Sufficient?]
            ├─ NO → [DENY ENTRY] → END
            └─ YES → [Process Payment]
                ↓
                [Generate Receipt]
                ↓
                [Create Passage]
                ↓
                [OPEN GATE]
                ↓
                END
```

## Flow 2: Bundle Customer Entry

```
START: Vehicle Detected
    ↓
[Plate Number Captured]
    ↓
[Lookup Vehicle]
    ↓
[Find Primary Account]
    ↓
[Check Bundle Subscription]
    ↓
[Bundle Active?]
    ├─ NO → [Regular Entry Flow]
    └─ YES → [Bundle Entry Processing]
        ↓
        [Set Passage Type: FREE]
        ↓
        [Set Amount: 0]
        ↓
        [Link to Bundle Subscription]
        ↓
        [Create Passage]
        ↓
        [OPEN GATE]
        ↓
        END
```

## Flow 3: Vehicle Exit

```
START: Vehicle Detected at Exit
    ↓
[Plate Number Captured]
    ↓
[Lookup Vehicle]
    ↓
[Find Active Passage]
    ↓
[Active Passage Found?]
    ├─ NO → [DENY EXIT] → END
    └─ YES → [Exit Processing]
        ↓
        [Check Receipt Exists]
        ↓
        [Receipt Found?]
        ├─ NO → [DENY EXIT] → END
        └─ YES → [Complete Exit]
            ↓
            [Set Exit Time]
            ↓
            [Set Exit Gate/Station]
            ↓
            [Calculate Duration]
            ↓
            [Update Passage Status]
            ↓
            [OPEN GATE]
            ↓
            END
```

## Flow 4: Payment Processing (Detailed)

```
START: Payment Required
    ↓
[Validate Payment Method]
    ↓
[Payment Method Valid?]
    ├─ NO → [DENY ENTRY]
    └─ YES → [Validate Payment Amount]
        ↓
        [Amount >= Required?]
        ├─ NO → [DENY ENTRY]
        └─ YES → [Process Payment]
            ↓
            [Generate Receipt Number]
            ↓
            [Create Receipt Record]
            ↓
            [Link to Vehicle Passage]
            ↓
            [Record Payment Details]
            ↓
            [Log Transaction]
            ↓
            [Payment Success]
            ↓
            END
```

## Key Decision Points Summary

### Entry Gate Decisions
1. **Vehicle Exists?** → Create or Use Existing
2. **Active Passage?** → Deny if Yes
3. **Has Account?** → Apply Account Benefits
4. **Bundle Active?** → Free Passage
5. **Exempted?** → Free Passage
6. **Payment Required?** → Process Payment
7. **Payment Valid?** → Generate Receipt
8. **Final Decision** → Open or Deny Gate

### Exit Gate Decisions
1. **Vehicle Found?** → Deny if No
2. **Active Passage?** → Deny if No
3. **Receipt Exists?** → Deny if No
4. **Final Decision** → Open or Deny Gate

## Error Scenarios

### Entry Errors
- Vehicle already has active passage
- Payment method not provided
- Insufficient payment amount
- Gate not active
- Invalid gate type for entry

### Exit Errors
- No active passage found
- No receipt found (payment not made)
- Gate not active
- Invalid gate type for exit

## Success Scenarios

### Successful Entry
- Vehicle validated
- Passage created
- Payment processed (if required)
- Receipt generated (if payment made)
- Gate opened

### Successful Exit
- Active passage found
- Receipt verified
- Exit completed
- Gate opened

## Data Flow Summary

### Input Data
- Plate Number
- Gate ID
- Direction (Entry/Exit)
- Payment Method (if required)
- Payment Amount (if required)

### Output Data
- Success/Error Status
- Gate Action (Open/Deny)
- Passage Details
- Receipt Details (if generated)
- Error Message (if failed)

### Database Operations
- Vehicle Lookup/Creation
- Passage Creation/Completion
- Receipt Generation
- Account/Bundle Validation

This simplified documentation provides the essential decision points and actions needed to create clear, readable flow diagrams for the vehicle detection and payment system.
