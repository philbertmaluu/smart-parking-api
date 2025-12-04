<?php

namespace App\Repositories;

use App\Models\Receipt;
use App\Models\VehiclePassage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ReceiptRepository
{
    protected $model;

    public function __construct(Receipt $model)
    {
        $this->model = $model;
    }

    /**
     * Create a receipt for vehicle passage
     *
     * @param VehiclePassage $vehiclePassage
     * @param array $data
     * @return Receipt
     */
    public function createReceiptForPassage(VehiclePassage $vehiclePassage, array $data): Receipt
    {
        $receiptData = [
            'receipt_number' => $this->generateReceiptNumber(),
            'vehicle_passage_id' => $vehiclePassage->id,
            'invoice_id' => $data['invoice_id'] ?? null,
            'amount' => $vehiclePassage->total_amount,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'issued_by' => $data['issued_by'],
            'issued_at' => $data['issued_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ];

        return $this->model->create($receiptData);
    }

    /**
     * Get receipt by ID with relationships
     *
     * @param int $id
     * @return Receipt|null
     */
    public function getReceiptByIdWithRelations(int $id): ?Receipt
    {
        return $this->model->with([
            'vehiclePassage.vehicle.bodyType',
            'vehiclePassage.entryStation',
            'vehiclePassage.entryGate',
            'vehiclePassage.paymentType',
            'invoice',
            'issuedBy'
        ])->find($id);
    }

    /**
     * Get receipt by receipt number
     *
     * @param string $receiptNumber
     * @return Receipt|null
     */
    public function getReceiptByNumber(string $receiptNumber): ?Receipt
    {
        return $this->model->with([
            'vehiclePassage.vehicle.bodyType',
            'vehiclePassage.entryStation',
            'vehiclePassage.entryGate',
            'vehiclePassage.paymentType',
            'invoice',
            'issuedBy'
        ])->where('receipt_number', $receiptNumber)->first();
    }

    /**
     * Get all receipts with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllReceiptsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehiclePassage.vehicle.bodyType',
            'vehiclePassage.entryStation',
            'vehiclePassage.paymentType',
            'issuedBy'
        ])->orderBy('issued_at', 'desc')->paginate($perPage);
    }

    /**
     * Get receipts by vehicle passage
     *
     * @param int $vehiclePassageId
     * @return Collection
     */
    public function getReceiptsByVehiclePassage(int $vehiclePassageId): Collection
    {
        return $this->model->with([
            'vehiclePassage.vehicle.bodyType',
            'vehiclePassage.entryStation',
            'vehiclePassage.paymentType',
            'issuedBy'
        ])->where('vehicle_passage_id', $vehiclePassageId)->get();
    }

    /**
     * Get receipts by date range
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getReceiptsByDateRange(string $startDate, string $endDate, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehiclePassage.vehicle.bodyType',
            'vehiclePassage.entryStation',
            'vehiclePassage.paymentType',
            'issuedBy'
        ])->whereBetween('issued_at', [$startDate, $endDate])->orderBy('issued_at', 'desc')->paginate($perPage);
    }

    /**
     * Get receipts by payment method
     *
     * @param string $paymentMethod
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getReceiptsByPaymentMethod(string $paymentMethod, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehiclePassage.vehicle.bodyType',
            'vehiclePassage.entryStation',
            'vehiclePassage.paymentType',
            'issuedBy'
        ])->where('payment_method', $paymentMethod)->orderBy('issued_at', 'desc')->paginate($perPage);
    }

    /**
     * Search receipts
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchReceipts(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehiclePassage.vehicle.bodyType',
            'vehiclePassage.entryStation',
            'vehiclePassage.paymentType',
            'issuedBy'
        ])->where(function ($query) use ($search) {
            $query->where('receipt_number', 'like', "%{$search}%")
                ->orWhereHas('vehiclePassage.vehicle', function ($q) use ($search) {
                    $q->where('plate_number', 'like', "%{$search}%");
                });
        })->orderBy('issued_at', 'desc')->paginate($perPage);
    }

    /**
     * Get receipt statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getReceiptStatistics(string $startDate, string $endDate): array
    {
        $receipts = $this->model->whereBetween('issued_at', [$startDate, $endDate]);

        return [
            'total_receipts' => $receipts->count(),
            'total_amount' => $receipts->sum('amount'),
            'payment_methods' => $receipts->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')
                ->get(),
            'daily_totals' => $receipts->selectRaw('DATE(issued_at) as date, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];
    }

    /**
     * Update receipt
     *
     * @param int $id
     * @param array $data
     * @return Receipt|null
     */
    public function updateReceipt(int $id, array $data): ?Receipt
    {
        $receipt = $this->model->find($id);
        if ($receipt) {
            $receipt->update($data);
            return $receipt->fresh();
        }
        return null;
    }

    /**
     * Delete receipt
     *
     * @param int $id
     * @return bool
     */
    public function deleteReceipt(int $id): bool
    {
        $receipt = $this->model->find($id);
        if ($receipt) {
            return $receipt->delete();
        }
        return false;
    }

    /**
     * Generate unique receipt number
     *
     * @return string
     */
    private function generateReceiptNumber(): string
    {
        do {
            $receiptNumber = 'RCPT' . date('Ymd') . strtoupper(Str::random(6));
        } while ($this->model->where('receipt_number', $receiptNumber)->exists());

        return $receiptNumber;
    }

    /**
     * Get receipts for dashboard
     *
     * @param int $limit
     * @return Collection
     */
    public function getRecentReceipts(int $limit = 10): Collection
    {
        return $this->model->with([
            'vehiclePassage.vehicle',
            'vehiclePassage.entryStation',
            'issuedBy'
        ])->orderBy('issued_at', 'desc')->limit($limit)->get();
    }

    /**
     * Get total revenue for a specific period
     *
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getTotalRevenue(string $startDate, string $endDate): float
    {
        return $this->model->whereBetween('issued_at', [$startDate, $endDate])->sum('amount');
    }
}
