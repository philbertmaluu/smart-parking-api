<?php

namespace App\Repositories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountRepository
{
    protected $model;

    public function __construct(Account $model)
    {
        $this->model = $model;
    }

    /**
     * Get all accounts with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllAccountsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['customer', 'accountVehicles', 'bundleSubscriptions'])
            ->paginate($perPage);
    }

    /**
     * Search accounts by name, account number, or customer name
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchAccounts(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['customer', 'accountVehicles', 'bundleSubscriptions'])
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            })
            ->paginate($perPage);
    }

    /**
     * Get account by ID with relationships
     *
     * @param int $id
     * @return Account|null
     */
    public function getAccountByIdWithRelations(int $id): ?Account
    {
        return $this->model->with([
            'customer',
            'accountVehicles.vehicle',
            'bundleSubscriptions.bundle.bundleType',
            'vehiclePassages',
            'transactions',
            'invoices'
        ])->find($id);
    }

    /**
     * Get active accounts
     *
     * @return Collection
     */
    public function getActiveAccounts(): Collection
    {
        return $this->model->active()
            ->with(['customer', 'accountVehicles'])
            ->get();
    }

    /**
     * Get accounts by type
     *
     * @param string $type
     * @return Collection
     */
    public function getAccountsByType(string $type): Collection
    {
        return $this->model->byType($type)
            ->with(['customer', 'accountVehicles'])
            ->get();
    }

    /**
     * Get accounts by customer
     *
     * @param int $customerId
     * @return Collection
     */
    public function getAccountsByCustomer(int $customerId): Collection
    {
        return $this->model->where('customer_id', $customerId)
            ->with(['customer', 'accountVehicles', 'bundleSubscriptions'])
            ->get();
    }

    /**
     * Create a new account
     *
     * @param array $data
     * @return Account
     */
    public function createAccount(array $data): Account
    {
        // Generate unique account number if not provided
        if (!isset($data['account_number'])) {
            $data['account_number'] = $this->generateAccountNumber();
        }

        return $this->model->create($data);
    }

    /**
     * Update account by ID
     *
     * @param int $id
     * @param array $data
     * @return Account|null
     */
    public function updateAccount(int $id, array $data): ?Account
    {
        $account = $this->model->find($id);
        if ($account) {
            $account->update($data);
            return $account->fresh();
        }
        return null;
    }

    /**
     * Delete account by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteAccount(int $id): bool
    {
        $account = $this->model->find($id);
        if ($account) {
            return $account->delete();
        }
        return false;
    }

    /**
     * Toggle account status
     *
     * @param int $id
     * @return Account|null
     */
    public function toggleAccountStatus(int $id): ?Account
    {
        $account = $this->model->find($id);
        if ($account) {
            $account->update(['is_active' => !$account->is_active]);
            return $account->fresh();
        }
        return null;
    }

    /**
     * Get accounts with balance information
     *
     * @param array $filters
     * @return Collection
     */
    public function getAccountsWithBalanceInfo(array $filters = []): Collection
    {
        $query = $this->model->with(['customer']);

        if (isset($filters['min_balance'])) {
            $query->where('balance', '>=', $filters['min_balance']);
        }

        if (isset($filters['max_balance'])) {
            $query->where('balance', '<=', $filters['max_balance']);
        }

        if (isset($filters['account_type'])) {
            $query->byType($filters['account_type']);
        }

        return $query->get();
    }

    /**
     * Get accounts with low balance
     *
     * @param float $threshold
     * @return Collection
     */
    public function getAccountsWithLowBalance(float $threshold = 100.00): Collection
    {
        return $this->model->prepaid()
            ->where('balance', '<', $threshold)
            ->where('is_active', true)
            ->with(['customer'])
            ->get();
    }

    /**
     * Get accounts by account number
     *
     * @param string $accountNumber
     * @return Account|null
     */
    public function getAccountByAccountNumber(string $accountNumber): ?Account
    {
        return $this->model->byAccountNumber($accountNumber)
            ->with(['customer', 'accountVehicles', 'bundleSubscriptions'])
            ->first();
    }

    /**
     * Generate unique account number
     *
     * @return string
     */
    private function generateAccountNumber(): string
    {
        do {
            $accountNumber = 'ACC' . str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->model->where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }
}
