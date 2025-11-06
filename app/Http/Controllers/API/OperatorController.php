<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\OperatorRequest;
use App\Repositories\OperatorRepository;
use App\Repositories\GateDeviceRepository;
use App\Repositories\VehiclePassageRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperatorController extends BaseController
{
    protected $operatorRepository;
    protected $gateDeviceRepository;
    protected $vehiclePassageRepository;

    public function __construct(
        OperatorRepository $operatorRepository, 
        GateDeviceRepository $gateDeviceRepository,
        VehiclePassageRepository $vehiclePassageRepository
    ) {
        $this->operatorRepository = $operatorRepository;
        $this->gateDeviceRepository = $gateDeviceRepository;
        $this->vehiclePassageRepository = $vehiclePassageRepository;
    }

    /**
     * Display a listing of operators
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $stationId = $request->get('station_id');

            if ($search) {
                $operators = $this->operatorRepository->searchOperators($search, $perPage);
            } elseif ($stationId) {
                $operators = $this->operatorRepository->getOperatorsByStation($stationId);
                return $this->sendResponse($operators, 'Operators retrieved successfully');
            } else {
                $operators = $this->operatorRepository->getAllOperatorsPaginated($perPage);
            }

            return $this->sendResponse($operators, 'Operators retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving operators', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified operator
     */
    public function show($id)
    {
        try {
            $operator = $this->operatorRepository->getOperatorByIdWithRelations($id);

            if (!$operator) {
                return $this->sendError('Operator not found', [], 404);
            }

            return $this->sendResponse($operator, 'Operator retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving operator', $e->getMessage(), 500);
        }
    }

    /**
     * Get stations assigned to an operator
     */
    public function getStations($operatorId)
    {
        try {
            $stations = $this->operatorRepository->getStationsByOperator($operatorId);
            return $this->sendResponse($stations, 'Stations retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving stations', $e->getMessage(), 500);
        }
    }

    /**
     * Get available gates for an operator at a station
     */
    public function getAvailableGates(Request $request, $operatorId)
    {
        try {
            $stationId = $request->get('station_id');

            if (!$stationId) {
                return $this->sendError('Station ID is required', [], 400);
            }

            $gates = $this->operatorRepository->getAvailableGatesForOperator($operatorId, $stationId);
            return $this->sendResponse($gates, 'Available gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gates', $e->getMessage(), 500);
        }
    }

    /**
     * Assign operator to a station
     */
    public function assignStation(OperatorRequest $request, $operatorId)
    {
        try {
            $data = $request->validated();
            $assignedBy = Auth::user()->id; // Get current authenticated user ID

            $assigned = $this->operatorRepository->assignOperatorToStation(
                $operatorId,
                $data['station_id'],
                $assignedBy
            );

            if (!$assigned) {
                return $this->sendError('Operator not found or is not a Gate Operator', [], 404);
            }

            $operator = $this->operatorRepository->getOperatorByIdWithRelations($operatorId);
            return $this->sendResponse($operator, 'Operator assigned to station successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error assigning operator to station', $e->getMessage(), 500);
        }
    }

    /**
     * Unassign operator from a station
     */
    public function unassignStation(Request $request, $operatorId)
    {
        try {
            $request->validate([
                'station_id' => 'required|exists:stations,id',
            ]);

            $unassigned = $this->operatorRepository->unassignOperatorFromStation(
                $operatorId,
                $request->station_id
            );

            if (!$unassigned) {
                return $this->sendError('Operator not found or is not a Gate Operator', [], 404);
            }

            $operator = $this->operatorRepository->getOperatorByIdWithRelations($operatorId);
            return $this->sendResponse($operator, 'Operator unassigned from station successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error unassigning operator from station', $e->getMessage(), 500);
        }
    }

    /**
     * Get all operators (non-paginated)
     */
    public function getAll()
    {
        try {
            $operators = $this->operatorRepository->getAllOperators();
            return $this->sendResponse($operators, 'Operators retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving operators', $e->getMessage(), 500);
        }
    }

    /**
     * Get available gates for logged-in operator
     * Excludes gates that are currently selected by other operators in the same station
     */
    public function getMyAvailableGates(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('Unauthorized', [], 401);
            }

            if (!$user->hasRole('Gate Operator')) {
                return $this->sendError('Only Gate Operators can access this endpoint', [], 403);
            }

            // Load assigned stations relationship using wherePivot for pivot columns
            $user->load(['assignedStations' => function ($query) {
                $query->wherePivot('is_active', true);
            }]);

            // Check if operator has assigned stations
            if ($user->assignedStations->isEmpty()) {
                return $this->sendResponse([
                    'available_gates' => [],
                    'selected_gate' => null,
                    'message' => 'No stations assigned to this operator',
                ], 'No stations assigned to operator');
            }

            $gates = $this->operatorRepository->getAvailableGatesForLoggedInOperator($user->id);
            
            // Get the currently selected gate for the operator
            $selectedGate = $this->operatorRepository->getSelectedGateForOperator($user->id);
            
            return $this->sendResponse([
                'available_gates' => $gates,
                'selected_gate' => $selectedGate,
            ], 'Available gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving available gates', $e->getMessage(), 500);
        }
    }

    /**
     * Select a gate for logged-in operator
     */
    public function selectGate(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('Unauthorized', [], 401);
            }

            if (!$user->hasRole('Gate Operator')) {
                return $this->sendError('Only Gate Operators can access this endpoint', [], 403);
            }

            $request->validate([
                'gate_id' => 'required|exists:gates,id',
            ]);

            // Get the gate to find its station_id
            $gate = \App\Models\Gate::find($request->gate_id);
            if (!$gate) {
                return $this->sendError('Gate not found', [], 404);
            }

            $success = $this->operatorRepository->selectGateForOperator(
                $user->id,
                $gate->station_id,
                $request->gate_id
            );

            if (!$success) {
                return $this->sendError('Failed to select gate. Gate may be occupied or operator not assigned to station', [], 400);
            }

            // Fetch gate devices for the selected gate
            $gateDevices = $this->gateDeviceRepository->getGateDevicesByGate($request->gate_id);
            
            $operator = $this->operatorRepository->getOperatorByIdWithRelations($user->id);
            
            return $this->sendResponse([
                'operator' => $operator,
                'gate_devices' => $gateDevices,
            ], 'Gate selected successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error selecting gate', $e->getMessage(), 500);
        }
    }

    /**
     * Get devices for the logged-in operator's currently selected gate
     */
    public function getMySelectedGateDevices(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('Unauthorized', [], 401);
            }

            if (!$user->hasRole('Gate Operator')) {
                return $this->sendError('Only Gate Operators can access this endpoint', [], 403);
            }

            // Get operator's assigned stations with current gate using wherePivot
            $assignedStations = $user->assignedStations()
                ->wherePivot('is_active', true)
                ->wherePivotNotNull('current_gate_id')
                ->get();

            if ($assignedStations->isEmpty()) {
                return $this->sendError('No gate selected. Please select a gate first', [], 404);
            }

            // Get the first selected gate (in case operator has multiple stations, get the first one)
            $selectedGateId = $assignedStations->first()->pivot->current_gate_id;
            
            if (!$selectedGateId) {
                return $this->sendError('No gate selected. Please select a gate first', [], 404);
            }

            // Fetch gate devices for the selected gate
            $gateDevices = $this->gateDeviceRepository->getGateDevicesByGate($selectedGateId);
            
            return $this->sendResponse($gateDevices, 'Gate devices retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate devices', $e->getMessage(), 500);
        }
    }

    /**
     * Deselect gate for logged-in operator
     */
    public function deselectGate(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('Unauthorized', [], 401);
            }

            if (!$user->hasRole('Gate Operator')) {
                return $this->sendError('Only Gate Operators can access this endpoint', [], 403);
            }

            $success = $this->operatorRepository->deselectGateForOperator($user->id);

            if (!$success) {
                return $this->sendError('Failed to deselect gate. No gate was selected', [], 400);
            }

            $operator = $this->operatorRepository->getOperatorByIdWithRelations($user->id);
            return $this->sendResponse($operator, 'Gate deselected successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deselecting gate', $e->getMessage(), 500);
        }
    }

    /**
     * Get recent vehicles (parked and exited) for logged-in operator
     */
    public function getMyRecentVehicles(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('Unauthorized', [], 401);
            }

            if (!$user->hasRole('Gate Operator')) {
                return $this->sendError('Only Gate Operators can access this endpoint', [], 403);
            }

            $limit = $request->get('limit', 20);
            $vehicles = $this->vehiclePassageRepository->getRecentVehiclesForOperator($user->id, $limit);

            return $this->sendResponse($vehicles, 'Recent vehicles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving recent vehicles', $e->getMessage(), 500);
        }
    }
}

