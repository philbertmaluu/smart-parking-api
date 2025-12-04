<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Http\Request;

class CustomerController extends BaseController
{
    protected $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Display a listing of customers
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $customers = $this->customerRepository->searchCustomers($search, $perPage);
            } else {
                $customers = $this->customerRepository->getAllCustomersPaginated($perPage);
            }

            return $this->sendResponse($customers, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving customers', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created customer
     */
    public function store(CustomerRequest $request)
    {
        try {
            $customer = $this->customerRepository->createCustomer($request->validated());

            return $this->sendResponse($customer, 'Customer created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating customer', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified customer
     */
    public function show($id)
    {
        try {
            $customer = $this->customerRepository->getCustomerByIdWithRelations($id);

            if (!$customer) {
                return $this->sendError('Customer not found', [], 404);
            }

            return $this->sendResponse($customer, 'Customer retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving customer', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified customer
     */
    public function update(CustomerRequest $request, $id)
    {
        try {
            $customer = $this->customerRepository->updateCustomer($id, $request->validated());

            if (!$customer) {
                return $this->sendError('Customer not found', [], 404);
            }

            return $this->sendResponse($customer, 'Customer updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating customer', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->customerRepository->deleteCustomer($id);

            if (!$deleted) {
                return $this->sendError('Customer not found', [], 404);
            }

            return $this->sendResponse([], 'Customer deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting customer', $e->getMessage(), 500);
        }
    }

    /**
     * Get active customers for dropdown
     */
    public function getActiveCustomers()
    {
        try {
            $customers = $this->customerRepository->getActiveCustomersForSelect();
            return $this->sendResponse($customers, 'Active customers retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active customers', $e->getMessage(), 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function getStatistics($id, Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $statistics = $this->customerRepository->getCustomerStatistics($id, $startDate, $endDate);

            if (empty($statistics)) {
                return $this->sendError('Customer not found', [], 404);
            }

            return $this->sendResponse($statistics, 'Customer statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving customer statistics', $e->getMessage(), 500);
        }
    }
}
