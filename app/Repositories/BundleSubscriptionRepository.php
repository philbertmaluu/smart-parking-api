<?php

namespace App\Repositories;

use App\Models\BundleSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BundleSubscriptionRepository
{
    protected $model;

    public function __construct(BundleSubscription $model)
    {
        $this->model = $model;
    }

    /**
     * Get all bundle subscriptions with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllBundleSubscriptionsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['account.customer', 'bundle.bundleType'])->paginate($perPage);
    }

    /**
     * Get all active bundle subscriptions
     *
     * @return Collection
     */
    public function getAllActiveBundleSubscriptions(): Collection
    {
        return $this->model->with(['account.customer', 'bundle.bundleType'])
            ->where('status', 'active')
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now())
            ->get();
    }

    /**
     * Get bundle subscription by ID with relationships
     *
     * @param int $id
     * @return BundleSubscription|null
     */
    public function getBundleSubscriptionById(int $id): ?BundleSubscription
    {
        return $this->model->with(['account.customer', 'bundle.bundleType', 'vehiclePassages'])->find($id);
    }

    /**
     * Get bundle subscriptions by account
     *
     * @param int $accountId
     * @return Collection
     */
    public function getBundleSubscriptionsByAccount(int $accountId): Collection
    {
        return $this->model->with(['bundle.bundleType'])
            ->where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get bundle subscriptions by bundle
     *
     * @param int $bundleId
     * @return Collection
     */
    public function getBundleSubscriptionsByBundle(int $bundleId): Collection
    {
        return $this->model->with(['account.customer'])
            ->where('bundle_id', $bundleId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a new bundle subscription
     *
     * @param array $data
     * @return BundleSubscription
     */
    public function createBundleSubscription(array $data): BundleSubscription
    {
        // Generate unique subscription number
        $data['subscription_number'] = $this->generateSubscriptionNumber();
        
        return $this->model->create($data);
    }

    /**
     * Update bundle subscription by ID
     *
     * @param int $id
     * @param array $data
     * @return BundleSubscription|null
     */
    public function updateBundleSubscription(int $id, array $data): ?BundleSubscription
    {
        $subscription = $this->model->find($id);
        if ($subscription) {
            $subscription->update($data);
            return $subscription->fresh(['account.customer', 'bundle.bundleType']);
        }
        return null;
    }

    /**
     * Delete bundle subscription by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteBundleSubscription(int $id): bool
    {
        $subscription = $this->model->find($id);
        if ($subscription) {
            return $subscription->delete();
        }
        return false;
    }

    /**
     * Update bundle subscription status
     *
     * @param int $id
     * @param string $status
     * @return BundleSubscription|null
     */
    public function updateBundleSubscriptionStatus(int $id, string $status): ?BundleSubscription
    {
        $subscription = $this->model->find($id);
        if ($subscription) {
            $subscription->update(['status' => $status]);
            return $subscription->fresh(['account.customer', 'bundle.bundleType']);
        }
        return null;
    }

    /**
     * Search bundle subscriptions by subscription number, customer name, or bundle name
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchBundleSubscriptions(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['account.customer', 'bundle.bundleType'])
            ->where('subscription_number', 'like', "%{$search}%")
            ->orWhereHas('account.customer', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
            })
            ->orWhereHas('bundle', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->paginate($perPage);
    }

    /**
     * Get bundle subscriptions with usage statistics
     *
     * @return Collection
     */
    public function getBundleSubscriptionsWithUsageStats(): Collection
    {
        return $this->model->withCount('vehiclePassages')
            ->with(['account.customer', 'bundle.bundleType'])
            ->get();
    }

    /**
     * Get expiring bundle subscriptions
     *
     * @param int $days
     * @return Collection
     */
    public function getExpiringBundleSubscriptions(int $days = 7): Collection
    {
        return $this->model->with(['account.customer', 'bundle.bundleType'])
            ->where('status', 'active')
            ->where('end_datetime', '<=', now()->addDays($days))
            ->where('end_datetime', '>', now())
            ->get();
    }

    /**
     * Generate unique subscription number
     *
     * @return string
     */
    private function generateSubscriptionNumber(): string
    {
        do {
            $number = 'SUB-' . strtoupper(uniqid());
        } while ($this->model->where('subscription_number', $number)->exists());

        return $number;
    }
}
