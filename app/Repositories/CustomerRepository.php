<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerRepository
{
    protected $model;

    public function __construct(Customer $model)
    {
        $this->model = $model;
    }

    /**
     * Get all customers with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllCustomersPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['user', 'accounts'])->paginate($perPage);
    }

    /**
     * Get all active customers
     *
     * @return Collection
     */
    public function getAllActiveCustomers(): Collection
    {
        return $this->model->with(['user'])->get();
    }

    /**
     * Get customer by ID with relationships
     *
     * @param int $id
     * @return Customer|null
     */
    public function getCustomerByIdWithRelations(int $id): ?Customer
    {
        return $this->model->with(['user', 'accounts'])->find($id);
    }

    /**
     * Get customers by type
     *
     * @param string $type
     * @return Collection
     */
    public function getCustomersByType(string $type): Collection
    {
        return $this->model->byType($type)->with(['user'])->get();
    }

    /**
     * Create a new customer
     *
     * @param array $data
     * @return Customer
     */
    public function createCustomer(array $data): Customer
    {
        return $this->model->create($data);
    }

    /**
     * Update customer by ID
     *
     * @param int $id
     * @param array $data
     * @return Customer|null
     */
    public function updateCustomer(int $id, array $data): ?Customer
    {
        $customer = $this->model->find($id);
        if ($customer) {
            $customer->update($data);
            return $customer->fresh();
        }
        return null;
    }

    /**
     * Delete customer by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteCustomer(int $id): bool
    {
        $customer = $this->model->find($id);
        if ($customer) {
            return $customer->delete();
        }
        return false;
    }

    /**
     * Search customers by name, company name, or customer number
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchCustomers(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('company_name', 'like', "%{$search}%")
            ->orWhere('customer_number', 'like', "%{$search}%")
            ->with(['user', 'accounts'])
            ->paginate($perPage);
    }

    /**
     * Get customers for dropdown/select
     *
     * @return Collection
     */
    public function getActiveCustomersForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'company_name', 'customer_type', 'customer_number')
            ->with('user:id,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get customer statistics
     *
     * @param int $customerId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getCustomerStatistics(int $customerId, string $startDate, string $endDate): array
    {
        $customer = $this->model->with(['accounts.transactions' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }])->find($customerId);

        if (!$customer) {
            return [];
        }

        $transactions = $customer->accounts->flatMap->transactions;

        return [
            'total_accounts' => $customer->accounts->count(),
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum('amount'),
            'active_accounts' => $customer->accounts->where('is_active', true)->count(),
        ];
    }

    /**
     * Get customers with recent activity
     *
     * @param int $days
     * @return Collection
     */
    public function getCustomersWithRecentActivity(int $days = 7): Collection
    {
        return $this->model->withCount(['accounts' => function ($query) use ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        }])->get();
    }
}
