<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\BundleSubscriptionRequest;
use App\Repositories\BundleSubscriptionRepository;
use Illuminate\Http\Request;

class BundleSubscriptionController extends BaseController
{
    protected $bundleSubscriptionRepository;

    public function __construct(BundleSubscriptionRepository $bundleSubscriptionRepository)
    {
        $this->bundleSubscriptionRepository = $bundleSubscriptionRepository;
    }

    /**
     * Display a listing of bundle subscriptions
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $subscriptions = $this->bundleSubscriptionRepository->searchBundleSubscriptions($search, $perPage);
            } else {
                $subscriptions = $this->bundleSubscriptionRepository->getAllBundleSubscriptionsPaginated($perPage);
            }

            return $this->sendResponse($subscriptions, 'Bundle subscriptions retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle subscriptions', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created bundle subscription
     */
    public function store(BundleSubscriptionRequest $request)
    {
        try {
            $subscription = $this->bundleSubscriptionRepository->createBundleSubscription($request->validated());

            return $this->sendResponse($subscription, 'Bundle subscription created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating bundle subscription', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified bundle subscription
     */
    public function show($id)
    {
        try {
            $subscription = $this->bundleSubscriptionRepository->getBundleSubscriptionById($id);

            if (!$subscription) {
                return $this->sendError('Bundle subscription not found', [], 404);
            }

            return $this->sendResponse($subscription, 'Bundle subscription retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle subscription', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified bundle subscription
     */
    public function update(BundleSubscriptionRequest $request, $id)
    {
        try {
            $subscription = $this->bundleSubscriptionRepository->updateBundleSubscription($id, $request->validated());

            if (!$subscription) {
                return $this->sendError('Bundle subscription not found', [], 404);
            }

            return $this->sendResponse($subscription, 'Bundle subscription updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating bundle subscription', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified bundle subscription
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->bundleSubscriptionRepository->deleteBundleSubscription($id);

            if (!$deleted) {
                return $this->sendError('Bundle subscription not found', [], 404);
            }

            return $this->sendResponse([], 'Bundle subscription deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting bundle subscription', $e->getMessage(), 500);
        }
    }

    /**
     * Update bundle subscription status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,active,suspended,expired,cancelled'
            ]);

            $subscription = $this->bundleSubscriptionRepository->updateBundleSubscriptionStatus($id, $request->status);

            if (!$subscription) {
                return $this->sendError('Bundle subscription not found', [], 404);
            }

            return $this->sendResponse($subscription, 'Bundle subscription status updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating bundle subscription status', $e->getMessage(), 500);
        }
    }

    /**
     * Get active bundle subscriptions
     */
    public function getActiveSubscriptions()
    {
        try {
            $subscriptions = $this->bundleSubscriptionRepository->getAllActiveBundleSubscriptions();
            return $this->sendResponse($subscriptions, 'Active bundle subscriptions retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active bundle subscriptions', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundle subscriptions by account
     */
    public function getByAccount($accountId)
    {
        try {
            $subscriptions = $this->bundleSubscriptionRepository->getBundleSubscriptionsByAccount($accountId);
            return $this->sendResponse($subscriptions, "Bundle subscriptions for account '{$accountId}' retrieved successfully");
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle subscriptions by account', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundle subscriptions by bundle
     */
    public function getByBundle($bundleId)
    {
        try {
            $subscriptions = $this->bundleSubscriptionRepository->getBundleSubscriptionsByBundle($bundleId);
            return $this->sendResponse($subscriptions, "Bundle subscriptions for bundle '{$bundleId}' retrieved successfully");
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle subscriptions by bundle', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundle subscriptions with usage statistics
     */
    public function getWithUsageStats()
    {
        try {
            $subscriptions = $this->bundleSubscriptionRepository->getBundleSubscriptionsWithUsageStats();
            return $this->sendResponse($subscriptions, 'Bundle subscriptions with usage statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle subscriptions with usage statistics', $e->getMessage(), 500);
        }
    }

    /**
     * Get expiring bundle subscriptions
     */
    public function getExpiringSubscriptions(Request $request)
    {
        try {
            $days = $request->get('days', 7);
            $subscriptions = $this->bundleSubscriptionRepository->getExpiringBundleSubscriptions($days);
            return $this->sendResponse($subscriptions, "Expiring bundle subscriptions (within {$days} days) retrieved successfully");
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving expiring bundle subscriptions', $e->getMessage(), 500);
        }
    }
}
