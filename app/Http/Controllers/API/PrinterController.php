<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Services\ReceiptPrinterService;
use App\Models\VehiclePassage;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PrinterController extends BaseController
{
    protected $printerService;

    public function __construct(ReceiptPrinterService $printerService)
    {
        $this->printerService = $printerService;
    }

    /**
     * Get printer status and configuration
     */
    public function status()
    {
        try {
            $status = $this->printerService->getStatus();
            return $this->sendResponse($status, 'Printer status retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving printer status', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Test printer connection and print test page
     */
    public function testConnection()
    {
        try {
            $result = $this->printerService->testConnection();
            
            if ($result['success']) {
                return $this->sendResponse($result, $result['message']);
            }
            
            return $this->sendError($result['message'], $result, 400);
        } catch (\Exception $e) {
            Log::error('Printer test connection error', ['error' => $e->getMessage()]);
            return $this->sendError('Printer test failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Print entry receipt for a vehicle passage
     */
    public function printEntryReceipt(Request $request, $passageId)
    {
        try {
            $passage = VehiclePassage::with([
                'vehicle.bodyType',
                'entryGate',
                'entryStation',
                'entryOperator',
                'receipts'
            ])->find($passageId);

            if (!$passage) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            // Get associated receipt if exists
            $receipt = $passage->receipts->first();

            $result = $this->printerService->printEntryReceipt($passage, $receipt);

            if ($result['success']) {
                return $this->sendResponse([
                    'passage_id' => $passage->id,
                    'passage_number' => $passage->passage_number,
                    'plate_number' => $passage->vehicle->plate_number ?? 'N/A',
                ], $result['message']);
            }

            return $this->sendError($result['message'], [], 400);

        } catch (\Exception $e) {
            Log::error('Print entry receipt error', [
                'passage_id' => $passageId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError('Failed to print entry receipt', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Print exit receipt for a vehicle passage
     */
    public function printExitReceipt(Request $request, $passageId)
    {
        try {
            $passage = VehiclePassage::with([
                'vehicle.bodyType',
                'entryGate',
                'exitGate',
                'entryStation',
                'exitStation',
                'receipts'
            ])->find($passageId);

            if (!$passage) {
                return $this->sendError('Vehicle passage not found', [], 404);
            }

            // Get associated receipt if exists
            $receipt = $passage->receipts->last();

            $result = $this->printerService->printExitReceipt($passage, $receipt);

            if ($result['success']) {
                return $this->sendResponse([
                    'passage_id' => $passage->id,
                    'passage_number' => $passage->passage_number,
                    'plate_number' => $passage->vehicle->plate_number ?? 'N/A',
                    'total_amount' => $passage->total_amount,
                ], $result['message']);
            }

            return $this->sendError($result['message'], [], 400);

        } catch (\Exception $e) {
            Log::error('Print exit receipt error', [
                'passage_id' => $passageId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError('Failed to print exit receipt', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Print receipt by receipt ID
     */
    public function printReceipt(Request $request, $receiptId)
    {
        try {
            $receipt = Receipt::with([
                'vehiclePassage.vehicle.bodyType',
                'vehiclePassage.entryGate',
                'vehiclePassage.exitGate',
                'vehiclePassage.entryStation',
                'vehiclePassage.exitStation',
            ])->find($receiptId);

            if (!$receipt) {
                return $this->sendError('Receipt not found', [], 404);
            }

            $passage = $receipt->vehiclePassage;

            // Determine if this is entry or exit receipt based on passage status
            if ($passage->status === 'completed' && $passage->exit_time) {
                $result = $this->printerService->printExitReceipt($passage, $receipt);
            } else {
                $result = $this->printerService->printEntryReceipt($passage, $receipt);
            }

            if ($result['success']) {
                return $this->sendResponse([
                    'receipt_id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'passage_number' => $passage->passage_number,
                ], $result['message']);
            }

            return $this->sendError($result['message'], [], 400);

        } catch (\Exception $e) {
            Log::error('Print receipt error', [
                'receipt_id' => $receiptId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError('Failed to print receipt', ['error' => $e->getMessage()], 500);
        }
    }
}

