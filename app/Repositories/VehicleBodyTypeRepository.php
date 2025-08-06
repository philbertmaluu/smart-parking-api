<?php

namespace App\Repositories;

use App\Models\VehicleBodyType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VehicleBodyTypeRepository
{
    protected $model;

    public function __construct(VehicleBodyType $model)
    {
        $this->model = $model;
    }

    /**
     * Get all vehicle body types with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllVehicleBodyTypesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['vehicles', 'prices', 'bundleVehicles'])->paginate($perPage);
    }

    /**
     * Get all active vehicle body types
     *
     * @return Collection
     */
    public function getAllActiveVehicleBodyTypes(): Collection
    {
        return $this->model->active()->with(['vehicles', 'prices'])->get();
    }

    /**
     * Get vehicle body type by ID with relationships
     *
     * @param int $id
     * @return VehicleBodyType|null
     */
    public function getVehicleBodyTypeByIdWithRelations(int $id): ?VehicleBodyType
    {
        return $this->model->with(['vehicles', 'prices', 'bundleVehicles'])->find($id);
    }

    /**
     * Get vehicle body type by name
     *
     * @param string $name
     * @return VehicleBodyType|null
     */
    public function getVehicleBodyTypeByName(string $name): ?VehicleBodyType
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * Get vehicle body types by category
     *
     * @param string $category
     * @return Collection
     */
    public function getVehicleBodyTypesByCategory(string $category): Collection
    {
        return $this->model->byCategory($category)->get();
    }

    /**
     * Create a new vehicle body type
     *
     * @param array $data
     * @return VehicleBodyType
     */
    public function createVehicleBodyType(array $data): VehicleBodyType
    {
        return $this->model->create($data);
    }

    /**
     * Update vehicle body type by ID
     *
     * @param int $id
     * @param array $data
     * @return VehicleBodyType|null
     */
    public function updateVehicleBodyType(int $id, array $data): ?VehicleBodyType
    {
        $vehicleBodyType = $this->model->find($id);
        if ($vehicleBodyType) {
            $vehicleBodyType->update($data);
            return $vehicleBodyType->fresh();
        }
        return null;
    }

    /**
     * Delete vehicle body type by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteVehicleBodyType(int $id): bool
    {
        $vehicleBodyType = $this->model->find($id);
        if ($vehicleBodyType) {
            return $vehicleBodyType->delete();
        }
        return false;
    }

    /**
     * Get vehicle body types with vehicle count
     *
     * @return Collection
     */
    public function getVehicleBodyTypesWithVehicleCount(): Collection
    {
        return $this->model->withCount('vehicles')->get();
    }

    /**
     * Search vehicle body types by name or description
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchVehicleBodyTypes(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->orWhere('category', 'like', "%{$search}%")
            ->with(['vehicles', 'prices'])
            ->paginate($perPage);
    }

    /**
     * Get vehicle body types for dropdown/select
     *
     * @return Collection
     */
    public function getVehicleBodyTypesForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'category')
            ->active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get vehicle body types with current pricing
     *
     * @param int $stationId
     * @return Collection
     */
    public function getVehicleBodyTypesWithCurrentPricing(int $stationId): Collection
    {
        return $this->model->with(['prices' => function ($query) use ($stationId) {
            $query->where('station_id', $stationId)
                ->effective();
        }])->active()->get();
    }

    /**
     * Get vehicle body types by category with pricing
     *
     * @param string $category
     * @param int $stationId
     * @return Collection
     */
    public function getVehicleBodyTypesByCategoryWithPricing(string $category, int $stationId): Collection
    {
        return $this->model->byCategory($category)
            ->with(['prices' => function ($query) use ($stationId) {
                $query->where('station_id', $stationId)
                    ->effective();
            }])
            ->active()
            ->get();
    }

    /**
     * Get vehicle body types with bundle information
     *
     * @param int $bundleId
     * @return Collection
     */
    public function getVehicleBodyTypesWithBundleInfo(int $bundleId): Collection
    {
        return $this->model->with(['bundleVehicles' => function ($query) use ($bundleId) {
            $query->where('bundle_id', $bundleId);
        }])->active()->get();
    }
}
