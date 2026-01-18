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

            // NOTE: Payment and receipt generation happens at EXIT, not ENTRY
            // Vehicles enter without payment, and payment is collected at exit
            $receipt = null;

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

            // Get active passage with all needed relations
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

            // Use cached gate if we already have it, otherwise fetch with relations
            if ($activePassage->exitGate && $activePassage->exitGate->station) {
                $gate = $activePassage->exitGate;
            } else {
                $gate = Gate::with('station')->findOrFail($gateId);
            }

            // Complete passage exit
            $exitData = [
                'exit_time' => now(),
                'exit_operator_id' => $operatorId,
                'exit_gate_id' => $gateId,
                'exit_station_id' => $gate->station_id,
                'notes' => $additionalData['notes'] ?? $activePassage->notes,
            ];

            $passage = $this->passageRepository->completePassageExit($activePassage->id, $exitData);

            // Generate receipt ONLY if payment was made (toll passage with amount > 0)
            // If total_amount is 0, it means free re-entry within 24 hours
            $receipt = null;
            if ($passage->total_amount > 0 && $passage->passage_type === 'toll') {
                // Default payment method to 'cash' if not provided
                $paymentData = [
                    'payment_method' => $additionalData['payment_method'] ?? 'cash',
                    'issued_by' => $operatorId,
                    'issued_at' => now(),
                    'notes' => $additionalData['receipt_notes'] ?? null,
                ];
                
                try {
                    $receipt = $this->receiptRepository->createReceiptForPassage($passage, $paymentData);
                    Log::info('Receipt generated for vehicle exit', [
                        'passage_id' => $passage->id,
                        'receipt_id' => $receipt->id,
                        'amount' => $receipt->amount
                    ]);
                    
                    // Set paid_until to FIRST_ENTRY + 24 hours — free window starts from first entry in 24h window
                    // Use selective query with index to get first entry
                    try {
                        $firstEntryIn24h = \App\Models\VehiclePassage::where('vehicle_id', $vehicle->id)
                            ->where('entry_time', '>=', now()->subHours(24))
                            ->select(['entry_time'])  // Only select needed column for performance
                            ->orderBy('entry_time', 'asc')
                            ->first();

                        if ($firstEntryIn24h) {
                            $paidUntilTime = $firstEntryIn24h->entry_time->copy()->addHours(24);
                        } else {
                            $paidUntilTime = now()->copy()->addHours(24);
                        }

                        $vehicle->update(['paid_until' => $paidUntilTime]);
                        Log::info('Vehicle paid_until set to 24h from first entry', [
                            'vehicle_id' => $vehicle->id,
                            'paid_until' => $paidUntilTime
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to set paid_until on vehicle', [
                            'vehicle_id' => $vehicle->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                } catch (Exception $receiptError) {
                    Log::error('Failed to generate receipt for exit', [
                        'passage_id' => $passage->id,
                        'error' => $receiptError->getMessage()
                    ]);
                    // Don't fail the exit if receipt generation fails
                }
            } else {
                Log::info('No receipt generated - free re-entry within 24 hours', [
                    'passage_id' => $passage->id,
                    'vehicle_id' => $vehicle->id,
                    'total_amount' => $passage->total_amount
                ]);
            }

            // Load receipts only if needed (lazily load if not already loaded)
            if (!$passage->relationLoaded('receipts')) {
                $passage->load('receipts');
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

            return [
                'success' => true,
                'message' => 'Vehicle exit processed successfully',
                'data' => $passage,
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
            // body_type_id is optional - can be set later during exit
            $vehicleData = [
                'plate_number' => $plateNumber,
                'body_type_id' => $additionalData['body_type_id'] ?? null, // Optional - can be set later
                'make' => $additionalData['make'] ?? null,
                'model' => $additionalData['model'] ?? null,
                'year' => $additionalData['year'] ?? null,
                'color' => $additionalData['color'] ?? null,
                'owner_name' => $additionalData['owner_name'] ?? null,
                'is_registered' => false, // New vehicles are unregistered by default
            ];

            $vehicle = $this->vehicleRepository->createVehicle($vehicleData);
            
            Log::info('New vehicle created during entry', [
                'plate_number' => $plateNumber,
                'vehicle_id' => $vehicle->id,
                'body_type_id' => $vehicle->body_type_id
            ]);
        } else {
            // Update body_type_id if provided and vehicle doesn't have one
            if (isset($additionalData['body_type_id']) && !$vehicle->body_type_id) {
                $vehicle->update(['body_type_id' => $additionalData['body_type_id']]);
                $vehicle->refresh();
                
                Log::info('Vehicle body type updated', [
                    'vehicle_id' => $vehicle->id,
                    'body_type_id' => $vehicle->body_type_id
                ]);
            }
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

        // For cash customers, require payment confirmation
        return 'require_payment';
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
