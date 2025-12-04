# Parking Fee Calculation Documentation

## Overview

The parking fee calculation system uses a **time-based pricing model** where the total amount charged depends on:
1. **Base Price** - The hourly rate for the vehicle type at the station
2. **Duration** - How long the vehicle stayed in the parking facility
3. **Smart Charging Rules** - Specific rounding rules applied to calculate billable hours

## Base Price

The base price is determined by:
- **Vehicle Body Type** (e.g., Motorcycle, Small Vehicle, Large Vehicle)
- **Station** where the vehicle entered

This base price represents the **cost per hour** for that specific vehicle type at that station.

**Example:**
- Motorcycle at Station A: Tsh. 500/hour
- Small Vehicle at Station A: Tsh. 2,000/hour
- Large Vehicle at Station A: Tsh. 3,000/hour

## Fee Calculation Formula

```
Total Amount = Base Price × Hours to Charge
```

Where **Hours to Charge** is calculated using smart charging rules (see below).

## Smart Charging Rules

The system uses intelligent rounding rules to calculate billable hours:

### Rule 1: Minimum Charge
- **Always charge for at least 1 hour**
- Even if a vehicle parks for 5 minutes or 30 minutes, they pay for 1 hour
- **Formula:** `Hours to Charge = 1` (if duration ≤ 0 hours)

### Rule 2: Grace Period (Up to 1.5 hours)
- **Up to 1 hour 30 minutes → Charge only 1 hour**
- This provides a small "grace period" for customers
- **Formula:** `Hours to Charge = 1` (if duration ≤ 1.5 hours)

### Rule 3: Double Charge (1.5 to 2 hours)
- **From 1 hour 31 minutes up to 2 hours → Charge 2 hours**
- **Formula:** `Hours to Charge = 2` (if 1.5 < duration < 2.0 hours)

### Rule 4: Round Up (More than 2 hours)
- **More than 2 hours → Round up to the next full hour**
- Always rounds up to the nearest hour
- **Formula:** `Hours to Charge = ceil(duration)` (if duration ≥ 2.0 hours)

## Calculation Examples

### Example 1: Short Stay (30 minutes)
- **Base Price:** Tsh. 2,000/hour
- **Duration:** 0.5 hours (30 minutes)
- **Hours to Charge:** 1 (Rule 1: Minimum charge)
- **Total Amount:** Tsh. 2,000 × 1 = **Tsh. 2,000**

### Example 2: Within Grace Period (1 hour 20 minutes)
- **Base Price:** Tsh. 2,000/hour
- **Duration:** 1.33 hours (1h 20m)
- **Hours to Charge:** 1 (Rule 2: Grace period)
- **Total Amount:** Tsh. 2,000 × 1 = **Tsh. 2,000**

### Example 3: Double Charge Period (1 hour 40 minutes)
- **Base Price:** Tsh. 2,000/hour
- **Duration:** 1.66 hours (1h 40m)
- **Hours to Charge:** 2 (Rule 3: Double charge)
- **Total Amount:** Tsh. 2,000 × 2 = **Tsh. 4,000**

### Example 4: Round Up (2 hours 10 minutes)
- **Base Price:** Tsh. 2,000/hour
- **Duration:** 2.16 hours (2h 10m)
- **Hours to Charge:** 3 (Rule 4: Round up)
- **Total Amount:** Tsh. 2,000 × 3 = **Tsh. 6,000**

### Example 5: Long Stay (3 hours 5 minutes)
- **Base Price:** Tsh. 2,000/hour
- **Duration:** 3.08 hours (3h 5m)
- **Hours to Charge:** 4 (Rule 4: Round up)
- **Total Amount:** Tsh. 2,000 × 4 = **Tsh. 8,000**

## Summary Table

| Duration | Hours to Charge | Example (Base: Tsh. 2,000) |
|----------|----------------|----------------------------|
| 0 - 1.5 hours | 1 hour | Tsh. 2,000 |
| 1.5 - 2.0 hours | 2 hours | Tsh. 4,000 |
| 2.0 - 3.0 hours | 3 hours | Tsh. 6,000 |
| 3.0 - 4.0 hours | 4 hours | Tsh. 8,000 |
| 4.0 - 5.0 hours | 5 hours | Tsh. 10,000 |
| ... | ... | ... |

## Implementation

The calculation is implemented in:
- **Backend:** `VehiclePassageRepository::calculateHoursToCharge()`
- **Backend:** `TollService::calculateHoursToCharge()`
- **Calculation occurs:** When a vehicle exits (exit time is recorded)

## Entry vs Exit Pricing

### Entry Time
- Base price is set at entry
- Initial `total_amount` = `base_amount` (1 hour charge)
- This is a placeholder until exit

### Exit Time
- Duration is calculated: `exit_time - entry_time`
- Hours to charge are calculated using smart rules
- `total_amount` is recalculated: `base_amount × hours_to_charge`
- Final amount is updated in the database

## Notes

1. **Base Price** is set per vehicle type and station combination
2. **Duration** is calculated in hours (decimal format, e.g., 1.5 = 1 hour 30 minutes)
3. **Rounding** always favors the parking facility (rounds up)
4. **Minimum charge** ensures revenue even for very short stays
5. **Grace period** (up to 1.5 hours) provides customer-friendly pricing

## API Endpoints

- **Entry:** `POST /api/toll-v1/vehicle-passages/entry`
  - Sets initial `base_amount` and `total_amount` (1 hour)
  
- **Exit:** `POST /api/toll-v1/vehicle-passages/exit`
  - Recalculates `total_amount` based on actual duration
  - Updates the passage record with final amount

## Database Fields

- `base_amount` - The hourly rate (set at entry)
- `total_amount` - Final amount charged (calculated at exit)
- `entry_time` - When vehicle entered
- `exit_time` - When vehicle exited
- `duration_minutes` - Total duration in minutes (calculated at exit)

