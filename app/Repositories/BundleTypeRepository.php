<?php

namespace App\Repositories;

use App\Models\BundleType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BundleTypeRepository
{
    protected $model;

    public function __construct(BundleType $model)
    {
        $this->model = $model;
    }

    /**
     * Get all bundle types with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllBundleTypesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with('bundles')->paginate($perPage);
    }

    /**
     * Get all active bundle types
     *
     * @return Collection
     */
    public function getAllActiveBundleTypes(): Collection
    {
        return $this->model->active()->with('bundles')->get();
    }

    /**
     * Get bundle type by ID with bundles
     *
     * @param int $id
     * @return BundleType|null
     */
    public function getBundleTypeByIdWithBundles(int $id): ?BundleType
    {
        return $this->model->with('bundles')->find($id);
    }

    /**
     * Get bundle type by name
     *
     * @param string $name
     * @return BundleType|null
     */
    public function getBundleTypeByName(string $name): ?BundleType
    {
        return $this->model->where('name', $name)->first();
    }

    /**
     * Get bundle types by duration
     *
     * @param int $durationDays
     * @return Collection
     */
    public function getBundleTypesByDuration(int $durationDays): Collection
    {
        return $this->model->byDuration($durationDays)->get();
    }

    /**
     * Create a new bundle type
     *
     * @param array $data
     * @return BundleType
     */
    public function createBundleType(array $data): BundleType
    {
        return $this->model->create($data);
    }

    /**
     * Update bundle type by ID
     *
     * @param int $id
     * @param array $data
     * @return BundleType|null
     */
    public function updateBundleType(int $id, array $data): ?BundleType
    {
        $bundleType = $this->model->find($id);
        if ($bundleType) {
            $bundleType->update($data);
            return $bundleType->fresh();
        }
        return null;
    }

    /**
     * Delete bundle type by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteBundleType(int $id): bool
    {
        $bundleType = $this->model->find($id);
        if ($bundleType) {
            return $bundleType->delete();
        }
        return false;
    }

    /**
     * Get bundle types with bundle count
     *
     * @return Collection
     */
    public function getBundleTypesWithBundleCount(): Collection
    {
        return $this->model->withCount('bundles')->get();
    }

    /**
     * Search bundle types by name or description
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchBundleTypes(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->with('bundles')
            ->paginate($perPage);
    }

    /**
     * Get bundle types for dropdown/select
     *
     * @return Collection
     */
    public function getBundleTypesForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'duration_days')
            ->active()
            ->orderBy('duration_days')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get bundle types with active bundles
     *
     * @return Collection
     */
    public function getBundleTypesWithActiveBundles(): Collection
    {
        return $this->model->with(['bundles' => function ($query) {
            $query->active();
        }])->active()->get();
    }

    /**
     * Get bundle types by duration with bundles
     *
     * @param int $durationDays
     * @return Collection
     */
    public function getBundleTypesByDurationWithBundles(int $durationDays): Collection
    {
        return $this->model->byDuration($durationDays)
            ->with(['bundles' => function ($query) {
                $query->active();
            }])
            ->active()
            ->get();
    }

    /**
     * Get popular bundle types (most used)
     *
     * @param int $limit
     * @return Collection
     */
    public function getPopularBundleTypes(int $limit = 10): Collection
    {
        return $this->model->withCount(['bundles' => function ($query) {
            $query->withCount('bundleSubscriptions');
        }])->orderBy('bundles_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Toggle the active status of a bundle type by ID
     *
     * @param int $id
     * @return BundleType|null
     */
    public function toggleBundleTypeStatus(int $id): ?BundleType
    {
        $bundleType = $this->model->find($id);
        if (!$bundleType) {
            return null;
        }

        $bundleType->is_active = !$bundleType->is_active;
        $bundleType->save();

        return $bundleType->fresh();
    }
}
