<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\AccountRequest;
use App\Models\Account;
use App\Repositories\AccountRepository;
use Illuminate\Http\Request;

class AccountController extends BaseController
{
    protected $accountRepository;

    public function __construct(AccountRepository $accountRepository)
    {
        $this->accountRepository = $accountRepository;
    }

    /**
     * Display a listing of accounts
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $accounts = $this->accountRepository->searchAccounts($search, $perPage);
            } else {
                $accounts = $this->accountRepository->getAllAccountsPaginated($perPage);
            }

            return $this->sendResponse($accounts, 'Accounts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving accounts', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created account
     */
    public function store(AccountRequest $request)
    {
        try {
            $account = $this->accountRepository->createAccount($request->validated());

            return $this->sendResponse($account, 'Account created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating account', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified account
     */
    public function show($id)
    {
        try {
            $account = $this->accountRepository->getAccountByIdWithRelations($id);

            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            return $this->sendResponse($account, 'Account retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving account', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified account
     */
    public function update(AccountRequest $request, $id)
    {
        try {
            $account = $this->accountRepository->updateAccount($id, $request->validated());

            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            return $this->sendResponse($account, 'Account updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating account', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified account
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->accountRepository->deleteAccount($id);

            if (!$deleted) {
                return $this->sendError('Account not found', [], 404);
            }

            return $this->sendResponse([], 'Account deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting account', $e->getMessage(), 500);
        }
    }

    /**
     * Toggle account status
     */
    public function toggleStatus($id)
    {
        try {
            $account = $this->accountRepository->toggleAccountStatus($id);

            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            return $this->sendResponse($account, 'Account status updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating account status', $e->getMessage(), 500);
        }
    }

    /**
     * Get active accounts
     */
    public function getActiveAccounts()
    {
        try {
            $accounts = $this->accountRepository->getActiveAccounts();

            return $this->sendResponse($accounts, 'Active accounts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active accounts', $e->getMessage(), 500);
        }
    }

    /**
     * Get accounts by type
     */
    public function getByType($type)
    {
        try {
            $accounts = $this->accountRepository->getAccountsByType($type);

            return $this->sendResponse($accounts, 'Accounts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving accounts', $e->getMessage(), 500);
        }
    }

    /**
     * Get accounts by customer
     */
    public function getByCustomer($customerId)
    {
        try {
            $accounts = $this->accountRepository->getAccountsByCustomer($customerId);

            return $this->sendResponse($accounts, 'Customer accounts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving customer accounts', $e->getMessage(), 500);
        }
    }

    /**
     * Get account statistics
     */
    public function getStatistics($id)
    {
        try {
            $account = $this->accountRepository->getAccountByIdWithRelations($id);

            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            $statistics = [
                'total_balance' => $account->balance,
                'available_credit' => $account->available_credit,
                'total_vehicles' => $account->accountVehicles->count(),
                'active_bundle_subscriptions' => $account->bundleSubscriptions()->where('status', 'active')->count(),
                'total_passages' => $account->vehiclePassages->count(),
                'recent_transactions' => $account->transactions()->latest()->take(5)->get(),
                'recent_invoices' => $account->invoices()->latest()->take(5)->get(),
            ];

            return $this->sendResponse($statistics, 'Account statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving account statistics', $e->getMessage(), 500);
        }
    }
}
