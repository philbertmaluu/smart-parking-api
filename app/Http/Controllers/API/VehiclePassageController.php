<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\VehiclePassageRequest;
use App\Repositories\VehiclePassageRepository;
use App\Services\VehiclePassageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VehiclePassageController extends BaseController
{
    protected $passageRepository;
    protected $passageService;

    public function __construct(
        VehiclePassageRepository $passageRepository,
        VehiclePassageService $passageService
    ) {
        $this->passageRepository = $passageRepository;
        $this->passageService = $passageService;
    }

    /**
     * Display a listing of vehicle passages
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $status = $request->get('status', '');
            $startDate = $request->get('start_date', '');
            $endDate = $request->get('end_date', '');

            if ($search) {
                $passages = $this->passageRepository->searchPassages($search, $perPage);
            } elseif ($startDate && $endDate) {
                $passages = $this->passageRepository->getPassagesByDateRange($startDate, $endDate, $perPage);
            } elseif ($status === 'active') {
                $passages = $this->passageRepository->getActivePassages();
                return $this->sendResponse($passages, 'Active passages retrieved successfully');
            } elseif ($status === 'completed') {
                $passages = $this->passageRepository->getCompletedPassages($perPage);
            } else {
                $passages = $this->passageRepository->getAllPassagesPaginated($perPage);
            }

            return $this->sendResponse($passages, 'Vehicle passages retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle passages', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created vehicle passage entry
     */
    public function store(VehiclePassageRequest $request)
    {
        try {
            $data = $request->validated();
            $data['entry_operator_id'] = Auth::id();

            $passage = $this->passageRepository->createPassageEntry($data);

            return $this->sendResponse($passage, 'Vehicle passage entry created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating vehicle passage entry', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified vehicle passage
     */
    public function show($id)
    {
        try {
            $passage = $this->passageRepository->getPassageByIdWithRelations((int) $id);

            if (!$passage) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            return $this->sendResponse($passage, 'Vehicle passage retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle passage', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified vehicle passage
     */
    public function update(VehiclePassageRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $passage = $this->passageRepository->getPassageByIdWithRelations($id);

            if (!$passage) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            // If updating exit data, use the service method
            if (isset($data['exit_time']) || isset($data['exit_gate_id'])) {
                $data['exit_operator_id'] = Auth::id();
                $passage = $this->passageRepository->completePassageExit($id, $data);
            } else {
                // Update other fields
                $passage->update($data);
                $passage = $passage->fresh();
            }

            return $this->sendResponse($passage, 'Vehicle passage updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating vehicle passage', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified vehicle passage
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->passageRepository->deletePassage($id);

            if (!$deleted) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            return $this->sendResponse([], 'Vehicle passage deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting vehicle passage', $e->getMessage(), 500);
        }
    }

    /**
     * Process vehicle entry with plate number detection and payment
     */
    public function processEntry(Request $request)
    {
        try {
            $request->validate([
                'plate_number' => 'required|string|max:20',
                'gate_id' => 'required|exists:gates,id',
                'body_type_id' => 'nullable|exists:vehicle_body_types,id',
                'make' => 'nullable|string|max:50',
                'model' => 'nullable|string|max:50',
                'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
                'color' => 'nullable|string|max:30',
                'owner_name' => 'nullable|string|max:100',
                'account_id' => 'nullable|exists:accounts,id',
                'payment_type_id' => 'nullable|exists:payment_types,id',
                'passage_type' => 'nullable|in:toll,free,exempted',
                'is_exempted' => 'nullable|boolean',
                'exemption_reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:500',
                'payment_method' => 'nullable|string|max:50',
                'payment_amount' => 'nullable|numeric|min:0',
                'receipt_notes' => 'nullable|string|max:500',
            ]);

            $result = $this->passageService->processVehicleEntry(
                $request->plate_number,
                $request->gate_id,
                Auth::id(),
                $request->all()
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message'], 201);
            } else {
                return $this->sendError($result['message'], $result['data'], 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error processing vehicle entry', $e->getMessage(), 500);
        }
    }

    /**
     * Process vehicle exit with plate number detection
     */
    public function processExit(Request $request)
    {
        try {
            $request->validate([
                'plate_number' => 'required|string|max:20',
                'gate_id' => 'required|exists:gates,id',
                'payment_confirmed' => 'nullable|boolean',
                'notes' => 'nullable|string|max:500',
            ]);

            $result = $this->passageService->processVehicleExit(
                $request->plate_number,
                $request->gate_id,
                Auth::id(),
                $request->all()
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'], 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error processing vehicle exit', $e->getMessage(), 500);
        }
    }

    /**
     * Return an exit preview for a passage (calculation only, no persistence)
     */
    public function previewExit($id)
    {
        try {
            $preview = $this->passageRepository->calculateExitPreview((int) $id);

            if (is_null($preview)) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            return $this->sendResponse($preview, 'Exit preview calculated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error calculating exit preview', $e->getMessage(), 500);
        }
    }

    /**
     * Set or update vehicle body type for a passage's vehicle and return updated preview
     */
    public function setVehicleType(Request $request, $id)
    {
        try {
            $request->validate([
                'body_type_id' => 'required|exists:vehicle_body_types,id',
            ]);

            $passage = $this->passageRepository->getPassageByIdWithRelations((int) $id);
            if (!$passage) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            $vehicle = $passage->vehicle;
            if (!$vehicle) {
                return $this->sendError('Vehicle not found for passage', [], 404);
            }

            // Update vehicle body type if changed
            $vehicle->update(['body_type_id' => $request->body_type_id]);

            // Update passage base_amount immediately using effective price
            $bodyTypePrice = \App\Models\VehicleBodyTypePrice::where('body_type_id', $request->body_type_id)
                ->where('is_active', true)
                ->orderBy('effective_from', 'desc')
                ->first();

            if ($bodyTypePrice) {
                $passage->update(['base_amount' => $bodyTypePrice->base_price]);
            }

            $preview = $this->passageRepository->calculateExitPreview((int) $id);

            return $this->sendResponse($preview, 'Vehicle type updated and preview calculated');
        } catch (\Exception $e) {
            return $this->sendError('Error updating vehicle type', $e->getMessage(), 500);
        }
    }

    /**
     * Quick plate number lookup for gate control
     */
    public function quickLookup(Request $request)
    {
        try {
            $request->validate([
                'plate_number' => 'required|string|max:20',
            ]);

            $result = $this->passageService->quickPlateLookup($request->plate_number);

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'], 404);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error processing plate lookup', $e->getMessage(), 500);
        }
    }

    /**
     * Get passage by passage number
     */
    public function getByPassageNumber($passageNumber)
    {
        try {
            $passage = $this->passageRepository->getPassageByNumber($passageNumber);

            if (!$passage) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            return $this->sendResponse($passage, 'Vehicle passage retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle passage', $e->getMessage(), 500);
        }
    }

    /**
     * Get passages by vehicle ID
     */
    public function getByVehicle($vehicleId, Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $passages = $this->passageRepository->getPassagesByVehicle($vehicleId, $perPage);

            return $this->sendResponse($passages, 'Vehicle passages retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving vehicle passages', $e->getMessage(), 500);
        }
    }

    /**
     * Get passages by station
     */
    public function getByStation($stationId, Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $passages = $this->passageRepository->getPassagesByStation($stationId, $perPage);

            return $this->sendResponse($passages, 'Station passages retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving station passages', $e->getMessage(), 500);
        }
    }

    /**
     * Get active passages for monitoring
     */
    public function getActivePassages(Request $request)
    {
        try {
            $perPage = $request->get('per_page', null);
            
            if ($perPage) {
                // Return paginated results
                $passages = $this->passageRepository->getActivePassages((int)$perPage);
                return $this->sendResponse($passages, 'Active passages retrieved successfully');
            } else {
                // Return all results (backward compatibility)
                $result = $this->passageService->getActivePassagesForMonitoring();
                return $this->sendResponse($result['data'], 'Active passages retrieved successfully');
            }
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active passages', $e->getMessage(), 500);
        }
    }

    /**
     * Get completed passages
     */
    public function getCompletedPassages(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $passages = $this->passageRepository->getCompletedPassages($perPage);

            return $this->sendResponse($passages, 'Completed passages retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving completed passages', $e->getMessage(), 500);
        }
    }

    /**
     * Get passage statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $statistics = $this->passageService->getDashboardStatistics($startDate, $endDate);

            return $this->sendResponse($statistics, 'Passage statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving passage statistics', $e->getMessage(), 500);
        }
    }

    /**
     * Get dashboard summary statistics
     * Provides quick stats for dashboard cards
     */
    public function getDashboardSummary()
    {
        try {
            $summary = $this->passageRepository->getDashboardSummary();

            return $this->sendResponse($summary, 'Dashboard summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving dashboard summary', $e->getMessage(), 500);
        }
    }

    /**
     * Update passage status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,cancelled,refunded',
            ]);

            $passage = $this->passageRepository->updatePassageStatus($id, $request->status);

            if (!$passage) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            return $this->sendResponse($passage, 'Passage status updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating passage status', $e->getMessage(), 500);
        }
    }

    /**
     * Search passages
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'search' => 'required|string|min:1',
            ]);

            $perPage = $request->get('per_page', 15);
            $passages = $this->passageRepository->searchPassages($request->search, $perPage);

            return $this->sendResponse($passages, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error searching passages', $e->getMessage(), 500);
        }
    }
}
