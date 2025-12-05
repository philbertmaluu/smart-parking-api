<?php

namespace App\Services;

use App\Repositories\VehiclePassageRepository;
use App\Repositories\VehicleRepository;
use App\Repositories\ReceiptRepository;
use App\Services\PricingService;
use App\Models\Vehicle;
use App\Models\VehiclePassage;
use App\Models\Receipt;
use App\Models\Gate;
use App\Models\Station;
use App\Models\Account;
use App\Models\BundleSubscription;
use App\Models\PaymentType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class VehiclePassageService
{
    protected $passageRepository;
    protected $vehicleRepository;
    protected $receiptRepository;
    protected $pricingService;

    public function __construct(
        VehiclePassageRepository $passageRepository,
        VehicleRepository $vehicleRepository,
        ReceiptRepository $receiptRepository,
        PricingService $pricingService
    ) {
        $this->passageRepository = $passageRepository;
        $this->vehicleRepository = $vehicleRepository;
        $this->receiptRepository = $receiptRepository;
        $this->pricingService = $pricingService;
    }

    /**
     * Process vehicle entry with plate number detection
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

            // Find or create vehicle
            $vehicle = $this->findOrCreateVehicle($plateNumber, $additionalData);


            // Check if vehicle has an active paid pass within 24 hours
            $paidPassActive = $this->isWithinPaidPassWindow($vehicle, $activePassage->entry_time, now());

            // Get gate and station information
            $gate = Gate::with('station')->findOrFail($gateId);

            if (!$gate) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Gate not found',
                    'data' => null,
                    'gate_action' => 'deny'
                ];
            }

            // Check if vehicle already has an active passage
            $activePassage = $this->passageRepository->getActivePassageByVehicle($vehicle->id);
            if ($activePassage) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Vehicle already has an active passage',
                    'data' => $activePassage,
                    'gate_action' => 'deny'
                ];
            }

            // Get account information
            $account = $this->getAccountForVehicle($vehicle, $additionalData);

            // Calculate pricing using PricingService
            $pricing = $this->pricingService->calculatePricing($vehicle, $gate->station, $account);

            // Create passage entry with pricing information
            $passageData = [
                'vehicle_id' => $vehicle->id,
                'account_id' => $account?->id,
                'bundle_subscription_id' => $pricing['bundle_subscription_id'] ?? null,
                'payment_type_id' => $pricing['payment_type_id'],
                'entry_time' => now(),
                'entry_operator_id' => $operatorId,
                'entry_gate_id' => $gateId,
                'entry_station_id' => $gate->station_id,
                'passage_type' => $this->determinePassageTypeFromPricing($pricing),
                'base_amount' => $pricing['base_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'total_amount' => $pricing['total_amount'],
                'notes' => $additionalData['notes'] ?? null,
            ];

            $passage = $this->passageRepository->createPassageEntry($passageData);

            // Handle payment and receipt generation
            $receipt = null;
            if ($passage->total_amount > 0 && $passage->passage_type === 'toll') {
                $receipt = $this->processPaymentAndGenerateReceipt($passage, $additionalData, $operatorId);
            }

            // Determine gate action
            $gateAction = $this->determineGateAction($passage, $pricing);

            DB::commit();

            Log::info('Vehicle entry processed', [
                'plate_number' => $plateNumber,
                'passage_id' => $passage->id,
                'gate_action' => $gateAction,
                'operator_id' => $operatorId,
                'pricing' => $pricing
            ]);

            return [
                'success' => true,
                'message' => 'Vehicle entry processed successfully',
                'data' => $passage,
                'gate_action' => $gateAction,
                'vehicle' => $vehicle,
                'pricing' => $pricing,
                'receipt' => $receipt
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing vehicle entry', [
                'plate_number' => $plateNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error processing vehicle entry: ' . $e->getMessage(),
                'data' => null,
                'gate_action' => 'deny'
            ];
        }
    }

    /**
     * Process vehicle exit with plate number detection
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
            $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);
            if (!$vehicle) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'data' => null,
                    'gate_action' => 'deny'
                ];
            }

            // Get active passage
            $activePassage = $this->passageRepository->getActivePassageByVehicle($vehicle->id);
            if (!$activePassage) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No active passage found for vehicle',
                    'data' => null,
                    'gate_action' => 'deny'
                ];
            }

            // Refresh vehicle to get latest paid_until status
            $vehicle->refresh();

            // Check if vehicle has body_type_id - required for payment calculation on exit
            if (!$vehicle->body_type_id) {
                // Check if body_type_id is provided in additional data
                if (isset($additionalData['body_type_id']) && $additionalData['body_type_id']) {
                    // Update vehicle with body type
                    $vehicle->update(['body_type_id' => $additionalData['body_type_id']]);
                    $vehicle->refresh();
                    
                    // Recalculate pricing for the passage with the new body type
                    $gate = Gate::with('station')->findOrFail($gateId);
                    $account = $this->getAccountForVehicle($vehicle, $additionalData);
                    $pricing = $this->pricingService->calculatePricing($vehicle, $gate->station, $account);
                    
                    // Update passage with new pricing
                    $activePassage->update([
                        'base_amount' => $pricing['base_amount'],
                        'discount_amount' => $pricing['discount_amount'],
                        'total_amount' => $pricing['total_amount'],
                    ]);
                    $activePassage->refresh();
                } else {
                    // Vehicle type is required for payment calculation
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Vehicle type is required for exit processing',
                        'data' => [
                            'vehicle' => $vehicle,
                            'passage' => $activePassage,
                            'requires_vehicle_type' => true
                        ],
                        'gate_action' => 'require_vehicle_type'
                    ];
                }
            }

            // Get gate and station information
            $gate = Gate::with('station')->findOrFail($gateId);

            // Check if vehicle has an active paid pass (paid within last 24 hours)
            $paidPassActive = $this->isWithinPaidPassWindow($vehicle, $activePassage->entry_time, now());

            // Recalculate pricing if base_amount is 0 but vehicle now has body_type_id
            if ($activePassage->base_amount == 0 && $vehicle->body_type_id) {
                $account = $this->getAccountForVehicle($vehicle, $additionalData);
                $pricing = $this->pricingService->calculatePricing($vehicle, $gate->station, $account);
                
                // Update passage with calculated pricing
                $activePassage->update([
                    'base_amount' => $pricing['base_amount'],
                    'discount_amount' => $pricing['discount_amount'],
                    'payment_type_id' => $pricing['payment_type_id'],
                ]);
                $activePassage->refresh();
            }

            // Complete passage exit (this will calculate days and total amount)
            $exitData = [
                'exit_time' => now(),
                'exit_operator_id' => $operatorId,
                'exit_gate_id' => $gateId,
                'exit_station_id' => $gate->station_id,
                'notes' => $additionalData['notes'] ?? $activePassage->notes,
            ];

            $passage = $this->passageRepository->completePassageExit($activePassage->id, $exitData);

            // If a paid pass is active, override amounts to zero and mark as no-fee exit
            if ($paidPassActive) {
                $passage->update([
                    'discount_amount' => $passage->total_amount,
                    'total_amount' => 0,
                ]);
                $passage->setAttribute('paid_pass_active', true);
            }

            // If payment was due and processed (non-zero total), mark vehicle as paid for 24 hours from exit time
            if (!$paidPassActive && $passage->total_amount > 0) {
                $vehicle->update([
                    'paid_until' => $passage->exit_time->copy()->addHours(24),
                ]);
                $vehicle->refresh();
            }

            // Determine gate action based on payment status
            $gateAction = $this->determineExitGateAction($passage, $additionalData);

            DB::commit();

            Log::info('Vehicle exit processed', [
                'plate_number' => $plateNumber,
                'passage_id' => $passage->id,
                'gate_action' => $gateAction,
                'operator_id' => $operatorId
            ]);

            // Add paid_pass_active to passage data for frontend
            $passageData = $passage->toArray();
            $passageData['paid_pass_active'] = $paidPassActive;

            return [
                'success' => true,
                'message' => 'Vehicle exit processed successfully',
                'data' => $passageData,
                'gate_action' => $gateAction,
                'vehicle' => $vehicle
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing vehicle exit', [
                'plate_number' => $plateNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error processing vehicle exit: ' . $e->getMessage(),
                'data' => null,
                'gate_action' => 'deny'
            ];
        }
    }

    /**
     * Quick plate number lookup for gate control
     *
     * @param string $plateNumber
     * @return array
     */
    public function quickPlateLookup(string $plateNumber): array
    {
        try {
            $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);

            if (!$vehicle) {
                return [
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'data' => null,
                    'gate_action' => 'deny'
                ];
            }

            // Check for active passage
            $activePassage = $this->passageRepository->getActivePassageByVehicle($vehicle->id);

            // Get account information (only for bundle subscribers)
            $account = $this->getAccountForVehicle($vehicle);

            // Determine gate action
            $gateAction = $activePassage ? 'deny' : 'allow';

            return [
                'success' => true,
                'message' => 'Vehicle found',
                'data' => [
                    'vehicle' => $vehicle,
                    'active_passage' => $activePassage,
                    'account' => $account,
                    'has_bundle_subscription' => $account !== null
                ],
                'gate_action' => $gateAction
            ];
        } catch (Exception $e) {
            Log::error('Error in quick plate lookup', [
                'plate_number' => $plateNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error processing plate lookup',
                'data' => null,
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
        $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);

        if (!$vehicle) {
            // Create new vehicle if not found
            // body_type_id can be null - will be set on exit if needed
            $vehicleData = [
                'plate_number' => $plateNumber,
                'body_type_id' => $additionalData['body_type_id'] ?? null, // Nullable - set on exit if needed
                'make' => $additionalData['make'] ?? null,
                'model' => $additionalData['model'] ?? null,
                'year' => $additionalData['year'] ?? null,
                'color' => $additionalData['color'] ?? null,
                'owner_name' => $additionalData['owner_name'] ?? null,
                'is_registered' => false, // New vehicles are unregistered by default
            ];

            $vehicle = $this->vehicleRepository->createVehicle($vehicleData);
        }

        return $vehicle;
    }



    /**
     * Determine gate action for entry
     *
     * @param VehiclePassage $passage
     * @param array $pricing
     * @return string
     */
    private function determineGateAction(VehiclePassage $passage, array $pricing): string
    {
        // If free or exempted, allow entry
        if ($passage->passage_type === 'free' || $passage->passage_type === 'exempted') {
            return 'allow';
        }

        // If bundle subscription, allow entry
        if ($pricing['payment_type'] === 'Bundle') {
            return 'allow';
        }

        // If exempted, allow entry
        if ($pricing['payment_type'] === 'Exemption') {
            return 'allow';
        }

        // For cash customers, check if payment is required
        if ($pricing['payment_type'] === 'Cash') {
            if ($pricing['requires_payment']) {
                return 'require_payment';
            }
            return 'allow';
        }

        // Default to allow entry
        return 'allow';
    }

    /**
     * Determine gate action for exit
     *
     * @param VehiclePassage $passage
     * @param array $additionalData
     * @return string
     */
    private function determineExitGateAction(VehiclePassage $passage, array $additionalData = []): string
    {
        // If vehicle has an active paid pass within 24 hours, allow exit
        $passage->loadMissing('vehicle');
        $vehicle = $passage->vehicle;
        if ($vehicle && $this->isWithinPaidPassWindow($vehicle, $passage->entry_time, now())) {
            return 'allow';
        }

        // If free or exempted, allow exit
        if ($passage->passage_type === 'free' || $passage->passage_type === 'exempted') {
            return 'allow';
        }

        // If payment is confirmed in additional data
        if (isset($additionalData['payment_confirmed']) && $additionalData['payment_confirmed']) {
            return 'allow';
        }

        // If has account, allow exit (billing will be handled separately)
        if ($passage->account_id) {
            return 'allow';
        }

        // Check if vehicle already paid within 24-hour period (same-day payment)
        if ($this->checkSameDayPayment($passage)) {
            return 'allow';
        }

        // For cash customers, require payment confirmation
        return 'require_payment';
    }

    /**
     * Check if vehicle has already paid within 24-hour rolling period from entry time
     *
     * @param VehiclePassage $passage
     * @return bool
     */
    private function checkSameDayPayment(VehiclePassage $passage): bool
    {
        // Only check for cash payments (toll passages)
        if ($passage->passage_type !== 'toll') {
            return false;
        }

        // Check if there's a valid receipt within 24-hour period from entry time
        $hasValidReceipt = $this->receiptRepository->hasValidReceiptInPeriod(
            $passage->vehicle_id,
            $passage->entry_time,
            $passage->id // Exclude current passage from check
        );

        if ($hasValidReceipt) {
            Log::info('Vehicle has valid receipt within 24-hour period', [
                'passage_id' => $passage->id,
                'vehicle_id' => $passage->vehicle_id,
                'entry_time' => $passage->entry_time,
            ]);
            return true;
        }

        // Also check if current passage has a receipt (paid on entry)
        $currentReceipts = $this->receiptRepository->getReceiptsByVehiclePassage($passage->id);
        if ($currentReceipts->isNotEmpty()) {
            Log::info('Current passage has receipt - payment already made', [
                'passage_id' => $passage->id,
                'vehicle_id' => $passage->vehicle_id,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get passage statistics for dashboard
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getDashboardStatistics(string $startDate, string $endDate): array
    {
        return $this->passageRepository->getPassageStatistics($startDate, $endDate);
    }

    /**
     * Get exit pricing preview (before processing exit)
     * Calculates duration, days, and total amount without completing exit
     *
     * @param string $plateNumber
     * @param int|null $bodyTypeId
     * @return array
     */
    public function getExitPricingPreview(string $plateNumber, ?int $bodyTypeId = null): array
    {
        try {
            // Find vehicle
            $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);
            if (!$vehicle) {
                return [
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'data' => null
                ];
            }

            // Get active passage
            $activePassage = $this->passageRepository->getActivePassageByVehicle($vehicle->id);
            if (!$activePassage) {
                return [
                    'success' => false,
                    'message' => 'No active passage found for vehicle',
                    'data' => null
                ];
            }

            // Refresh vehicle to get latest paid_until status
            $vehicle->refresh();

            // Use provided body_type_id or vehicle's existing one
            $finalBodyTypeId = $bodyTypeId ?? $vehicle->body_type_id;
            
            if (!$finalBodyTypeId) {
                return [
                    'success' => false,
                    'message' => 'Vehicle type is required for pricing calculation',
                    'data' => [
                        'vehicle' => $vehicle,
                        'passage' => $activePassage,
                        'requires_vehicle_type' => true
                    ]
                ];
            }

            // Update vehicle with body type if provided
            if ($bodyTypeId && !$vehicle->body_type_id) {
                $vehicle->update(['body_type_id' => $bodyTypeId]);
                $vehicle->refresh();
            }

            // Check if vehicle has an active paid pass within 24 hours
            $paidPassActive = $this->isWithinPaidPassWindow($vehicle, $activePassage->entry_time, now());

            // Get gate and station
            $gate = Gate::with('station')->findOrFail($activePassage->entry_gate_id);
            
            // Recalculate pricing with vehicle type
            $account = $this->getAccountForVehicle($vehicle);
            $pricing = $this->pricingService->calculatePricing($vehicle, $gate->station, $account);
            
            // Calculate duration and days
            $entryTime = $activePassage->entry_time;
            $exitTime = now();
            $durationMinutes = $entryTime->diffInMinutes($exitTime);
            $durationHours = $entryTime->diffInHours($exitTime, true);
            
            // Calculate days based on rolling 24-hour periods
            $daysToCharge = $paidPassActive ? 0 : $this->calculateDaysToCharge($entryTime, $exitTime);
            
            // Calculate total amount
            $baseAmount = $paidPassActive ? 0 : ($pricing['base_amount'] ?? 0);
            $totalAmount = $baseAmount * $daysToCharge;
            
            // Check if vehicle already paid within 24 hours
            $hasValidReceipt = $this->receiptRepository->hasValidReceiptInPeriod(
                $vehicle->id,
                $entryTime,
                $activePassage->id
            );
            
            // Check if current passage has receipt (paid on entry)
            $currentReceipts = $this->receiptRepository->getReceiptsByVehiclePassage($activePassage->id);
            $hasCurrentReceipt = $currentReceipts->isNotEmpty();
            
            // Determine if payment is needed
            $needsPayment = !$hasValidReceipt && !$hasCurrentReceipt && !$paidPassActive && $totalAmount > 0;
            $isWithin24Hours = $durationHours < 24;
            $noFee = $paidPassActive || ($isWithin24Hours && ($hasValidReceipt || $hasCurrentReceipt));

            return [
                'success' => true,
                'message' => 'Exit pricing calculated',
                'data' => [
                    'vehicle' => $vehicle,
                    'passage' => $activePassage,
                    'entry_time' => $entryTime,
                    'exit_time' => $exitTime,
                    'duration_minutes' => $durationMinutes,
                    'duration_hours' => $durationHours,
                    'days_to_charge' => $daysToCharge,
                    'base_amount' => $baseAmount,
                    'total_amount' => $totalAmount,
                    'pricing' => $pricing,
                    'needs_payment' => $needsPayment,
                    'no_fee' => $noFee,
                    'paid_pass_active' => $paidPassActive,
                    'has_valid_receipt' => $hasValidReceipt || $hasCurrentReceipt,
                    'is_within_24_hours' => $isWithin24Hours,
                ]
            ];
        } catch (Exception $e) {
            Log::error('Error calculating exit pricing preview', [
                'plate_number' => $plateNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error calculating exit pricing: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Check if vehicle has an active paid pass (paid exit) that covers the current entry/exit window.
     */
    private function isWithinPaidPassWindow(Vehicle $vehicle, \Carbon\Carbon $entryTime, \Carbon\Carbon $now): bool
    {
        if (!$vehicle->paid_until) {
            return false;
        }

        // Valid if current time is before paid_until and the entry occurred before paid_until window expires
        return $now->lt($vehicle->paid_until) && $entryTime->lt($vehicle->paid_until);
    }

    /**
     * Calculate days to charge based on rolling 24-hour periods
     *
     * @param \Carbon\Carbon $entryTime
     * @param \Carbon\Carbon $exitTime
     * @return int
     */
    private function calculateDaysToCharge(\Carbon\Carbon $entryTime, \Carbon\Carbon $exitTime): int
    {
        $hoursSpent = $entryTime->diffInHours($exitTime, true);
        
        // Minimum charge — always 1 day
        if ($hoursSpent <= 0) {
            return 1;
        }

        // If parked less than 24 hours, charge 1 day
        if ($hoursSpent < 24) {
            return 1;
        }

        // If parked 24 hours or more, calculate number of full 24-hour periods
        // Round up to next full day if there's any partial day
        $daysSpent = $hoursSpent / 24;
        return (int) ceil($daysSpent);
    }

    /**
     * Get active passages for monitoring
     *
     * @return array
     */
    public function getActivePassagesForMonitoring(): array
    {
        $passages = $this->passageRepository->getActivePassages();

        return [
            'success' => true,
            'data' => $passages,
            'count' => $passages->count()
        ];
    }

    /**
     * Get account for vehicle (only for bundle subscribers)
     *
     * @param Vehicle $vehicle
     * @param array $additionalData
     * @return Account|null
     */
    private function getAccountForVehicle(Vehicle $vehicle, array $additionalData = []): ?Account
    {
        // Check if account_id is provided in additional data
        if (isset($additionalData['account_id'])) {
            return Account::find($additionalData['account_id']);
        }

        // Try to find account by vehicle (only for bundle subscribers)
        $accountVehicle = $vehicle->accountVehicles()->where('is_primary', true)->first();
        if ($accountVehicle) {
            $account = Account::find($accountVehicle->account_id);
            // Only return account if it has active bundle subscription
            if ($account && $this->pricingService->hasActiveBundleSubscription($account)) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Determine passage type from pricing
     *
     * @param array $pricing
     * @return string
     */
    private function determinePassageTypeFromPricing(array $pricing): string
    {
        return match ($pricing['payment_type']) {
            'Exemption' => 'exempted',
            'Bundle' => 'free',
            'Cash' => 'toll',
            default => 'toll',
        };
    }

    /**
     * Process payment and generate receipt
     *
     * @param VehiclePassage $passage
     * @param array $additionalData
     * @param int $operatorId
     * @return Receipt|null
     */
    private function processPaymentAndGenerateReceipt(VehiclePassage $passage, array $additionalData, int $operatorId): ?Receipt
    {
        try {
            // Validate payment data
            if (!isset($additionalData['payment_method'])) {
                throw new Exception('Payment method is required for toll passages');
            }

            // Validate payment amount
            $paymentAmount = $additionalData['payment_amount'] ?? $passage->total_amount;
            if ($paymentAmount < $passage->total_amount) {
                throw new Exception('Payment amount is insufficient');
            }

            // Create receipt
            $receiptData = [
                'payment_method' => $additionalData['payment_method'],
                'issued_by' => $operatorId,
                'issued_at' => now(),
                'notes' => $additionalData['receipt_notes'] ?? null,
            ];

            $receipt = $this->receiptRepository->createReceiptForPassage($passage, $receiptData);

            Log::info('Receipt generated for vehicle passage', [
                'passage_id' => $passage->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'amount' => $receipt->amount,
                'payment_method' => $receipt->payment_method,
                'operator_id' => $operatorId
            ]);

            return $receipt;
        } catch (Exception $e) {
            Log::error('Error processing payment and generating receipt', [
                'passage_id' => $passage->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
