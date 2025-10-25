<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Services\TollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TollController extends BaseController
{
    protected $tollService;

    public function __construct(TollService $tollService)
    {
        $this->tollService = $tollService;
    }

    /**
     * Process vehicle entry
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plate_number' => 'required|string|max:20',
            'gate_id' => 'required|integer|exists:gates,id',
            'operator_id' => 'required|integer|exists:users,id',
            'body_type_id' => 'sometimes|integer|exists:vehicle_body_types,id',
            'make' => 'sometimes|string|max:50',
            'model' => 'sometimes|string|max:50',
            'year' => 'sometimes|integer|min:1900|max:' . date('Y'),
            'color' => 'sometimes|string|max:30',
            'owner_name' => 'sometimes|string|max:100',
            'notes' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        try {
            $result = $this->tollService->processVehicleEntry(
                $request->plate_number,
                $request->gate_id,
                $request->operator_id,
                $request->only([
                    'body_type_id', 'make', 'model', 'year', 'color', 
                    'owner_name', 'notes'
                ])
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? [], 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error processing vehicle entry', $e->getMessage(), 500);
        }
    }

    /**
     * Process vehicle exit
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processExit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plate_number' => 'required|string|max:20',
            'gate_id' => 'required|integer|exists:gates,id',
            'operator_id' => 'required|integer|exists:users,id',
            'payment_confirmed' => 'sometimes|boolean',
            'payment_method' => 'sometimes|string|in:cash,card,mobile',
            'payment_amount' => 'sometimes|numeric|min:0',
            'receipt_notes' => 'sometimes|string|max:500',
            'notes' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        try {
            $result = $this->tollService->processVehicleExit(
                $request->plate_number,
                $request->gate_id,
                $request->operator_id,
                $request->only([
                    'payment_confirmed', 'payment_method', 'payment_amount',
                    'receipt_notes', 'notes'
                ])
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? [], 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error processing vehicle exit', $e->getMessage(), 500);
        }
    }

    /**
     * Confirm payment for exit
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'passage_id' => 'required|integer|exists:vehicle_passages,id',
            'operator_id' => 'required|integer|exists:users,id',
            'payment_method' => 'required|string|in:cash,card,mobile',
            'payment_amount' => 'required|numeric|min:0',
            'receipt_notes' => 'sometimes|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        try {
            $result = $this->tollService->confirmPayment(
                $request->passage_id,
                $request->operator_id,
                $request->only([
                    'payment_method', 'payment_amount', 'receipt_notes'
                ])
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], $result['data'] ?? [], 400);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error confirming payment', $e->getMessage(), 500);
        }
    }

    /**
     * Get active passages
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivePassages()
    {
        try {
            $result = $this->tollService->getActivePassages();
            
            if ($result['success']) {
                return $this->sendResponse($result['data'], 'Active passages retrieved successfully');
            } else {
                return $this->sendError('Error retrieving active passages', [], 500);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active passages', $e->getMessage(), 500);
        }
    }

    /**
     * Get passage details
     *
     * @param int $passageId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPassageDetails(int $passageId)
    {
        try {
            $result = $this->tollService->getPassageDetails($passageId);
            
            if ($result['success']) {
                return $this->sendResponse($result['data'], 'Passage details retrieved successfully');
            } else {
                return $this->sendError($result['message'], [], 404);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving passage details', $e->getMessage(), 500);
        }
    }

    /**
     * Calculate toll amount for active passage
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateTollAmount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'passage_id' => 'required|integer|exists:vehicle_passages,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        try {
            $passage = \App\Models\VehiclePassage::find($request->passage_id);

            if (!$passage || $passage->exit_time) {
                return $this->sendError('Passage not found or already completed', [], 404);
            }

            $entryTime = $passage->entry_time;
            $currentTime = now();
            $hoursSpent = $entryTime->diffInHours($currentTime, true);
            $hoursToCharge = max(1, ceil($hoursSpent));
            $totalAmount = $passage->base_amount * $hoursToCharge;

            $data = [
                'passage_id' => $passage->id,
                'entry_time' => $entryTime,
                'current_time' => $currentTime,
                'hours_spent' => $hoursSpent,
                'hours_to_charge' => $hoursToCharge,
                'price_per_hour' => $passage->base_amount,
                'total_amount' => $totalAmount
            ];

            return $this->sendResponse($data, 'Toll amount calculated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error calculating toll amount', $e->getMessage(), 500);
        }
    }

    /**
     * Get toll statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->startOfDay());
            $endDate = $request->get('end_date', now()->endOfDay());

            $statistics = [
                'total_passages' => \App\Models\VehiclePassage::whereBetween('entry_time', [$startDate, $endDate])->count(),
                'active_passages' => \App\Models\VehiclePassage::whereNull('exit_time')->count(),
                'total_revenue' => \App\Models\VehiclePassage::whereBetween('entry_time', [$startDate, $endDate])
                    ->whereNotNull('exit_time')
                    ->sum('total_amount'),
                'average_stay_time' => \App\Models\VehiclePassage::whereBetween('entry_time', [$startDate, $endDate])
                    ->whereNotNull('exit_time')
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, entry_time, exit_time)) as avg_hours')
                    ->value('avg_hours')
            ];

            return $this->sendResponse($statistics, 'Toll statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving statistics', $e->getMessage(), 500);
        }
    }
}
