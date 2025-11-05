<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\OperatorRequest;
use App\Repositories\OperatorRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperatorController extends BaseController
{
    protected $operatorRepository;

    public function __construct(OperatorRepository $operatorRepository)
    {
        $this->operatorRepository = $operatorRepository;
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
}

