<?php

namespace App\Repositories;

use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentTypeRepository
{
    protected $model;

    public function __construct(PaymentType $model)
    {
        $this->model = $model;
    }

    /**
     * Get all payment types with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaymentTypesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with('vehiclePassages')->paginate($perPage);
    }

    /**
     * Get all active payment types
     *
     * @return Collection
     */
    public function getAllActivePaymentTypes(): Collection
    {
        return $this->model->active()->with('vehiclePassages')->get();
    }

    /**
     * Get payment type by ID with vehicle passages
     *
     * @param int $id
     * @return PaymentType|null
     */
    public function getPaymentTypeByIdWithVehiclePassages(int $id): ?PaymentType
    {
        return $this->model->with('vehiclePassages')->find($id);
    }

    /**
     * Get payment type by name
     *
     * @param string $name
     * @return PaymentType|null
     */
    public function getPaymentTypeByName(string $name): ?PaymentType
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * Create a new payment type
     *
     * @param array $data
     * @return PaymentType
     */
    public function createPaymentType(array $data): PaymentType
    {
        return $this->model->create($data);
    }

    /**
     * Update payment type by ID
     *
     * @param int $id
     * @param array $data
     * @return PaymentType|null
     */
    public function updatePaymentType(int $id, array $data): ?PaymentType
    {
        $paymentType = $this->model->find($id);
        if ($paymentType) {
            $paymentType->update($data);
            return $paymentType->fresh();
        }
        return null;
    }

    /**
     * Delete payment type by ID
     *
     * @param int $id
     * @return bool
     */
    public function deletePaymentType(int $id): bool
    {
        $paymentType = $this->model->find($id);
        if ($paymentType) {
            return $paymentType->delete();
        }
        return false;
    }

    /**
     * Get payment types with vehicle passage count
     *
     * @return Collection
     */
    public function getPaymentTypesWithVehiclePassageCount(): Collection
    {
        return $this->model->withCount('vehiclePassages')->get();
    }

    /**
     * Search payment types by name or description
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchPaymentTypes(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->with('vehiclePassages')
            ->paginate($perPage);
    }

    /**
     * Get payment types for dropdown/select
     *
     * @return Collection
     */
    public function getPaymentTypesForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'description')
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get payment type usage statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getPaymentTypeUsageStatistics(string $startDate, string $endDate): Collection
    {
        return $this->model->withCount(['vehiclePassages' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('entry_time', [$startDate, $endDate]);
        }])->active()->get();
    }

    /**
     * Get payment types with recent usage
     *
     * @param int $days
     * @return Collection
     */
    public function getPaymentTypesWithRecentUsage(int $days = 30): Collection
    {
        return $this->model->withCount(['vehiclePassages' => function ($query) use ($days) {
            $query->where('entry_time', '>=', now()->subDays($days));
        }])->active()->get();
    }
}
