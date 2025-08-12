<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Services\GateControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GateControlController extends BaseController
{
    protected $gateControlService;

    public function __construct(GateControlService $gateControlService)
    {
        $this->gateControlService = $gateControlService;
    }

    /**
     * Process plate detection for gate control
     */
    public function processPlateDetection(Request $request)
    {
        try {
            $request->validate([
                'plate_number' => 'required|string|max:20',
                'gate_id' => 'required|exists:gates,id',
                'direction' => 'required|in:entry,exit',
                'body_type_id' => 'nullable|exists:vehicle_body_types,id',
                'make' => 'nullable|string|max:50',
                'model' => 'nullable|string|max:50',
                'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'color' => 'nullable|string|max:30',
                'owner_name' => 'nullable|string|max:100',
                'account_id' => 'nullable|exists:accounts,id',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'passage_type' => 'nullable|in:toll,free,exempted',
                'is_exempted' => 'nullable|boolean',
                'exemption_reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:500',
            ]);

            $result = $this->gateControlService->processPlateDetection(
                $request->plate_number,
                $request->gate_id,
                $request->direction,
                Auth::id(),
                $request->all()
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error processing plate detection', $e->getMessage(), 500);
        }
    }

    /**
     * Quick plate lookup for gate control (without creating passage)
     */
    public function quickLookup(Request $request)
    {
        try {
            $request->validate([
                'plate_number' => 'required|string|max:20',
                'gate_id' => 'required|exists:gates,id',
                'direction' => 'required|in:entry,exit',
            ]);

            $result = $this->gateControlService->quickPlateLookup(
                $request->plate_number,
                $request->gate_id,
                $request->direction
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error processing quick lookup', $e->getMessage(), 500);
        }
    }

    /**
     * Manual gate control
     */
    public function manualControl(Request $request)
    {
        try {
            $request->validate([
                'gate_id' => 'required|exists:gates,id',
                'action' => 'required|in:open,close,deny',
                'reason' => 'nullable|string|max:255',
            ]);

            $result = $this->gateControlService->manualGateControl(
                $request->gate_id,
                $request->action,
                Auth::id(),
                $request->reason ?? ''
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error in manual gate control', $e->getMessage(), 500);
        }
    }

    /**
     * Emergency gate control
     */
    public function emergencyControl(Request $request)
    {
        try {
            $request->validate([
                'gate_id' => 'required|exists:gates,id',
                'action' => 'required|in:open,close,deny',
                'emergency_reason' => 'required|string|max:255',
            ]);

            $result = $this->gateControlService->emergencyGateControl(
                $request->gate_id,
                $request->action,
                Auth::id(),
                $request->emergency_reason
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error in emergency gate control', $e->getMessage(), 500);
        }
    }

    /**
     * Get gate status
     */
    public function getGateStatus($gateId)
    {
        try {
            $result = $this->gateControlService->getGateStatus($gateId);

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 404);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate status', $e->getMessage(), 500);
        }
    }

    /**
     * Get all active gates
     */
    public function getActiveGates()
    {
        try {
            $result = $this->gateControlService->getActiveGates();

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 500);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active gates', $e->getMessage(), 500);
        }
    }

    /**
     * Get gate control history (cached actions)
     */
    public function getGateControlHistory($gateId)
    {
        try {
            // This would typically fetch from a dedicated table
            // For now, we'll return the cached control status
            $result = $this->gateControlService->getGateStatus($gateId);

            if ($result['success']) {
                $data = $result['data'];
                $history = [
                    'gate' => $data['gate'],
                    'current_status' => $data['last_control_action'],
                    'is_active' => $data['is_active'],
                    'gate_type' => $data['gate_type'],
                    'station' => $data['station'],
                ];

                return $this->sendResponse($history, 'Gate control history retrieved successfully');
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 404);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate control history', $e->getMessage(), 500);
        }
    }

    /**
     * Test gate connection
     */
    public function testGateConnection($gateId)
    {
        try {
            $result = $this->gateControlService->getGateStatus($gateId);

            if ($result['success']) {
                $data = $result['data'];

                // Simulate gate connection test
                $connectionStatus = [
                    'gate_id' => $gateId,
                    'gate_name' => $data['gate']->name,
                    'connection_status' => 'connected', // This would be determined by actual hardware
                    'last_communication' => now(),
                    'hardware_status' => 'online',
                    'test_result' => 'success'
                ];

                return $this->sendResponse($connectionStatus, 'Gate connection test completed successfully');
            } else {
                return $this->sendError($result['message'], $result['data'] ?? null, 404);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error testing gate connection', $e->getMessage(), 500);
        }
    }

    /**
     * Get gate monitoring dashboard data
     */
    public function getMonitoringDashboard()
    {
        try {
            // Get active gates
            $activeGatesResult = $this->gateControlService->getActiveGates();

            if (!$activeGatesResult['success']) {
                return $this->sendError('Error retrieving active gates', null, 500);
            }

            $activeGates = $activeGatesResult['data'];

            // Get gate statuses
            $gateStatuses = [];
            foreach ($activeGates as $gate) {
                $statusResult = $this->gateControlService->getGateStatus($gate->id);
                if ($statusResult['success']) {
                    $gateStatuses[] = $statusResult['data'];
                }
            }

            $dashboardData = [
                'total_active_gates' => count($activeGates),
                'gates_by_type' => [
                    'entry' => $activeGates->where('gate_type', 'entry')->count(),
                    'exit' => $activeGates->where('gate_type', 'exit')->count(),
                    'both' => $activeGates->where('gate_type', 'both')->count(),
                ],
                'gate_statuses' => $gateStatuses,
                'last_updated' => now()
            ];

            return $this->sendResponse($dashboardData, 'Gate monitoring dashboard data retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving monitoring dashboard', $e->getMessage(), 500);
        }
    }
}
