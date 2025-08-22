<?php

namespace App\Services;

use App\Repositories\VehiclePassageRepository;
use App\Repositories\VehicleRepository;
use App\Repositories\ReceiptRepository;
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

    public function __construct(
        VehiclePassageRepository $passageRepository,
        VehicleRepository $vehicleRepository,
        ReceiptRepository $receiptRepository
    ) {
        $this->passageRepository = $passageRepository;
        $this->vehicleRepository = $vehicleRepository;
        $this->receiptRepository = $receiptRepository;
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

            // Determine account and bundle subscription
            $accountInfo = $this->determineAccountAndBundle($vehicle, $additionalData);

            // Determine payment type
            $paymentType = $this->determinePaymentType($accountInfo, $additionalData);

            // Create passage entry
            $passageData = [
                'vehicle_id' => $vehicle->id,
                'account_id' => $accountInfo['account_id'],
                'bundle_subscription_id' => $accountInfo['bundle_subscription_id'],
                'payment_type_id' => $paymentType->id,
                'entry_time' => now(),
                'entry_operator_id' => $operatorId,
                'entry_gate_id' => $gateId,
                'entry_station_id' => $gate->station_id,
                'passage_type' => $this->determinePassageType($accountInfo, $additionalData),
                'is_exempted' => $this->isExempted($accountInfo, $additionalData),
                'exemption_reason' => $additionalData['exemption_reason'] ?? null,
                'notes' => $additionalData['notes'] ?? null,
            ];

            $passage = $this->passageRepository->createPassageEntry($passageData);

            // Handle payment and receipt generation
            $receipt = null;
            if ($passage->total_amount > 0 && $passage->passage_type === 'toll') {
                $receipt = $this->processPaymentAndGenerateReceipt($passage, $additionalData, $operatorId);
            }

            // Determine gate action
            $gateAction = $this->determineGateAction($passage, $accountInfo);

            DB::commit();

            Log::info('Vehicle entry processed', [
                'plate_number' => $plateNumber,
                'passage_id' => $passage->id,
                'gate_action' => $gateAction,
                'operator_id' => $operatorId
            ]);

            return [
                'success' => true,
                'message' => 'Vehicle entry processed successfully',
                'data' => $passage,
                'gate_action' => $gateAction,
                'vehicle' => $vehicle,
                'account_info' => $accountInfo,
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

            // Get gate and station information
            $gate = Gate::with('station')->findOrFail($gateId);

            // Complete passage exit
            $exitData = [
                'exit_time' => now(),
                'exit_operator_id' => $operatorId,
                'exit_gate_id' => $gateId,
                'exit_station_id' => $gate->station_id,
                'notes' => $additionalData['notes'] ?? $activePassage->notes,
            ];

            $passage = $this->passageRepository->completePassageExit($activePassage->id, $exitData);

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

            // Determine account and bundle info
            $accountInfo = $this->determineAccountAndBundle($vehicle);

            // Determine gate action
            $gateAction = $activePassage ? 'deny' : 'allow';

            return [
                'success' => true,
                'message' => 'Vehicle found',
                'data' => [
                    'vehicle' => $vehicle,
                    'active_passage' => $activePassage,
                    'account_info' => $accountInfo
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
            $vehicleData = [
                'plate_number' => $plateNumber,
                'body_type_id' => $additionalData['body_type_id'] ?? 1, // Default body type
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
     * Determine account and bundle subscription for vehicle
     *
     * @param Vehicle $vehicle
     * @param array $additionalData
     * @return array
     */
    private function determineAccountAndBundle(Vehicle $vehicle, array $additionalData = []): array
    {
        $accountId = null;
        $bundleSubscriptionId = null;

        // Check if account_id is provided in additional data
        if (isset($additionalData['account_id'])) {
            $accountId = $additionalData['account_id'];
        } else {
            // Try to find account by vehicle
            $accountVehicle = $vehicle->accountVehicles()->where('is_primary', true)->first();
            if ($accountVehicle) {
                $accountId = $accountVehicle->account_id;
            }
        }

        // Check for active bundle subscription
        if ($accountId) {
            $bundleSubscription = BundleSubscription::where('account_id', $accountId)
                ->where('status', 'active')
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if ($bundleSubscription) {
                $bundleSubscriptionId = $bundleSubscription->id;
            }
        }

        return [
            'account_id' => $accountId,
            'bundle_subscription_id' => $bundleSubscriptionId
        ];
    }

    /**
     * Determine payment type
     *
     * @param array $accountInfo
     * @param array $additionalData
     * @return PaymentType
     */
    private function determinePaymentType(array $accountInfo, array $additionalData = []): PaymentType
    {
        // If payment_type_id is provided, use it
        if (isset($additionalData['payment_type_id'])) {
            return PaymentType::findOrFail($additionalData['payment_type_id']);
        }

        // If account exists, try to get default payment type
        if ($accountInfo['account_id']) {
            $account = Account::find($accountInfo['account_id']);
            if ($account && $account->default_payment_type_id) {
                return PaymentType::findOrFail($account->default_payment_type_id);
            }
        }

        // Default to cash payment
        return PaymentType::where('name', 'Cash')->firstOrFail();
    }

    /**
     * Determine passage type
     *
     * @param array $accountInfo
     * @param array $additionalData
     * @return string
     */
    private function determinePassageType(array $accountInfo, array $additionalData = []): string
    {
        if (isset($additionalData['passage_type'])) {
            return $additionalData['passage_type'];
        }

        // If has active bundle, it's free
        if ($accountInfo['bundle_subscription_id']) {
            return 'free';
        }

        // Check if exempted
        if ($this->isExempted($accountInfo, $additionalData)) {
            return 'exempted';
        }

        return 'toll';
    }

    /**
     * Check if vehicle is exempted
     *
     * @param array $accountInfo
     * @param array $additionalData
     * @return bool
     */
    private function isExempted(array $accountInfo, array $additionalData = []): bool
    {
        if (isset($additionalData['is_exempted'])) {
            return $additionalData['is_exempted'];
        }

        // Check account exemptions
        if ($accountInfo['account_id']) {
            $account = Account::find($accountInfo['account_id']);
            if ($account && $account->is_exempted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine gate action for entry
     *
     * @param VehiclePassage $passage
     * @param array $accountInfo
     * @return string
     */
    private function determineGateAction(VehiclePassage $passage, array $accountInfo): string
    {
        // If free or exempted, allow entry
        if ($passage->passage_type === 'free' || $passage->passage_type === 'exempted') {
            return 'allow';
        }

        // If has account, allow entry (payment will be handled at exit)
        if ($accountInfo['account_id']) {
            return 'allow';
        }

        // For cash customers, allow entry (payment at exit)
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
