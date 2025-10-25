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

            // Step 3: Create passage entry
            $passage = VehiclePassage::create([
                'vehicle_id' => $vehicle->id,
                'entry_time' => now(),
                'entry_operator_id' => $operatorId,
                'entry_gate_id' => $gateId,
                'entry_station_id' => $gate->station_id,
                'passage_type' => 'toll',
                'base_amount' => $bodyTypePrice->base_price,
                'discount_amount' => 0,
                'total_amount' => 0, // Will be calculated on exit
                'notes' => $additionalData['notes'] ?? null,
            ]);

            // Step 4: Log the passage entry
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
            
            // Minimum 1 hour charge
            $hoursToCharge = max(1, ceil($hoursSpent));
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
            $vehicle = Vehicle::create([
                'plate_number' => $plateNumber,
                'body_type_id' => $additionalData['body_type_id'] ?? 1, // Default to car
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
}
