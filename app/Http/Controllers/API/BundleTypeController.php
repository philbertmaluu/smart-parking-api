<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\BundleTypeRequest;
use App\Repositories\BundleTypeRepository;
use Illuminate\Http\Request;

class BundleTypeController extends BaseController
{
    protected $bundleTypeRepository;

    public function __construct(BundleTypeRepository $bundleTypeRepository)
    {
        $this->bundleTypeRepository = $bundleTypeRepository;
    }

    /**
     * Display a listing of bundle types
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $bundleTypes = $this->bundleTypeRepository->searchBundleTypes($search, $perPage);
            } else {
                $bundleTypes = $this->bundleTypeRepository->getAllBundleTypesPaginated($perPage);
            }

            return $this->sendResponse($bundleTypes, 'Bundle types retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle types', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created bundle type
     */
    public function store(BundleTypeRequest $request)
    {
        try {
            $bundleType = $this->bundleTypeRepository->createBundleType($request->validated());

            return $this->sendResponse($bundleType, 'Bundle type created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating bundle type', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified bundle type
     */
    public function show($id)
    {
        try {
            $bundleType = $this->bundleTypeRepository->getBundleTypeByIdWithBundles($id);

            if (!$bundleType) {
                return $this->sendError('Bundle type not found', [], 404);
            }

            return $this->sendResponse($bundleType, 'Bundle type retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle type', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified bundle type
     */
    public function update(BundleTypeRequest $request, $id)
    {
        try {
            $bundleType = $this->bundleTypeRepository->updateBundleType($id, $request->validated());

            if (!$bundleType) {
                return $this->sendError('Bundle type not found', [], 404);
            }

            return $this->sendResponse($bundleType, 'Bundle type updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating bundle type', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified bundle type
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->bundleTypeRepository->deleteBundleType($id);

            if (!$deleted) {
                return $this->sendError('Bundle type not found', [], 404);
            }

            return $this->sendResponse([], 'Bundle type deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting bundle type', $e->getMessage(), 500);
        }
    }

    /**
     * Toggle active status of a bundle type
     */
    public function toggleStatus($id)
    {
        try {
            $bundleType = $this->bundleTypeRepository->toggleBundleTypeStatus($id);

            if (!$bundleType) {
                return $this->sendError('Bundle type not found', [], 404);
            }

            return $this->sendResponse($bundleType, 'Bundle type status toggled successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error toggling bundle type status', $e->getMessage(), 500);
        }
    }

    /**
     * Get active bundle types for dropdown
     */
    public function getActiveBundleTypes()
    {
        try {
            $bundleTypes = $this->bundleTypeRepository->getBundleTypesForSelect();
            return $this->sendResponse($bundleTypes, 'Active bundle types retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active bundle types', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundle types by duration
     */
    public function getByDuration($durationDays)
    {
        try {
            $bundleTypes = $this->bundleTypeRepository->getBundleTypesByDuration($durationDays);
            return $this->sendResponse($bundleTypes, "Bundle types with duration '{$durationDays}' days retrieved successfully");
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle types by duration', $e->getMessage(), 500);
        }
    }

    /**
     * Get bundle types with bundle count
     */
    public function getWithBundleCount()
    {
        try {
            $bundleTypes = $this->bundleTypeRepository->getBundleTypesWithBundleCount();
            return $this->sendResponse($bundleTypes, 'Bundle types with bundle count retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving bundle types with bundle count', $e->getMessage(), 500);
        }
    }

    /**
     * Get popular bundle types
     */
    public function getPopular(Request $request)
    {
        try {
            $limit = (int) $request->get('limit', 10);
            $bundleTypes = $this->bundleTypeRepository->getPopularBundleTypes($limit);
            return $this->sendResponse($bundleTypes, 'Popular bundle types retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving popular bundle types', $e->getMessage(), 500);
        }
    }
}
