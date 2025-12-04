<?php

namespace App\Repositories;

use App\Models\Bundle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BundleRepository
{
    protected $model;

    public function __construct(Bundle $model)
    {
        $this->model = $model;
    }

    /**
     * Get all bundles with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllBundlesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with('bundleType')->paginate($perPage);
    }

    /**
     * Get all active bundles
     *
     * @return Collection
     */
    public function getAllActiveBundles(): Collection
    {
        return $this->model->active()->with('bundleType')->get();
    }

    /**
     * Get bundle by ID with bundle type
     *
     * @param int $id
     * @return Bundle|null
     */
    public function getBundleByIdWithBundleType(int $id): ?Bundle
    {
        return $this->model->with('bundleType')->find($id);
    }

    /**
     * Get bundles by bundle type
     *
     * @param int $bundleTypeId
     * @return Collection
     */
    public function getBundlesByType(int $bundleTypeId): Collection
    {
        return $this->model->byType($bundleTypeId)->with('bundleType')->get();
    }

    /**
     * Get bundles within a price range
     *
     * @param float $minAmount
     * @param float $maxAmount
     * @return Collection
     */
    public function getBundlesByPriceRange(float $minAmount, float $maxAmount): Collection
    {
        return $this->model->byPriceRange($minAmount, $maxAmount)->with('bundleType')->get();
    }

    /**
     * Create a new bundle
     *
     * @param array $data
     * @return Bundle
     */
    public function createBundle(array $data): Bundle
    {
        return $this->model->create($data);
    }

    /**
     * Update bundle by ID
     *
     * @param int $id
     * @param array $data
     * @return Bundle|null
     */
    public function updateBundle(int $id, array $data): ?Bundle
    {
        $bundle = $this->model->find($id);
        if ($bundle) {
            $bundle->update($data);
            return $bundle->fresh(['bundleType']);
        }
        return null;
    }

    /**
     * Delete bundle by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteBundle(int $id): bool
    {
        $bundle = $this->model->find($id);
        if ($bundle) {
            return $bundle->delete();
        }
        return false;
    }

    /**
     * Toggle bundle active status
     *
     * @param int $id
     * @return Bundle|null
     */
    public function toggleBundleStatus(int $id): ?Bundle
    {
        $bundle = $this->model->find($id);
        if ($bundle) {
            $bundle->update(['is_active' => !$bundle->is_active]);
            return $bundle->fresh(['bundleType']);
        }
        return null;
    }

    /**
     * Search bundles by name or description
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchBundles(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%")
            ->with('bundleType')
            ->paginate($perPage);
    }

    /**
     * Get bundles with subscription count
     *
     * @return Collection
     */
    public function getBundlesWithSubscriptionCount(): Collection
    {
        return $this->model->withCount('bundleSubscriptions')->with('bundleType')->get();
    }

    /**
     * Get popular bundles (most subscribed)
     *
     * @param int $limit
     * @return Collection
     */
    public function getPopularBundles(int $limit = 10): Collection
    {
        return $this->model->withCount('bundleSubscriptions')
            ->with('bundleType')
            ->orderBy('bundle_subscriptions_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get bundles for select dropdown
     *
     * @return Collection
     */
    public function getBundlesForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'amount', 'bundle_type_id')
            ->active()
            ->with('bundleType:id,name')
            ->orderBy('name')
            ->get();
    }
}
