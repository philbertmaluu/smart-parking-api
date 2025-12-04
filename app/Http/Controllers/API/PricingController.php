<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Services\PricingService;
use App\Models\Vehicle;
use App\Models\Station;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PricingController extends BaseController
{
    protected $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Calculate pricing for vehicle entry
     */
    public function calculatePricing(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'vehicle_id' => 'required|integer|exists:vehicles,id',
                'station_id' => 'required|integer|exists:stations,id',
                'account_id' => 'nullable|integer|exists:accounts,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $vehicle = Vehicle::findOrFail($request->vehicle_id);
            $station = Station::findOrFail($request->station_id);
            $account = $request->account_id ? Account::find($request->account_id) : null;

            $pricing = $this->pricingService->calculatePricing($vehicle, $station, $account);

            return $this->sendResponse($pricing, 'Pricing calculated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error calculating pricing', $e->getMessage(), 500);
        }
    }

    /**
     * Calculate pricing by plate number
     */
    public function calculatePricingByPlate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_number' => 'required|string|max:20',
                'station_id' => 'required|integer|exists:stations,id',
                'account_id' => 'nullable|integer|exists:accounts,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $vehicle = Vehicle::where('plate_number', $request->plate_number)->first();
            if (!$vehicle) {
                return $this->sendError('Vehicle not found', [], 404);
            }

            $station = Station::findOrFail($request->station_id);
            $account = $request->account_id ? Account::find($request->account_id) : null;

            $pricing = $this->pricingService->calculatePricing($vehicle, $station, $account);

            return $this->sendResponse([
                'vehicle' => $vehicle,
                'pricing' => $pricing
            ], 'Pricing calculated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error calculating pricing', $e->getMessage(), 500);
        }
    }

    /**
     * Get pricing summary for station
     */
    public function getStationPricingSummary(Request $request, int $stationId): JsonResponse
    {
        try {
            $summary = $this->pricingService->getStationPricingSummary($stationId);

            return $this->sendResponse($summary, 'Station pricing summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving station pricing summary', $e->getMessage(), 500);
        }
    }

    /**
     * Validate pricing configuration for station
     */
    public function validatePricingConfiguration(Request $request, int $stationId): JsonResponse
    {
        try {
            $validation = $this->pricingService->validatePricingConfiguration($stationId);

            return $this->sendResponse($validation, 'Pricing configuration validation completed');
        } catch (\Exception $e) {
            return $this->sendError('Error validating pricing configuration', $e->getMessage(), 500);
        }
    }

    /**
     * Get base price for vehicle body type and station
     */
    public function getBasePrice(Request $request): JsonResponse
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

            $basePrice = $this->pricingService->getBasePrice(
                $request->body_type_id,
                $request->station_id
            );

            if (!$basePrice) {
                return $this->sendError('No pricing found for the specified body type and station', [], 404);
            }

            return $this->sendResponse($basePrice, 'Base price retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving base price', $e->getMessage(), 500);
        }
    }

    /**
     * Determine payment type for vehicle
     */
    public function determinePaymentType(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'vehicle_id' => 'required|integer|exists:vehicles,id',
                'account_id' => 'nullable|integer|exists:accounts,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $vehicle = Vehicle::findOrFail($request->vehicle_id);
            $account = $request->account_id ? Account::find($request->account_id) : null;

            $paymentType = $this->pricingService->determinePaymentType($vehicle, $account);

            return $this->sendResponse($paymentType, 'Payment type determined successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error determining payment type', $e->getMessage(), 500);
        }
    }

    /**
     * Check if account has active bundle subscription
     */
    public function checkBundleSubscription(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'required|integer|exists:accounts,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $account = Account::findOrFail($request->account_id);
            $hasActiveBundle = $this->pricingService->hasActiveBundleSubscription($account);
            $bundleSubscription = $hasActiveBundle ? $this->pricingService->getActiveBundleSubscription($account) : null;

            return $this->sendResponse([
                'has_active_bundle' => $hasActiveBundle,
                'bundle_subscription' => $bundleSubscription,
            ], 'Bundle subscription status checked successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error checking bundle subscription', $e->getMessage(), 500);
        }
    }

    /**
     * Calculate bulk pricing for multiple vehicles
     */
    public function calculateBulkPricing(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'vehicles' => 'required|array|min:1',
                'vehicles.*.id' => 'required|integer|exists:vehicles,id',
                'station_id' => 'required|integer|exists:stations,id',
                'account_id' => 'nullable|integer|exists:accounts,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error', $validator->errors(), 422);
            }

            $vehicles = Vehicle::whereIn('id', collect($request->vehicles)->pluck('id'))->get();
            $station = Station::findOrFail($request->station_id);
            $account = $request->account_id ? Account::find($request->account_id) : null;

            $results = $this->pricingService->calculateBulkPricing($vehicles->toArray(), $station, $account);

            return $this->sendResponse($results, 'Bulk pricing calculated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error calculating bulk pricing', $e->getMessage(), 500);
        }
    }
}
