<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\VehicleRequest;
use App\Models\Vehicle;
use App\Repositories\VehicleRepository;
use Illuminate\Http\Request;

class VehicleController extends BaseController
{
    protected $vehicleRepository;

    public function __construct(VehicleRepository $vehicleRepository)
    {
        $this->vehicleRepository = $vehicleRepository;
    }

    /**
     * Display a listing of vehicles
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $bodyTypeId = $request->get('body_type_id');

            if ($search) {
                $vehicles = $this->vehicleRepository->searchVehicles($search, $perPage);
            } elseif ($bodyTypeId) {
                $vehicles = $this->vehicleRepository->getVehiclesByBodyType($bodyTypeId);
                return $this->sendResponse($vehicles, 'Vehicles retrieved successfully');
            } else {
                $vehicles = $this->vehicleRepository->getAllVehiclesPaginated($perPage);
            }

            return $this->sendResponse($vehicles, 'Vehicles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicles', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created vehicle
     */
    public function store(VehicleRequest $request)
    {
        try {
            $vehicle = $this->vehicleRepository->createVehicle($request->validated());

            return $this->sendResponse($vehicle, 'Vehicle created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating vehicle', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified vehicle
     */
    public function show($id)
    {
        try {
            $vehicle = $this->vehicleRepository->getVehicleByIdWithRelations($id);

            if (!$vehicle) {
                return $this->sendError('Vehicle not found', [], 404);
            }

            return $this->sendResponse($vehicle, 'Vehicle retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified vehicle
     */
    public function update(VehicleRequest $request, $id)
    {
        try {
            $vehicle = $this->vehicleRepository->updateVehicle($id, $request->validated());

            if (!$vehicle) {
                return $this->sendError('Vehicle not found', [], 404);
            }

            return $this->sendResponse($vehicle, 'Vehicle updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating vehicle', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified vehicle
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->vehicleRepository->deleteVehicle($id);

            if (!$deleted) {
                return $this->sendError('Vehicle not found', [], 404);
            }

            return $this->sendResponse([], 'Vehicle deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting vehicle', $e->getMessage(), 500);
        }
    }

    /**
     * Get active vehicles for dropdown
     */
    public function getActiveVehicles()
    {
        try {
            $vehicles = $this->vehicleRepository->getActiveVehiclesForSelect();
            return $this->sendResponse($vehicles, 'Active vehicles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active vehicles', $e->getMessage(), 500);
        }
    }



    /**
     * Get vehicle statistics
     */
    public function getStatistics($id, Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $statistics = $this->vehicleRepository->getVehicleStatistics($id, $startDate, $endDate);

            if (empty($statistics)) {
                return $this->sendError('Vehicle not found', [], 404);
            }

            return $this->sendResponse($statistics, 'Vehicle statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle statistics', $e->getMessage(), 500);
        }
    }

    /**
     * Search vehicle by plate number
     */
    public function searchByPlate($plateNumber)
    {
        try {
            $vehicle = $this->vehicleRepository->searchByPlateNumber($plateNumber);

            if (!$vehicle) {
                return $this->sendError('Vehicle not found', [], 404);
            }

            return $this->sendResponse($vehicle, 'Vehicle found successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error searching vehicle', $e->getMessage(), 500);
        }
    }

    /**
     * Lookup vehicle by exact plate number
     */
    public function lookupByPlate($plateNumber)
    {
        try {
            $vehicle = $this->vehicleRepository->lookupByPlateNumber($plateNumber);

            if (!$vehicle) {
                return $this->sendError('Vehicle not found', [], 404);
            }

            return $this->sendResponse($vehicle, 'Vehicle found successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error looking up vehicle', $e->getMessage(), 500);
        }
    }

    /**
     * Get active vehicles list
     */
    public function getActiveVehiclesList()
    {
        try {
            $vehicles = $this->vehicleRepository->getActiveVehiclesList();
            return $this->sendResponse($vehicles, 'Active vehicles list retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active vehicles list', $e->getMessage(), 500);
        }
    }

    /**
     * Get vehicles by body type
     */
    public function getVehiclesByBodyType($bodyTypeId)
    {
        try {
            $vehicles = $this->vehicleRepository->getVehiclesByBodyType($bodyTypeId);
            return $this->sendResponse($vehicles, 'Vehicles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicles by body type', $e->getMessage(), 500);
        }
    }

    /**
     * Get registered vehicles list
     */
    public function getRegisteredVehicles()
    {
        try {
            $vehicles = $this->vehicleRepository->getRegisteredVehicles();
            return $this->sendResponse($vehicles, 'Registered vehicles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving registered vehicles', $e->getMessage(), 500);
        }
    }

    /**
     * Get unregistered vehicles list
     */
    public function getUnregisteredVehicles()
    {
        try {
            $vehicles = $this->vehicleRepository->getUnregisteredVehicles();
            return $this->sendResponse($vehicles, 'Unregistered vehicles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving unregistered vehicles', $e->getMessage(), 500);
        }
    }
}
