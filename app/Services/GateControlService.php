<?php

namespace App\Services;

use App\Models\Gate;
use App\Models\Station;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class GateControlService
{
    protected $passageService;

    public function __construct(VehiclePassageService $passageService)
    {
        $this->passageService = $passageService;
    }

    /**
     * Process plate number detection and control gate
     *
     * @param string $plateNumber
     * @param int $gateId
     * @param string $direction (entry|exit)
     * @param int $operatorId
     * @param array $additionalData
     * @return array
     */
    public function processPlateDetection(string $plateNumber, int $gateId, string $direction, int $operatorId, array $additionalData = []): array
    {
        try {
            // Validate gate exists and is active
            $gate = Gate::with('station')->find($gateId);
            if (!$gate) {
                return $this->createResponse(false, 'Gate not found', 'deny');
            }

            if (!$gate->is_active) {
                return $this->createResponse(false, 'Gate is not active', 'deny');
            }

            // Process based on direction
            if ($direction === 'entry') {
                return $this->processEntryDetection($plateNumber, $gate, $operatorId, $additionalData);
            } elseif ($direction === 'exit') {
                return $this->processExitDetection($plateNumber, $gate, $operatorId, $additionalData);
            } else {
                return $this->createResponse(false, 'Invalid direction specified', 'deny');
            }
        } catch (Exception $e) {
            Log::error('Error in plate detection processing', [
                'plate_number' => $plateNumber,
                'gate_id' => $gateId,
                'direction' => $direction,
                'error' => $e->getMessage()
            ]);

            return $this->createResponse(false, 'Error processing plate detection', 'deny');
        }
    }

    /**
     * Process entry detection
     *
     * @param string $plateNumber
     * @param Gate $gate
     * @param int $operatorId
     * @param array $additionalData
     * @return array
     */
    private function processEntryDetection(string $plateNumber, Gate $gate, int $operatorId, array $additionalData = []): array
    {
        // Check if gate is entry type
        if ($gate->gate_type !== 'entry' && $gate->gate_type !== 'both') {
            return $this->createResponse(false, 'Gate is not configured for entry', 'deny');
        }

        // Process vehicle entry
        $result = $this->passageService->processVehicleEntry(
            $plateNumber,
            $gate->id,
            $operatorId,
            $additionalData
        );

        if (!$result['success']) {
            return $this->createResponse(false, $result['message'], 'deny');
        }

        // Determine gate action
        $gateAction = $this->determineGateAction($result['gate_action'], $gate);

        // Log the action
        $this->logGateAction($gate, $plateNumber, 'entry', $gateAction, $result);

        return $this->createResponse(true, $result['message'], $gateAction, $result['data']);
    }

    /**
     * Process exit detection
     *
     * @param string $plateNumber
     * @param Gate $gate
     * @param int $operatorId
     * @param array $additionalData
     * @return array
     */
    private function processExitDetection(string $plateNumber, Gate $gate, int $operatorId, array $additionalData = []): array
    {
        // Check if gate is exit type
        if ($gate->gate_type !== 'exit' && $gate->gate_type !== 'both') {
            return $this->createResponse(false, 'Gate is not configured for exit', 'deny');
        }

        // Process vehicle exit
        $result = $this->passageService->processVehicleExit(
            $plateNumber,
            $gate->id,
            $operatorId,
            $additionalData
        );

        if (!$result['success']) {
            return $this->createResponse(false, $result['message'], 'deny');
        }

        // Determine gate action
        $gateAction = $this->determineGateAction($result['gate_action'], $gate);

        // Log the action
        $this->logGateAction($gate, $plateNumber, 'exit', $gateAction, $result);

        return $this->createResponse(true, $result['message'], $gateAction, $result['data']);
    }

    /**
     * Quick plate lookup for gate control (without creating passage)
     *
     * @param string $plateNumber
     * @param int $gateId
     * @param string $direction
     * @return array
     */
    public function quickPlateLookup(string $plateNumber, int $gateId, string $direction): array
    {
        try {
            // Validate gate exists and is active
            $gate = Gate::with('station')->find($gateId);
            if (!$gate) {
                return $this->createResponse(false, 'Gate not found', 'deny');
            }

            if (!$gate->is_active) {
                return $this->createResponse(false, 'Gate is not active', 'deny');
            }

            // Check gate type compatibility
            if ($direction === 'entry' && $gate->gate_type !== 'entry' && $gate->gate_type !== 'both') {
                return $this->createResponse(false, 'Gate is not configured for entry', 'deny');
            }

            if ($direction === 'exit' && $gate->gate_type !== 'exit' && $gate->gate_type !== 'both') {
                return $this->createResponse(false, 'Gate is not configured for exit', 'deny');
            }

            // Perform quick lookup
            $result = $this->passageService->quickPlateLookup($plateNumber);

            if (!$result['success']) {
                return $this->createResponse(false, $result['message'], 'deny');
            }

            // Determine gate action based on direction and vehicle status
            $gateAction = $this->determineQuickLookupGateAction($result, $direction);

            // Cache the gate action for hardware integration (only for open/close actions)
            if (in_array($gateAction, ['open', 'close', 'deny'])) {
                $cacheKey = "gate_control_{$gateId}";
                Cache::put($cacheKey, [
                    'action' => $gateAction,
                    'timestamp' => now(),
                    'operator_id' => null,
                    'reason' => "Quick lookup {$direction} - Vehicle: {$plateNumber}",
                    'plate_number' => $plateNumber,
                    'direction' => $direction,
                ], 60); // Cache for 1 minute
            }

            return $this->createResponse(true, $result['message'], $gateAction, $result['data']);
        } catch (Exception $e) {
            Log::error('Error in quick plate lookup', [
                'plate_number' => $plateNumber,
                'gate_id' => $gateId,
                'direction' => $direction,
                'error' => $e->getMessage()
            ]);

            return $this->createResponse(false, 'Error processing plate lookup', 'deny');
        }
    }

    /**
     * Manually control gate (for testing or emergency purposes)
     *
     * @param int $gateId
     * @param string $action (open|close|deny)
     * @param int $operatorId
     * @param string $reason
     * @return array
     */
    public function manualGateControl(int $gateId, string $action, int $operatorId, string $reason = ''): array
    {
        try {
            $gate = Gate::find($gateId);
            if (!$gate) {
                return $this->createResponse(false, 'Gate not found', 'deny');
            }

            if (!$gate->is_active) {
                return $this->createResponse(false, 'Gate is not active', 'deny');
            }

            // Validate action
            if (!in_array($action, ['open', 'close', 'deny'])) {
                return $this->createResponse(false, 'Invalid action specified', 'deny');
            }

            // Log manual control
            Log::info('Manual gate control', [
                'gate_id' => $gateId,
                'gate_name' => $gate->name,
                'action' => $action,
                'operator_id' => $operatorId,
                'reason' => $reason
            ]);

            // Cache the gate action for a short period (for hardware integration)
            $cacheKey = "gate_control_{$gateId}";
            Cache::put($cacheKey, [
                'action' => $action,
                'timestamp' => now(),
                'operator_id' => $operatorId,
                'reason' => $reason
            ], 60); // Cache for 1 minute

            return $this->createResponse(true, "Gate {$action} command sent successfully", $action);
        } catch (Exception $e) {
            Log::error('Error in manual gate control', [
                'gate_id' => $gateId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return $this->createResponse(false, 'Error controlling gate', 'deny');
        }
    }

    /**
     * Get gate status
     *
     * @param int $gateId
     * @return array
     */
    public function getGateStatus(int $gateId): array
    {
        try {
            $gate = Gate::with('station')->find($gateId);
            if (!$gate) {
                return $this->createResponse(false, 'Gate not found', null);
            }

            // Get cached gate control status
            $cacheKey = "gate_control_{$gateId}";
            $controlStatus = Cache::get($cacheKey);

            $status = [
                'gate' => $gate,
                'is_active' => $gate->is_active,
                'gate_type' => $gate->gate_type,
                'station' => $gate->station,
                'last_control_action' => $controlStatus,
                'current_time' => now()
            ];

            return $this->createResponse(true, 'Gate status retrieved successfully', null, $status);
        } catch (Exception $e) {
            Log::error('Error getting gate status', [
                'gate_id' => $gateId,
                'error' => $e->getMessage()
            ]);

            return $this->createResponse(false, 'Error retrieving gate status', null);
        }
    }

    /**
     * Determine gate action based on passage service result
     *
     * @param string $passageAction
     * @param Gate $gate
     * @return string
     */
    private function determineGateAction(string $passageAction, Gate $gate): string
    {
        switch ($passageAction) {
            case 'allow':
                return 'open';
            case 'deny':
                return 'deny';
            case 'require_payment':
                return 'deny'; // Keep gate closed until payment
            default:
                return 'deny';
        }
    }

    /**
     * Determine gate action for quick lookup
     *
     * @param array $result
     * @param string $direction
     * @return string
     */
    private function determineQuickLookupGateAction(array $result, string $direction): string
    {
        $data = $result['data'];
        $activePassage = $data['active_passage'] ?? null;

        if ($direction === 'entry') {
            // For entry, deny if vehicle already has active passage
            return $activePassage ? 'deny' : 'open';
        } else {
            // For exit, allow if vehicle has active passage
            return $activePassage ? 'open' : 'deny';
        }
    }

    /**
     * Log gate action and cache it for hardware integration
     *
     * @param Gate $gate
     * @param string $plateNumber
     * @param string $direction
     * @param string $action
     * @param array $result
     * @return void
     */
    private function logGateAction(Gate $gate, string $plateNumber, string $direction, string $action, array $result): void
    {
        Log::info('Gate action processed', [
            'gate_id' => $gate->id,
            'gate_name' => $gate->name,
            'plate_number' => $plateNumber,
            'direction' => $direction,
            'action' => $action,
            'passage_id' => $result['data']['id'] ?? null,
            'station_id' => $gate->station_id,
            'station_name' => $gate->station->name ?? null,
            'timestamp' => now()
        ]);

        // Cache the gate action for hardware integration (only for open/close actions)
        // This allows the GateHardwareService to pick it up and send to physical boom gate
        if (in_array($action, ['open', 'close', 'deny'])) {
            $cacheKey = "gate_control_{$gate->id}";
            $operatorId = $result['data']['operator_id'] ?? $result['data']['created_by'] ?? null;
            
            Cache::put($cacheKey, [
                'action' => $action,
                'timestamp' => now(),
                'operator_id' => $operatorId,
                'reason' => "Automatic {$direction} processing - Vehicle: {$plateNumber}",
                'plate_number' => $plateNumber,
                'direction' => $direction,
                'passage_id' => $result['data']['id'] ?? null,
            ], 60); // Cache for 1 minute
        }
    }

    /**
     * Create standardized response
     *
     * @param bool $success
     * @param string $message
     * @param string|null $gateAction
     * @param mixed $data
     * @return array
     */
    private function createResponse(bool $success, string $message, ?string $gateAction = null, $data = null): array
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => now()
        ];

        if ($gateAction !== null) {
            $response['gate_action'] = $gateAction;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * Get all active gates for monitoring
     *
     * @return array
     */
    public function getActiveGates(): array
    {
        try {
            $gates = Gate::with('station')
                ->where('is_active', true)
                ->orderBy('station_id')
                ->orderBy('name')
                ->get();

            return $this->createResponse(true, 'Active gates retrieved successfully', null, $gates);
        } catch (Exception $e) {
            Log::error('Error getting active gates', [
                'error' => $e->getMessage()
            ]);

            return $this->createResponse(false, 'Error retrieving active gates', null);
        }
    }

    /**
     * Emergency gate control (override all logic)
     *
     * @param int $gateId
     * @param string $action
     * @param int $operatorId
     * @param string $emergencyReason
     * @return array
     */
    public function emergencyGateControl(int $gateId, string $action, int $operatorId, string $emergencyReason): array
    {
        try {
            $gate = Gate::find($gateId);
            if (!$gate) {
                return $this->createResponse(false, 'Gate not found', 'deny');
            }

            // Log emergency action
            Log::warning('Emergency gate control', [
                'gate_id' => $gateId,
                'gate_name' => $gate->name,
                'action' => $action,
                'operator_id' => $operatorId,
                'emergency_reason' => $emergencyReason,
                'timestamp' => now()
            ]);

            // Cache emergency action with longer duration
            $cacheKey = "gate_emergency_{$gateId}";
            Cache::put($cacheKey, [
                'action' => $action,
                'timestamp' => now(),
                'operator_id' => $operatorId,
                'emergency_reason' => $emergencyReason,
                'is_emergency' => true
            ], 300); // Cache for 5 minutes

            return $this->createResponse(true, "Emergency gate {$action} command sent successfully", $action);
        } catch (Exception $e) {
            Log::error('Error in emergency gate control', [
                'gate_id' => $gateId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return $this->createResponse(false, 'Error in emergency gate control', 'deny');
        }
    }
}
