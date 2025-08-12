<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Repositories\ReceiptRepository;
use Illuminate\Http\Request;

class ReceiptController extends BaseController
{
    protected $receiptRepository;

    public function __construct(ReceiptRepository $receiptRepository)
    {
        $this->receiptRepository = $receiptRepository;
    }

    /**
     * Display a listing of receipts
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $paymentMethod = $request->get('payment_method', '');
            $startDate = $request->get('start_date', '');
            $endDate = $request->get('end_date', '');

            if ($search) {
                $receipts = $this->receiptRepository->searchReceipts($search, $perPage);
            } elseif ($startDate && $endDate) {
                $receipts = $this->receiptRepository->getReceiptsByDateRange($startDate, $endDate, $perPage);
            } elseif ($paymentMethod) {
                $receipts = $this->receiptRepository->getReceiptsByPaymentMethod($paymentMethod, $perPage);
            } else {
                $receipts = $this->receiptRepository->getAllReceiptsPaginated($perPage);
            }

            return $this->sendResponse($receipts, 'Receipts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipts', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified receipt
     */
    public function show($id)
    {
        try {
            $receipt = $this->receiptRepository->getReceiptByIdWithRelations($id);

            if (!$receipt) {
                return $this->sendError('Receipt not found', [], 404);
            }

            return $this->sendResponse($receipt, 'Receipt retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipt', $e->getMessage(), 500);
        }
    }

    /**
     * Get receipt by receipt number
     */
    public function getByReceiptNumber($receiptNumber)
    {
        try {
            $receipt = $this->receiptRepository->getReceiptByNumber($receiptNumber);

            if (!$receipt) {
                return $this->sendError('Receipt not found', [], 404);
            }

            return $this->sendResponse($receipt, 'Receipt retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipt', $e->getMessage(), 500);
        }
    }

    /**
     * Get receipts by vehicle passage
     */
    public function getByVehiclePassage($vehiclePassageId)
    {
        try {
            $receipts = $this->receiptRepository->getReceiptsByVehiclePassage($vehiclePassageId);

            return $this->sendResponse($receipts, 'Receipts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipts', $e->getMessage(), 500);
        }
    }

    /**
     * Get receipt statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $statistics = $this->receiptRepository->getReceiptStatistics($startDate, $endDate);

            return $this->sendResponse($statistics, 'Receipt statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipt statistics', $e->getMessage(), 500);
        }
    }

    /**
     * Get recent receipts for dashboard
     */
    public function getRecentReceipts(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $receipts = $this->receiptRepository->getRecentReceipts($limit);

            return $this->sendResponse($receipts, 'Recent receipts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving recent receipts', $e->getMessage(), 500);
        }
    }

    /**
     * Get total revenue for a period
     */
    public function getTotalRevenue(Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $totalRevenue = $this->receiptRepository->getTotalRevenue($startDate, $endDate);

            return $this->sendResponse(['total_revenue' => $totalRevenue], 'Total revenue retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving total revenue', $e->getMessage(), 500);
        }
    }

    /**
     * Search receipts
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'search' => 'required|string|min:1',
            ]);

            $perPage = $request->get('per_page', 15);
            $receipts = $this->receiptRepository->searchReceipts($request->search, $perPage);

            return $this->sendResponse($receipts, 'Search results retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error searching receipts', $e->getMessage(), 500);
        }
    }

    /**
     * Update receipt
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'notes' => 'nullable|string|max:500',
            ]);

            $receipt = $this->receiptRepository->updateReceipt($id, $request->all());

            if (!$receipt) {
                return $this->sendError('Receipt not found', [], 404);
            }

            return $this->sendResponse($receipt, 'Receipt updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating receipt', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified receipt
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->receiptRepository->deleteReceipt($id);

            if (!$deleted) {
                return $this->sendError('Receipt not found', [], 404);
            }

            return $this->sendResponse([], 'Receipt deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting receipt', $e->getMessage(), 500);
        }
    }

    /**
     * Print receipt (generate printable format)
     */
    public function printReceipt($id)
    {
        try {
            $receipt = $this->receiptRepository->getReceiptByIdWithRelations($id);

            if (!$receipt) {
                return $this->sendError('Receipt not found', [], 404);
            }

            // Generate printable receipt data
            $printableReceipt = [
                'receipt_number' => $receipt->receipt_number,
                'issued_at' => $receipt->issued_at->format('Y-m-d H:i:s'),
                'amount' => $receipt->amount,
                'payment_method' => $receipt->payment_method,
                'vehicle' => [
                    'plate_number' => $receipt->vehiclePassage->vehicle->plate_number,
                    'make' => $receipt->vehiclePassage->vehicle->make,
                    'model' => $receipt->vehiclePassage->vehicle->model,
                    'body_type' => $receipt->vehiclePassage->vehicle->bodyType->name ?? 'N/A',
                ],
                'station' => [
                    'name' => $receipt->vehiclePassage->entryStation->name ?? 'N/A',
                    'gate' => $receipt->vehiclePassage->entryGate->name ?? 'N/A',
                ],
                'operator' => $receipt->issuedBy->name ?? 'N/A',
                'notes' => $receipt->notes,
                'passage_number' => $receipt->vehiclePassage->passage_number,
            ];

            return $this->sendResponse($printableReceipt, 'Printable receipt data generated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error generating printable receipt', $e->getMessage(), 500);
        }
    }

    /**
     * Get receipts by date range
     */
    public function getByDateRange(Request $request)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $perPage = $request->get('per_page', 15);
            $receipts = $this->receiptRepository->getReceiptsByDateRange(
                $request->start_date,
                $request->end_date,
                $perPage
            );

            return $this->sendResponse($receipts, 'Receipts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipts', $e->getMessage(), 500);
        }
    }

    /**
     * Get receipts by payment method
     */
    public function getByPaymentMethod(Request $request)
    {
        try {
            $request->validate([
                'payment_method' => 'required|string',
            ]);

            $perPage = $request->get('per_page', 15);
            $receipts = $this->receiptRepository->getReceiptsByPaymentMethod(
                $request->payment_method,
                $perPage
            );

            return $this->sendResponse($receipts, 'Receipts retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipts', $e->getMessage(), 500);
        }
    }
}
