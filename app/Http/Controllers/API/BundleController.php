<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\BundleRequest;
use App\Repositories\BundleRepository;
use Illuminate\Http\Request;

class BundleController extends BaseController
{
    protected $bundleRepository;

    public function __construct(BundleRepository $bundleRepository)
    {
        $this->bundleRepository = $bundleRepository;
    }

    /**
     * Display a listing of bundles
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $bundles = $this->bundleRepository->searchBundles($search, $perPage);
            } else {
                $bundles = $this->bundleRepository->getAllBundlesPaginated($perPage);
            }

            return $this->sendResponse($bundles, 'Bundles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundles', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created bundle
     */
    public function store(BundleRequest $request)
    {
        try {
            $bundle = $this->bundleRepository->createBundle($request->validated());

            return $this->sendResponse($bundle, 'Bundle created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating bundle', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified bundle
     */
    public function show($id)
    {
        try {
            $bundle = $this->bundleRepository->getBundleByIdWithBundleType($id);

            if (!$bundle) {
                return $this->sendError('Bundle not found', [], 404);
            }

            return $this->sendResponse($bundle, 'Bundle retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified bundle
     */
    public function update(BundleRequest $request, $id)
    {
        try {
            $bundle = $this->bundleRepository->updateBundle($id, $request->validated());

            if (!$bundle) {
                return $this->sendError('Bundle not found', [], 404);
            }

            return $this->sendResponse($bundle, 'Bundle updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating bundle', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified bundle
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->bundleRepository->deleteBundle($id);

            if (!$deleted) {
                return $this->sendError('Bundle not found', [], 404);
            }

            return $this->sendResponse([], 'Bundle deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting bundle', $e->getMessage(), 500);
        }
    }

    /**
     * Toggle bundle active status
     */
    public function toggleStatus($id)
    {
        try {
            $bundle = $this->bundleRepository->toggleBundleStatus($id);

            if (!$bundle) {
                return $this->sendError('Bundle not found', [], 404);
            }

            return $this->sendResponse($bundle, 'Bundle status toggled successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error toggling bundle status', $e->getMessage(), 500);
        }
    }

    /**
     * Get active bundles for dropdown
     */
    public function getActiveBundles()
    {
        try {
            $bundles = $this->bundleRepository->getBundlesForSelect();
            return $this->sendResponse($bundles, 'Active bundles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active bundles', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundles by type
     */
    public function getByType($bundleTypeId)
    {
        try {
            $bundles = $this->bundleRepository->getBundlesByType($bundleTypeId);
            return $this->sendResponse($bundles, "Bundles for type '{$bundleTypeId}' retrieved successfully");
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundles by type', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundles within price range
     */
    public function getByPriceRange(Request $request)
    {
        try {
            $request->validate([
                'min_amount' => 'required|numeric|min:0',
                'max_amount' => 'required|numeric|min:0|gte:min_amount',
            ]);

            $bundles = $this->bundleRepository->getBundlesByPriceRange(
                $request->min_amount,
                $request->max_amount
            );

            return $this->sendResponse($bundles, 'Bundles within price range retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundles by price range', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundles with subscription count
     */
    public function getWithSubscriptionCount()
    {
        try {
            $bundles = $this->bundleRepository->getBundlesWithSubscriptionCount();
            return $this->sendResponse($bundles, 'Bundles with subscription count retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundles with subscription count', $e->getMessage(), 500);
        }
    }

    /**
     * Get popular bundles
     */
    public function getPopular(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $bundles = $this->bundleRepository->getPopularBundles($limit);
            return $this->sendResponse($bundles, 'Popular bundles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving popular bundles', $e->getMessage(), 500);
        }
    }
}
