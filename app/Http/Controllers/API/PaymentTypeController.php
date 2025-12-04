<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\PaymentTypeRequest;
use App\Repositories\PaymentTypeRepository;
use Illuminate\Http\Request;

class PaymentTypeController extends BaseController
{
    protected $paymentTypeRepository;

    public function __construct(PaymentTypeRepository $paymentTypeRepository)
    {
        $this->paymentTypeRepository = $paymentTypeRepository;
    }

    /**
     * Display a listing of payment types
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $paymentTypes = $this->paymentTypeRepository->searchPaymentTypes($search, $perPage);
            } else {
                $paymentTypes = $this->paymentTypeRepository->getAllPaymentTypesPaginated($perPage);
            }

            return $this->sendResponse($paymentTypes, 'Payment types retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving payment types', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created payment type
     */
    public function store(PaymentTypeRequest $request)
    {
        try {
            $paymentType = $this->paymentTypeRepository->createPaymentType($request->validated());

            return $this->sendResponse($paymentType, 'Payment type created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating payment type', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified payment type
     */
    public function show($id)
    {
        try {
            $paymentType = $this->paymentTypeRepository->getPaymentTypeByIdWithVehiclePassages($id);

            if (!$paymentType) {
                return $this->sendError('Payment type not found', [], 404);
            }

            return $this->sendResponse($paymentType, 'Payment type retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving payment type', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified payment type
     */
    public function update(PaymentTypeRequest $request, $id)
    {
        try {
            $paymentType = $this->paymentTypeRepository->updatePaymentType($id, $request->validated());

            if (!$paymentType) {
                return $this->sendError('Payment type not found', [], 404);
            }

            return $this->sendResponse($paymentType, 'Payment type updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating payment type', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified payment type
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->paymentTypeRepository->deletePaymentType($id);

            if (!$deleted) {
                return $this->sendError('Payment type not found', [], 404);
            }

            return $this->sendResponse([], 'Payment type deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting payment type', $e->getMessage(), 500);
        }
    }

    /**
     * Get active payment types for dropdown
     */
    public function getActivePaymentTypes()
    {
        try {
            $paymentTypes = $this->paymentTypeRepository->getPaymentTypesForSelect();
            return $this->sendResponse($paymentTypes, 'Active payment types retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active payment types', $e->getMessage(), 500);
        }
    }

    /**
     * Get payment types with vehicle passage count
     */
    public function getWithVehiclePassageCount()
    {
        try {
            $paymentTypes = $this->paymentTypeRepository->getPaymentTypesWithVehiclePassageCount();
            return $this->sendResponse($paymentTypes, 'Payment types with vehicle passage count retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving payment types with vehicle passage count', $e->getMessage(), 500);
        }
    }

    /**
     * Get payment type usage statistics
     */
    public function getUsageStatistics(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $statistics = $this->paymentTypeRepository->getPaymentTypeUsageStatistics($startDate, $endDate);
            return $this->sendResponse($statistics, 'Payment type usage statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving payment type usage statistics', $e->getMessage(), 500);
        }
    }

    /**
     * Get payment types with recent usage
     */
    public function getWithRecentUsage(Request $request)
    {
        try {
            $days = $request->get('days', 30);
            $paymentTypes = $this->paymentTypeRepository->getPaymentTypesWithRecentUsage($days);
            return $this->sendResponse($paymentTypes, 'Payment types with recent usage retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving payment types with recent usage', $e->getMessage(), 500);
        }
    }

    /**
     * Get payment type by name
     */
    public function getByName($name)
    {
        try {
            $paymentType = $this->paymentTypeRepository->getPaymentTypeByName($name);

            if (!$paymentType) {
                return $this->sendError('Payment type not found', [], 404);
            }

            return $this->sendResponse($paymentType, 'Payment type retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving payment type', $e->getMessage(), 500);
        }
    }
}
