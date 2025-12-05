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
use Carbon\Carbon;

class TollService
{
    /**
     * Process vehicle entry - DAILY FLAT-RATE PRICING
     * 
     * Pricing Rules:
     * - Pay ONCE at entry for the entire day (based on body type)
     * - Same-day re-entry is FREE (already paid today)
     * - Exit is FREE (no payment required)
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

            // Step 1: Find or create vehicle
            $vehicle = $this->findOrCreateVehicle($plateNumber, $additionalData);

            // Step 2: Get gate and station info
            $gate = Gate::with('station')->findOrFail($gateId);
            
            // Step 3: Get daily price for this body type
            $bodyTypePrice = $this->getBodyTypePrice($vehicle->body_type_id, $gate->station_id);

            if (!$bodyTypePrice) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No pricing found for this vehicle type at this station',
                    'gate_action' => 'deny'
                ];
            }

            // Step 4: Check if vehicle already has an active passage (still inside)
            $activePassage = VehiclePassage::where('vehicle_id', $vehicle->id)
                ->whereNull('exit_time')
                ->first();

            if ($activePassage) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Vehicle is already inside (has active passage)',
                    'gate_action' => 'deny'
                ];
            }

            // Step 5: Check if vehicle already PAID today (same-day re-entry = FREE)
            $alreadyPaidToday = $this->hasVehiclePaidToday($vehicle->id, $gate->station_id);
            
            $dailyPrice = $bodyTypePrice->base_price;
            $amountToCharge = $alreadyPaidToday ? 0 : $dailyPrice;
            $paymentRequired = !$alreadyPaidToday;

            // Step 6: Generate passage number
            $passageNumber = 'PASS-' . date('Ymd') . '-' . str_pad(VehiclePassage::whereDate('created_at', today())->count() + 1, 6, '0', STR_PAD_LEFT);
            
            // Get payment type
            $paymentType = PaymentType::where('name', 'Cash')->first();
            if (!$paymentType) {
                $paymentType = PaymentType::create([
                    'name' => 'Cash',
                    'description' => 'Cash payment',
                    'is_active' => true
                ]);
            }

            // Step 7: Create passage entry
            $passage = VehiclePassage::create([
                'passage_number' => $passageNumber,
                'vehicle_id' => $vehicle->id,
                'payment_type_id' => $paymentType->id,
                'entry_time' => now(),
                'entry_operator_id' => $operatorId,
                'entry_gate_id' => $gateId,
                'entry_station_id' => $gate->station_id,
                'passage_type' => $alreadyPaidToday ? 'reentry' : 'toll',
                'base_amount' => $dailyPrice,
                'discount_amount' => $alreadyPaidToday ? $dailyPrice : 0,
                'total_amount' => $amountToCharge,
                'is_paid' => !$paymentRequired, // Mark as paid if re-entry (free)
                'paid_at' => $alreadyPaidToday ? now() : null,
                'notes' => $alreadyPaidToday ? 'Same-day re-entry (free)' : ($additionalData['notes'] ?? null),
            ]);

            // Step 8: If payment required, create receipt at ENTRY
            $receipt = null;
            if ($paymentRequired) {
                // Payment is required - operator must confirm payment
                Log::info('Payment required at entry - Daily Rate', [
                    'plate_number' => $plateNumber,
                    'passage_id' => $passage->id,
                    'daily_price' => $dailyPrice,
                    'operator_id' => $operatorId
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Payment required at entry',
                    'gate_action' => 'require_payment',
                    'requires_payment' => true,
                    'data' => [
                        'passage_id' => $passage->id,
                        'passage_number' => $passageNumber,
                        'vehicle' => $vehicle->load('bodyType'),
                        'entry_time' => $passage->entry_time,
                        'daily_price' => $dailyPrice,
                        'amount_to_pay' => $amountToCharge,
                        'is_reentry' => false,
                        'pricing_type' => 'daily'
                    ]
                ];
            }

            // Free re-entry - just open gate
            Log::info('Vehicle entry processed - Same-day re-entry (FREE)', [
                'plate_number' => $plateNumber,
                'passage_id' => $passage->id,
                'vehicle_id' => $vehicle->id,
                'operator_id' => $operatorId
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Same-day re-entry - No payment required',
                'gate_action' => 'open',
                'requires_payment' => false,
                'data' => [
                    'passage_id' => $passage->id,
                    'passage_number' => $passageNumber,
                    'vehicle' => $vehicle->load('bodyType'),
                    'entry_time' => $passage->entry_time,
                    'daily_price' => $dailyPrice,
                    'amount_to_pay' => 0,
                    'is_reentry' => true,
                    'pricing_type' => 'daily'
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing vehicle entry', [
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
     * Confirm payment at entry and generate receipt
     *
     * @param int $passageId
     * @param int $operatorId
     * @param array $paymentData
     * @return array
     */
    public function confirmEntryPayment(int $passageId, int $operatorId, array $paymentData = []): array
    {
        try {
            DB::beginTransaction();

            $passage = VehiclePassage::with('vehicle')->findOrFail($passageId);

            if ($passage->is_paid) {
                DB::commit();
                return [
                    'success' => true,
                    'message' => 'Already paid',
                    'gate_action' => 'open',
                    'data' => [
                        'passage_id' => $passage->id,
                        'vehicle' => $passage->vehicle
                    ]
                ];
            }

            // Process payment and generate receipt
            $receipt = $this->processPayment($passage, $operatorId, $paymentData);

            // Mark passage as paid
            $passage->update([
                'is_paid' => true,
                'paid_at' => now(),
            ]);

            Log::info('Entry payment confirmed - Daily Rate', [
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
                    'vehicle' => $passage->vehicle,
                    'receipt' => $receipt
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error confirming entry payment', [
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
     * Process vehicle exit - NO PAYMENT REQUIRED (already paid at entry)
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

            // Find vehicle
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

            // Check if paid (should always be paid for daily rate, but check anyway)
            if (!$activePassage->is_paid && $activePassage->total_amount > 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Vehicle has unpaid entry fee',
                    'gate_action' => 'deny',
                    'data' => [
                        'passage_id' => $activePassage->id,
                        'amount_due' => $activePassage->total_amount
                    ]
                ];
            }

            // Calculate duration for records
            $entryTime = $activePassage->entry_time;
            $exitTime = now();
            $duration = $entryTime->diff($exitTime);

            // Update passage with exit information
            $activePassage->update([
                'exit_time' => $exitTime,
                'exit_operator_id' => $operatorId,
                'exit_gate_id' => $gateId,
                'exit_station_id' => Gate::find($gateId)->station_id,
                'notes' => $additionalData['notes'] ?? $activePassage->notes,
            ]);

            Log::info('Vehicle exit processed - Daily Rate (No payment at exit)', [
                'plate_number' => $plateNumber,
                'passage_id' => $activePassage->id,
                'duration' => $duration->format('%H:%I:%S'),
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
                    'entry_time' => $entryTime,
                    'exit_time' => $exitTime,
                    'duration' => $duration->format('%H:%I:%S'),
                    'amount_paid' => $activePassage->total_amount,
                    'pricing_type' => 'daily'
                ]
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing vehicle exit', [
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
     * Check if vehicle has already paid today at this station
     *
     * @param int $vehicleId
     * @param int $stationId
     * @return bool
     */
    private function hasVehiclePaidToday(int $vehicleId, int $stationId): bool
    {
        return VehiclePassage::where('vehicle_id', $vehicleId)
            ->where('entry_station_id', $stationId)
            ->whereDate('entry_time', today())
            ->where('is_paid', true)
            ->where('total_amount', '>', 0) // Exclude free re-entries
            ->exists();
    }

    /**
     * Confirm payment (legacy support)
     */
    public function confirmPayment(int $passageId, int $operatorId, array $paymentData = []): array
    {
        return $this->confirmEntryPayment($passageId, $operatorId, $paymentData);
    }

    /**
     * Find or create vehicle by plate number
     */
    private function findOrCreateVehicle(string $plateNumber, array $additionalData = []): Vehicle
    {
        $vehicle = Vehicle::where('plate_number', $plateNumber)->first();

        if (!$vehicle) {
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
     * Get body type price for station (DAILY RATE)
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
     */
    private function processPayment(VehiclePassage $passage, int $operatorId, array $paymentData = []): Receipt
    {
        $paymentMethod = $paymentData['payment_method'] ?? 'cash';
        $paymentAmount = $paymentData['payment_amount'] ?? $passage->total_amount;

        if ($paymentAmount < $passage->total_amount) {
            throw new Exception('Payment amount is insufficient');
        }

        $paymentType = PaymentType::where('name', 'Cash')->first();

        $receipt = Receipt::create([
            'vehicle_passage_id' => $passage->id,
            'amount' => $paymentAmount,
            'payment_method' => $paymentMethod,
            'payment_type_id' => $paymentType->id,
            'issued_by' => $operatorId,
            'issued_at' => now(),
            'notes' => $paymentData['receipt_notes'] ?? 'Daily parking fee',
        ]);

        return $receipt;
    }

    /**
     * Get active passages for monitoring
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
     * Get today's revenue summary
     */
    public function getTodayRevenue(int $stationId = null): array
    {
        $query = VehiclePassage::whereDate('entry_time', today())
            ->where('is_paid', true);

        if ($stationId) {
            $query->where('entry_station_id', $stationId);
        }

        $totalRevenue = $query->sum('total_amount');
        $paidEntries = $query->count();
        $freeReentries = VehiclePassage::whereDate('entry_time', today())
            ->where('passage_type', 'reentry')
            ->count();

        return [
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'paid_entries' => $paidEntries,
                'free_reentries' => $freeReentries,
                'date' => today()->toDateString()
            ]
        ];
    }
}
