<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Repositories\VehicleBodyTypePriceRepository;
use App\Models\VehicleBodyTypePrice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class VehicleBodyTypePriceController extends BaseController
{
    protected $vehicleBodyTypePriceRepository;

    public function __construct(VehicleBodyTypePriceRepository $vehicleBodyTypePriceRepository)
    {
        $this->vehicleBodyTypePriceRepository = $vehicleBodyTypePriceRepository;
    }

    /**
     * Display a listing of vehicle body type prices.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $vehicleBodyTypePrices = $this->vehicleBodyTypePriceRepository->getAllVehicleBodyTypePricesPaginated($perPage);

            return $this->sendResponse($vehicleBodyTypePrices, 'Vehicle body type prices retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body type prices', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created vehicle body type price.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'body_type_id' => 'required|integer|exists:vehicle_body_types,id',
                'station_id' => 'required|integer|exists:stations,id',
                'base_price' => 'required|numeric|min:0',
                'effective_from' => 'required|date',
                'effective_to' => 'nullable|date|after:effective_from',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $vehicleBodyTypePrice = $this->vehicleBodyTypePriceRepository->createVehicleBodyTypePrice($request->all());

            return $this->sendResponse($vehicleBodyTypePrice, 'Vehicle body type price created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating vehicle body type price', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified vehicle body type price.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $vehicleBodyTypePrice = $this->vehicleBodyTypePriceRepository->getVehicleBodyTypePriceByIdWithRelations($id);

            if (!$vehicleBodyTypePrice) {
                return $this->sendError('Vehicle body type price not found', [], 404);
            }

            return $this->sendResponse($vehicleBodyTypePrice, 'Vehicle body type price retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle body type price', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified vehicle body type price.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'body_type_id' => 'sometimes|required|integer|exists:vehicle_body_types,id',
                'station_id' => 'sometimes|required|integer|exists:stations,id',
                'base_price' => 'sometimes|required|numeric|min:0',
                'effective_from' => 'sometimes|required|date',
                'effective_to' => 'nullable|date|after:effective_from',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $vehicleBodyTypePrice = $this->vehicleBodyTypePriceRepository->updateVehicleBodyTypePrice($id, $request->all());

            if (!$vehicleBodyTypePrice) {
                return $this->sendError('Vehicle body type price not found', [], 404);
            }

            return $this->sendResponse($vehicleBodyTypePrice, 'Vehicle body type price updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating vehicle body type price', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified vehicle body type price.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->vehicleBodyTypePriceRepository->deleteVehicleBodyTypePrice($id);

            if (!$deleted) {
                return $this->sendError('Vehicle body type price not found', [], 404);
            }

            return $this->sendResponse([], 'Vehicle body type price deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting vehicle body type price', $e->getMessage(), 500);
        }
    }

    /**
     * Get current effective price for a body type and station.
     */
    public function getCurrentPrice(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'body_type_id' => 'required|integer|exists:vehicle_body_types,id',
                'station_id' => 'required|integer|exists:stations,id',
                'date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $price = $this->vehicleBodyTypePriceRepository->getCurrentPrice(
                $request->body_type_id,
                $request->station_id,
                $request->date
            );

            if (!$price) {
                return $this->sendError('No current price found for the specified body type and station', [], 404);
            }

            return $this->sendResponse($price, 'Current price retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving current price', $e->getMessage(), 500);
        }
    }

    /**
     * Get all current effective prices for a station.
     */
    public function getCurrentPricesForStation(Request $request, int $stationId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $prices = $this->vehicleBodyTypePriceRepository->getCurrentPricesForStation(
                $stationId,
                $request->date
            );

            return $this->sendResponse($prices, 'Current prices for station retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving current prices for station', $e->getMessage(), 500);
        }
    }

    /**
     * Get all current effective prices for a body type.
     */
    public function getCurrentPricesForBodyType(Request $request, int $bodyTypeId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $prices = $this->vehicleBodyTypePriceRepository->getCurrentPricesForBodyType(
                $bodyTypeId,
                $request->date
            );

            return $this->sendResponse($prices, 'Current prices for body type retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving current prices for body type', $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update prices for multiple body types and stations.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'prices' => 'required|array|min:1',
                'prices.*.body_type_id' => 'required|integer|exists:vehicle_body_types,id',
                'prices.*.station_id' => 'required|integer|exists:stations,id',
                'prices.*.base_price' => 'required|numeric|min:0',
                'prices.*.effective_from' => 'required|date',
                'prices.*.effective_to' => 'nullable|date|after:prices.*.effective_from',
                'prices.*.is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $success = $this->vehicleBodyTypePriceRepository->bulkUpdatePrices($request->prices);

            if (!$success) {
                return $this->sendError('Error updating prices', [], 500);
            }

            return $this->sendResponse([], 'Prices updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating prices', $e->getMessage(), 500);
        }
    }

    /**
     * Get pricing history for a body type and station.
     */
    public function getPricingHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'body_type_id' => 'required|integer|exists:vehicle_body_types,id',
                'station_id' => 'required|integer|exists:stations,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $history = $this->vehicleBodyTypePriceRepository->getPricingHistory(
                $request->body_type_id,
                $request->station_id
            );

            return $this->sendResponse($history, 'Pricing history retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving pricing history', $e->getMessage(), 500);
        }
    }

    /**
     * Get all prices effective on a specific date.
     */
    public function getPricesEffectiveOnDate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $prices = $this->vehicleBodyTypePriceRepository->getPricesEffectiveOnDate($request->date);

            return $this->sendResponse($prices, 'Prices effective on date retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving prices effective on date', $e->getMessage(), 500);
        }
    }

    /**
     * Search vehicle body type prices.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'search' => 'required|string|min:2',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $prices = $this->vehicleBodyTypePriceRepository->searchVehicleBodyTypePrices(
                $request->search,
                $request->get('per_page', 15)
            );

            return $this->sendResponse($prices, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error searching vehicle body type prices', $e->getMessage(), 500);
        }
    }

    /**
     * Get pricing summary for dashboard.
     */
    public function getPricingSummary(): JsonResponse
    {
        try {
            $summary = $this->vehicleBodyTypePriceRepository->getPricingSummary();

            return $this->sendResponse($summary, 'Pricing summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving pricing summary', $e->getMessage(), 500);
        }
    }

    /**
     * Get price comparison between stations for a body type.
     */
    public function getPriceComparison(int $bodyTypeId): JsonResponse
    {
        try {
            $comparison = $this->vehicleBodyTypePriceRepository->getPriceComparison($bodyTypeId);

            return $this->sendResponse($comparison, 'Price comparison retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving price comparison', $e->getMessage(), 500);
        }
    }
}
