<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\CustomerAccountRequest;
use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\CustomerRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAccountController extends BaseController
{
    protected $accountRepository;
    protected $customerRepository;

    public function __construct(AccountRepository $accountRepository, CustomerRepository $customerRepository)
    {
        $this->accountRepository = $accountRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Display a listing of customer accounts
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $accounts = Account::with(['customer.user', 'accountVehicles.vehicle', 'bundleSubscriptions'])
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('account_number', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                $customerQuery->where('name', 'like', "%{$search}%")
                                    ->orWhereHas('user', function ($userQuery) use ($search) {
                                        $userQuery->where('email', 'like', "%{$search}%")
                                            ->orWhere('phone', 'like', "%{$search}%");
                                    });
                            });
                    });
                })
                ->paginate($perPage);

            return $this->sendResponse($accounts, 'Customer accounts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving customer accounts', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created customer account (User + Customer + Account)
     */
    public function store(CustomerAccountRequest $request)
    {
        try {
            DB::beginTransaction();

            // Generate unique identifiers
            $customerNumber = $this->generateCustomerNumber();
            $accountNumber = $request->account_number ?: $this->generateAccountNumber();

            // Create User
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make('12341234'), // Default password set by backend
                'address' => $request->address,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'is_active' => true,
                'role_id' => 3, // Customer role
            ]);

            // Create Customer
            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_number' => $customerNumber,
                'name' => $request->name,
                'company_name' => $request->company_name,
                'customer_type' => $request->customer_type,
            ]);

            // Create Account
            $account = Account::create([
                'customer_id' => $customer->id,
                'account_number' => $accountNumber,
                'name' => $request->account_name,
                'account_type' => $request->account_type,
                'balance' => $request->initial_balance,
                'credit_limit' => $request->credit_limit ?: 0,
                'is_active' => true,
            ]);

            DB::commit();

            // Load relationships
            $account->load(['customer.user', 'accountVehicles.vehicle', 'bundleSubscriptions']);

            return $this->sendResponse($account, 'Customer account created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error creating customer account', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified customer account
     */
    public function show($id)
    {
        try {
            $account = Account::with(['customer.user', 'accountVehicles.vehicle', 'bundleSubscriptions'])
                ->find($id);

            if (!$account) {
                return $this->sendError('Customer account not found', [], 404);
            }

            return $this->sendResponse($account, 'Customer account retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving customer account', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified customer account
     */
    public function update(CustomerAccountRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $account = Account::with(['customer.user'])->find($id);
            
            if (!$account) {
                return $this->sendError('Customer account not found', [], 404);
            }

            // Update User
            if ($account->customer && $account->customer->user) {
                $user = $account->customer->user;
                $user->update([
                    'username' => $request->username ?: $user->username,
                    'email' => $request->email ?: $user->email,
                    'phone' => $request->phone ?: $user->phone,
                    'address' => $request->address ?: $user->address,
                    'gender' => $request->gender ?: $user->gender,
                    'date_of_birth' => $request->date_of_birth ?: $user->date_of_birth,
                    'is_active' => $request->has('is_active') ? $request->is_active : $user->is_active,
                    // Password is not updated - kept as default
                ]);
            }

            // Update Customer
            $customer = $account->customer;
            $customer->update([
                'name' => $request->name ?: $customer->name,
                'company_name' => $request->company_name ?: $customer->company_name,
                'customer_type' => $request->customer_type ?: $customer->customer_type,
            ]);

            // Update Account
            $account->update([
                'name' => $request->account_name ?: $account->name,
                'account_type' => $request->account_type ?: $account->account_type,
                'balance' => $request->balance ?: $account->balance,
                'credit_limit' => $request->credit_limit ?: $account->credit_limit,
                'is_active' => $request->has('account_is_active') ? $request->account_is_active : $account->is_active,
            ]);

            DB::commit();

            // Load relationships
            $account->load(['customer.user', 'accountVehicles.vehicle', 'bundleSubscriptions']);

            return $this->sendResponse($account, 'Customer account updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error updating customer account', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified customer account (soft delete)
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $account = Account::find($id);
            
            if (!$account) {
                return $this->sendError('Customer account not found', [], 404);
            }

            // Soft delete account
            $account->delete();

            // Soft delete customer
            if ($account->customer) {
                $account->customer->delete();
            }

            // Soft delete user
            if ($account->customer && $account->customer->user) {
                $account->customer->user->delete();
            }

            DB::commit();

            return $this->sendResponse([], 'Customer account deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Error deleting customer account', $e->getMessage(), 500);
        }
    }

    /**
     * Add vehicle to account
     */
    public function addVehicle(Request $request, $accountId)
    {
        try {
            $account = Account::find($accountId);
            
            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            $request->validate([
                'vehicle_id' => 'required|integer|exists:vehicles,id',
                'is_primary' => 'boolean',
            ]);

            // Check if vehicle is already associated with this account
            $existing = $account->accountVehicles()->where('vehicle_id', $request->vehicle_id)->first();
            if ($existing) {
                return $this->sendError('Vehicle is already associated with this account', [], 400);
            }

            // If this is set as primary, unset other primary vehicles
            if ($request->is_primary) {
                $account->accountVehicles()->update(['is_primary' => false]);
            }

            $accountVehicle = $account->accountVehicles()->create([
                'vehicle_id' => $request->vehicle_id,
                'is_primary' => $request->is_primary ?: false,
                'registered_at' => now(),
            ]);

            $accountVehicle->load('vehicle');

            return $this->sendResponse($accountVehicle, 'Vehicle added to account successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error adding vehicle to account', $e->getMessage(), 500);
        }
    }

    /**
     * Remove vehicle from account
     */
    public function removeVehicle($accountId, $vehicleId)
    {
        try {
            $account = Account::find($accountId);
            
            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            $accountVehicle = $account->accountVehicles()->where('vehicle_id', $vehicleId)->first();
            
            if (!$accountVehicle) {
                return $this->sendError('Vehicle not found in this account', [], 404);
            }

            $accountVehicle->delete();

            return $this->sendResponse([], 'Vehicle removed from account successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error removing vehicle from account', $e->getMessage(), 500);
        }
    }

    /**
     * Get vehicles for account
     */
    public function getVehicles($accountId)
    {
        try {
            $account = Account::find($accountId);
            
            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            $vehicles = $account->accountVehicles()->with('vehicle.bodyType')->get();

            return $this->sendResponse($vehicles, 'Account vehicles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving account vehicles', $e->getMessage(), 500);
        }
    }

    /**
     * Generate unique customer number
     */
    private function generateCustomerNumber(): string
    {
        do {
            $customerNumber = 'CUST' . str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while (Customer::where('customer_number', $customerNumber)->exists());

        return $customerNumber;
    }

    /**
     * Generate unique account number (alphanumeric pattern)
     */
    private function generateAccountNumber(): string
    {
        do {
            // Generate alphanumeric account number: ACC + 6 alphanumeric characters
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $randomString = '';
            for ($i = 0; $i < 6; $i++) {
                $randomString .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $accountNumber = 'ACC' . $randomString;
        } while (Account::where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }
}
