<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\VehicleBodyTypeRequest;
use App\Repositories\VehicleBodyTypeRepository;
use Illuminate\Http\Request;

class VehicleBodyTypeController extends BaseController
{
    protected $vehicleBodyTypeRepository;

    public function __construct(VehicleBodyTypeRepository $vehicleBodyTypeRepository)
    {
        $this->vehicleBodyTypeRepository = $vehicleBodyTypeRepository;
    }

    /**
     * Display a listing of vehicle body types
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $vehicleBodyTypes = $this->vehicleBodyTypeRepository->searchVehicleBodyTypes($search, $perPage);
            } else {
                $vehicleBodyTypes = $this->vehicleBodyTypeRepository->getAllVehicleBodyTypesPaginated($perPage);
            }

            return $this->sendResponse($vehicleBodyTypes, 'Vehicle body types retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body types', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created vehicle body type
     */
    public function store(VehicleBodyTypeRequest $request)
    {
        try {
            $vehicleBodyType = $this->vehicleBodyTypeRepository->createVehicleBodyType($request->validated());

            return $this->sendResponse($vehicleBodyType, 'Vehicle body type created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating vehicle body type', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified vehicle body type
     */
    public function show($id)
    {
        try {
            $vehicleBodyType = $this->vehicleBodyTypeRepository->getVehicleBodyTypeByIdWithRelations($id);

            if (!$vehicleBodyType) {
                return $this->sendError('Vehicle body type not found', [], 404);
            }

            return $this->sendResponse($vehicleBodyType, 'Vehicle body type retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body type', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified vehicle body type
     */
    public function update(VehicleBodyTypeRequest $request, $id)
    {
        try {
            $vehicleBodyType = $this->vehicleBodyTypeRepository->updateVehicleBodyType($id, $request->validated());

            if (!$vehicleBodyType) {
                return $this->sendError('Vehicle body type not found', [], 404);
            }

            return $this->sendResponse($vehicleBodyType, 'Vehicle body type updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating vehicle body type', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified vehicle body type
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->vehicleBodyTypeRepository->deleteVehicleBodyType($id);

            if (!$deleted) {
                return $this->sendError('Vehicle body type not found', [], 404);
            }

            return $this->sendResponse([], 'Vehicle body type deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting vehicle body type', $e->getMessage(), 500);
        }
    }

    /**
     * Get active vehicle body types for dropdown
     */
    public function getActiveVehicleBodyTypes()
    {
        try {
            $vehicleBodyTypes = $this->vehicleBodyTypeRepository->getVehicleBodyTypesForSelect();
            return $this->sendResponse($vehicleBodyTypes, 'Active vehicle body types retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active vehicle body types', $e->getMessage(), 500);
        }
    }

    /**
     * Get vehicle body types by category
     */
    public function getByCategory($category)
    {
        try {
            $vehicleBodyTypes = $this->vehicleBodyTypeRepository->getVehicleBodyTypesByCategory($category);
            return $this->sendResponse($vehicleBodyTypes, "Vehicle body types in category '{$category}' retrieved successfully");
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body types by category', $e->getMessage(), 500);
        }
    }

    /**
     * Get vehicle body types with vehicle count
     */
    public function getWithVehicleCount()
    {
        try {
            $vehicleBodyTypes = $this->vehicleBodyTypeRepository->getVehicleBodyTypesWithVehicleCount();
            return $this->sendResponse($vehicleBodyTypes, 'Vehicle body types with vehicle count retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body types with vehicle count', $e->getMessage(), 500);
        }
    }

    /**
     * Get vehicle body types with current pricing for a station
     */
    public function getWithPricing(Request $request)
    {
        try {
            $stationId = $request->get('station_id');

            if (!$stationId) {
                return $this->sendError('Station ID is required', [], 400);
            }

            $vehicleBodyTypes = $this->vehicleBodyTypeRepository->getVehicleBodyTypesWithCurrentPricing($stationId);
            return $this->sendResponse($vehicleBodyTypes, 'Vehicle body types with pricing retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body types with pricing', $e->getMessage(), 500);
        }
    }

    /**
     * Get vehicle body types by category with pricing for a station
     */
    public function getByCategoryWithPricing(Request $request, $category)
    {
        try {
            $stationId = $request->get('station_id');

            if (!$stationId) {
                return $this->sendError('Station ID is required', [], 400);
            }

            $vehicleBodyTypes = $this->vehicleBodyTypeRepository->getVehicleBodyTypesByCategoryWithPricing($category, $stationId);
            return $this->sendResponse($vehicleBodyTypes, "Vehicle body types in category '{$category}' with pricing retrieved successfully");
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body types by category with pricing', $e->getMessage(), 500);
        }
    }
}
