<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehiclePassage;
use App\Models\Gate;
use App\Models\Station;
use App\Models\VehicleBodyTypePrice;
use App\Models\Receipt;
use App\Models\PaymentType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class TollService
{
    /**
     * Process vehicle entry - simplified toll system
     *
     * @param string $plateNumber
     * @param int $gateId
     * @param int $operatorId
     * @param array $additionalData
     * @return array
     */
    public function processVehicleEntry(string $plateNumber, int $gateId, int $operatorId, array $additionalData = []): array
    {
        try {
            DB::beginTransaction();

            // Step 1: Detect the plate number and find/create vehicle
            $vehicle = $this->findOrCreateVehicle($plateNumber, $additionalData);

            // Step 2: Determine body type price
            $gate = Gate::with('station')->findOrFail($gateId);
            $bodyTypePrice = $this->getBodyTypePrice($vehicle->body_type_id, $gate->station_id);

            if (!$bodyTypePrice) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No pricing found for this vehicle type at this station',
                    'gate_action' => 'deny'
                ];
            }

            // Check if vehicle already has an active passage
            $activePassage = VehiclePassage::where('vehicle_id', $vehicle->id)
                ->whereNull('exit_time')
                ->first();

            if ($activePassage) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Vehicle already has an active passage',
                    'gate_action' => 'deny'
                ];
            }

            // Step 3: Generate passage number
            $passageNumber = 'PASS-' . date('Ymd') . '-' . str_pad(VehiclePassage::count() + 1, 6, '0', STR_PAD_LEFT);
            
            // Get default payment type (Cash)
            $defaultPaymentType = PaymentType::where('name', 'Cash')->first();
            if (!$defaultPaymentType) {
                // Create default payment type if it doesn't exist
                $defaultPaymentType = PaymentType::create([
                    'name' => 'Cash',
                    'description' => 'Cash payment',
                    'is_active' => true
                ]);
            }

            // Step 4: Create passage entry
            $passage = VehiclePassage::create([
                'passage_number' => $passageNumber,
                'vehicle_id' => $vehicle->id,
                'payment_type_id' => $defaultPaymentType->id,
                'entry_time' => now(),
                'entry_operator_id' => $operatorId,
                'entry_gate_id' => $gateId,
                'entry_station_id' => $gate->station_id,
                'passage_type' => 'toll',
                'base_amount' => $bodyTypePrice->base_price,
                'discount_amount' => 0,
                'total_amount' => $bodyTypePrice->base_price, // Set initial total amount
                'notes' => $additionalData['notes'] ?? null,
            ]);

            // Step 5: Log the passage entry
            Log::info('Vehicle entry processed - Simple Toll', [
                'plate_number' => $plateNumber,
                'passage_id' => $passage->id,
                'vehicle_id' => $vehicle->id,
                'gate_id' => $gateId,
                'station_id' => $gate->station_id,
                'body_type_price_per_hour' => $bodyTypePrice->base_price,
                'operator_id' => $operatorId,
                'entry_time' => $passage->entry_time
            ]);

            DB::commit();

            // Step 3: Open the gate
            return [
                'success' => true,
                'message' => 'Vehicle entry processed successfully',
                'gate_action' => 'open',
                'data' => [
                    'passage_id' => $passage->id,
                    'vehicle' => $vehicle,
                    'entry_time' => $passage->entry_time,
                    'price_per_hour' => $bodyTypePrice->base_price
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing vehicle entry - Simple Toll', [
                'plate_number' => $plateNumber,
                'gate_id' => $gateId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error processing vehicle entry: ' . $e->getMessage(),
                'gate_action' => 'deny'
            ];
        }
    }

    /**
     * Process vehicle exit - simplified toll system
     *
     * @param string $plateNumber
     * @param int $gateId
     * @param int $operatorId
     * @param array $additionalData
     * @return array
     */
    public function processVehicleExit(string $plateNumber, int $gateId, int $operatorId, array $additionalData = []): array
    {
        try {
            DB::beginTransaction();

            // Step 5: Detect the plate number
            $vehicle = Vehicle::where('plate_number', $plateNumber)->first();

            if (!$vehicle) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'gate_action' => 'deny'
                ];
            }

            // Find active passage
            $activePassage = VehiclePassage::where('vehicle_id', $vehicle->id)
                ->whereNull('exit_time')
                ->first();

            if (!$activePassage) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No active passage found for this vehicle',
                    'gate_action' => 'deny'
                ];
            }

            // Step 6: Calculate amount based on time spent
            $entryTime = $activePassage->entry_time;
            $exitTime = now();
            $hoursSpent = $entryTime->diffInHours($exitTime, true);
            
            // Calculate hours to charge based on specific rounding rules
            $hoursToCharge = $this->calculateHoursToCharge($hoursSpent);
            $totalAmount = $activePassage->base_amount * $hoursToCharge;

            // Update passage with exit information and calculated amount
            $activePassage->update([
                'exit_time' => $exitTime,
                'exit_operator_id' => $operatorId,
                'exit_gate_id' => $gateId,
                'exit_station_id' => Gate::find($gateId)->station_id,
                'total_amount' => $totalAmount,
                'notes' => $additionalData['notes'] ?? $activePassage->notes,
            ]);

            // Check if payment is confirmed
            $paymentConfirmed = $additionalData['payment_confirmed'] ?? false;

            if (!$paymentConfirmed) {
                // Step 7: Require payment before opening gate
                Log::info('Payment required for vehicle exit', [
                    'plate_number' => $plateNumber,
                    'passage_id' => $activePassage->id,
                    'total_amount' => $totalAmount,
                    'hours_charged' => $hoursToCharge,
                    'operator_id' => $operatorId
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Payment required before exit',
                    'gate_action' => 'require_payment',
                    'data' => [
                        'passage_id' => $activePassage->id,
                        'vehicle' => $vehicle,
                        'total_amount' => $totalAmount,
                        'hours_charged' => $hoursToCharge,
                        'entry_time' => $entryTime,
                        'exit_time' => $exitTime
                    ]
                ];
            }

            // Process payment and generate receipt
            $receipt = $this->processPayment($activePassage, $operatorId, $additionalData);

            // Step 7: Open the gate for exit
            Log::info('Vehicle exit processed - Simple Toll', [
                'plate_number' => $plateNumber,
                'passage_id' => $activePassage->id,
                'total_amount' => $totalAmount,
                'hours_charged' => $hoursToCharge,
                'receipt_id' => $receipt->id ?? null,
                'operator_id' => $operatorId
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Vehicle exit processed successfully',
                'gate_action' => 'open',
                'data' => [
                    'passage_id' => $activePassage->id,
                    'vehicle' => $vehicle,
                    'total_amount' => $totalAmount,
                    'hours_charged' => $hoursToCharge,
                    'entry_time' => $entryTime,
                    'exit_time' => $exitTime,
                    'receipt' => $receipt
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing vehicle exit - Simple Toll', [
                'plate_number' => $plateNumber,
                'gate_id' => $gateId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error processing vehicle exit: ' . $e->getMessage(),
                'gate_action' => 'deny'
            ];
        }
    }

    /**
     * Confirm payment and open gate
     *
     * @param int $passageId
     * @param int $operatorId
     * @param array $paymentData
     * @return array
     */
    public function confirmPayment(int $passageId, int $operatorId, array $paymentData = []): array
    {
        try {
            DB::beginTransaction();

            $passage = VehiclePassage::findOrFail($passageId);

            if ($passage->exit_time) {
                return [
                    'success' => false,
                    'message' => 'Passage already completed',
                    'gate_action' => 'deny'
                ];
            }

            // Process payment and generate receipt
            $receipt = $this->processPayment($passage, $operatorId, $paymentData);

            Log::info('Payment confirmed for vehicle exit', [
                'passage_id' => $passageId,
                'receipt_id' => $receipt->id,
                'amount' => $passage->total_amount,
                'operator_id' => $operatorId
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Payment confirmed, gate will open',
                'gate_action' => 'open',
                'data' => [
                    'passage_id' => $passage->id,
                    'receipt' => $receipt
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error confirming payment', [
                'passage_id' => $passageId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error confirming payment: ' . $e->getMessage(),
                'gate_action' => 'deny'
            ];
        }
    }

    /**
     * Find or create vehicle by plate number
     *
     * @param string $plateNumber
     * @param array $additionalData
     * @return Vehicle
     */
    private function findOrCreateVehicle(string $plateNumber, array $additionalData = []): Vehicle
    {
        $vehicle = Vehicle::where('plate_number', $plateNumber)->first();

        if (!$vehicle) {
            // Require body_type_id for new vehicles
            if (!isset($additionalData['body_type_id'])) {
                throw new Exception('Body type ID is required for new vehicles');
            }
            
            $vehicle = Vehicle::create([
                'plate_number' => $plateNumber,
                'body_type_id' => $additionalData['body_type_id'],
                'make' => $additionalData['make'] ?? null,
                'model' => $additionalData['model'] ?? null,
                'year' => $additionalData['year'] ?? null,
                'color' => $additionalData['color'] ?? null,
                'owner_name' => $additionalData['owner_name'] ?? null,
                'is_registered' => false,
            ]);
        }

        return $vehicle;
    }

    /**
     * Get body type price for station
     *
     * @param int $bodyTypeId
     * @param int $stationId
     * @return VehicleBodyTypePrice|null
     */
    private function getBodyTypePrice(int $bodyTypeId, int $stationId): ?VehicleBodyTypePrice
    {
        return VehicleBodyTypePrice::where('body_type_id', $bodyTypeId)
            ->where('station_id', $stationId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Process payment and generate receipt
     *
     * @param VehiclePassage $passage
     * @param int $operatorId
     * @param array $paymentData
     * @return Receipt
     */
    private function processPayment(VehiclePassage $passage, int $operatorId, array $paymentData = []): Receipt
    {
        $paymentMethod = $paymentData['payment_method'] ?? 'cash';
        $paymentAmount = $paymentData['payment_amount'] ?? $passage->total_amount;

        // Validate payment amount
        if ($paymentAmount < $passage->total_amount) {
            throw new Exception('Payment amount is insufficient');
        }

        // Get cash payment type
        $paymentType = PaymentType::where('name', 'Cash')->first();

        // Create receipt
        $receipt = Receipt::create([
            'vehicle_passage_id' => $passage->id,
            'amount' => $paymentAmount,
            'payment_method' => $paymentMethod,
            'payment_type_id' => $paymentType->id,
            'issued_by' => $operatorId,
            'issued_at' => now(),
            'notes' => $paymentData['receipt_notes'] ?? null,
        ]);

        return $receipt;
    }

    /**
     * Get active passages for monitoring
     *
     * @return array
     */
    public function getActivePassages(): array
    {
        $passages = VehiclePassage::with(['vehicle', 'entryGate', 'exitGate'])
            ->whereNull('exit_time')
            ->orderBy('entry_time', 'desc')
            ->get();

        return [
            'success' => true,
            'data' => $passages,
            'count' => $passages->count()
        ];
    }

    /**
     * Get passage details by ID
     *
     * @param int $passageId
     * @return array
     */
    public function getPassageDetails(int $passageId): array
    {
        $passage = VehiclePassage::with(['vehicle', 'entryGate', 'exitGate', 'receipts'])
            ->find($passageId);

        if (!$passage) {
            return [
                'success' => false,
                'message' => 'Passage not found'
            ];
        }

        return [
            'success' => true,
            'data' => $passage
        ];
    }

    /**
     * Calculate total billable hours based on parking time and smart charging rules.
     *
     * ⚖️ Charging Rules:
     * - Minimum charge — always 1 hour (TSh 2,000)
     *   Even if someone parks for 5 minutes or 30 minutes, they must pay for 1 hour.
     * - Up to 1 hour 30 minutes → Still charge only 1 hour (TSh 2,000)
     *   Small "grace period" makes customers feel treated fairly.
     * - From 1 hour 31 minutes up to 2 hours → Charge double = 2 hours (TSh 4,000)
     * - More than 2 hours → Round up to the next full hour
     *   After 2 hours, charge by each full hour — always rounding up.
     *
     * Examples:
     *  - 07:00 to 07:30 → 0.5 hours → charge 1 hour (TSh 2,000)
     *  - 07:00 to 08:20 → 1.33 hours → charge 1 hour (TSh 2,000)
     *  - 07:00 to 08:40 → 1.66 hours → charge 2 hours (TSh 4,000)
     *  - 07:00 to 09:10 → 2.16 hours → charge 3 hours (TSh 6,000)
     *  - 07:00 to 10:05 → 3.08 hours → charge 4 hours (TSh 8,000)
     *
     * @param float $hoursSpent  Actual number of hours spent (e.g., 1.25 = 1h15m)
     * @return int  Billable hours to charge
     */
    private function calculateHoursToCharge(float $hoursSpent): int
    {
        // Minimum charge — always 1 hour
        if ($hoursSpent <= 0) {
            return 1;
        }

        // Up to 1 hour 30 minutes → Still charge only 1 hour
        if ($hoursSpent <= 1.5) {
            return 1;
        }

        // From 1 hour 31 minutes up to 2 hours → Charge double = 2 hours
        if ($hoursSpent < 2.0) {
            return 2;
        }

        // More than 2 hours → Round up to the next full hour
        return (int) ceil($hoursSpent);
    }
}
