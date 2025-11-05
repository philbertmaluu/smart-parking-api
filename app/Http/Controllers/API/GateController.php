<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\GateRequest;
use App\Models\Gate;
use App\Repositories\GateRepository;
use Illuminate\Http\Request;

class GateController extends BaseController
{
    protected $gateRepository;

    public function __construct(GateRepository $gateRepository)
    {
        $this->gateRepository = $gateRepository;
    }

    /**
     * Display a listing of gates
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $stationId = $request->get('station_id');
            $gateType = $request->get('gate_type');

            if ($search) {
                $gates = $this->gateRepository->searchGates($search, $perPage);
            } elseif ($stationId) {
                $gates = $this->gateRepository->getGatesByStation($stationId);
                return $this->sendResponse($gates, 'Gates retrieved successfully');
            } elseif ($gateType) {
                $gates = $this->gateRepository->getGatesByType($gateType);
                return $this->sendResponse($gates, 'Gates retrieved successfully');
            } else {
                $gates = $this->gateRepository->getAllGatesPaginated($perPage);
            }

            return $this->sendResponse($gates, 'Gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gates', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created gate
     */
    public function store(GateRequest $request)
    {
        try {
            $gate = $this->gateRepository->createGate($request->validated());

            return $this->sendResponse($gate, 'Gate created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating gate', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified gate
     */
    public function show($id)
    {
        try {
            $gate = $this->gateRepository->getGateByIdWithRelations($id);

            if (!$gate) {
                return $this->sendError('Gate not found', [], 404);
            }

            return $this->sendResponse($gate, 'Gate retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified gate
     */
    public function update(GateRequest $request, $id)
    {
        try {
            $gate = $this->gateRepository->updateGate($id, $request->validated());

            if (!$gate) {
                return $this->sendError('Gate not found', [], 404);
            }

            return $this->sendResponse($gate, 'Gate updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating gate', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified gate
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->gateRepository->deleteGate($id);

            if (!$deleted) {
                return $this->sendError('Gate not found', [], 404);
            }

            return $this->sendResponse([], 'Gate deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting gate', $e->getMessage(), 500);
        }
    }

    /**
     * Get active gates for dropdown
     */
    public function getActiveGates()
    {
        try {
            $gates = $this->gateRepository->getGatesForSelect();
            return $this->sendResponse($gates, 'Active gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active gates', $e->getMessage(), 500);
        }
    }

    /**
     * Get gates by station for dropdown
     */
    public function getGatesByStation($stationId)
    {
        try {
            $gates = $this->gateRepository->getGatesByStationForSelect($stationId);
            return $this->sendResponse($gates, 'Gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gates', $e->getMessage(), 500);
        }
    }

    /**
     * Get gate statistics
     */
    public function getStatistics($id, Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $statistics = $this->gateRepository->getGateStatistics($id, $startDate, $endDate);

            if (empty($statistics)) {
                return $this->sendError('Gate not found', [], 404);
            }

            return $this->sendResponse($statistics, 'Gate statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gate statistics', $e->getMessage(), 500);
        }
    }

    /**
     * Get entry gates
     */
    public function getEntryGates()
    {
        try {
            $gates = $this->gateRepository->getEntryGates();
            return $this->sendResponse($gates, 'Entry gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving entry gates', $e->getMessage(), 500);
        }
    }

    /**
     * Get exit gates
     */
    public function getExitGates()
    {
        try {
            $gates = $this->gateRepository->getExitGates();
            return $this->sendResponse($gates, 'Exit gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving exit gates', $e->getMessage(), 500);
        }
    }

    /**
     * Get both entry and exit gates
     */
    public function getBothGates()
    {
        try {
            $gates = $this->gateRepository->getBothGates();
            return $this->sendResponse($gates, 'Both entry and exit gates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving gates', $e->getMessage(), 500);
        }
    }

    /**
     * Get camera configuration for a gate
     */
    public function getCameraConfig($id)
    {
        try {
            $gate = Gate::with('devices')->find($id);

            if (!$gate) {
                return $this->sendError('Gate not found', [], 404);
            }

            // Get the primary camera (first active camera)
            $camera = $gate->devices()
                ->where('device_type', 'camera')
                ->where('status', 'active')
                ->first();

            if (!$camera) {
                return $this->sendError('No active camera found for this gate', [], 404);
            }

            // Return camera configuration
            $config = $camera->getCameraConfig();

            return $this->sendResponse($config, 'Camera configuration retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving camera configuration', $e->getMessage(), 500);
        }
    }
}
