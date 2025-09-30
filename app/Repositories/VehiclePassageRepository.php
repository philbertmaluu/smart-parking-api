<?php

namespace App\Repositories;

use App\Models\VehiclePassage;
use App\Models\Vehicle;
use App\Models\Gate;
use App\Models\Station;
use App\Models\Account;
use App\Models\BundleSubscription;
use App\Models\PaymentType;
use App\Models\VehicleBodyTypePrice;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VehiclePassageRepository
{
    protected $model;
    protected $pricingService;

    public function __construct(VehiclePassage $model, PricingService $pricingService)
    {
        $this->model = $model;
        $this->pricingService = $pricingService;
    }

    /**
     * Get all vehicle passages with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPassagesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'bundleSubscription',
            'paymentType',
            'entryOperator',
            'exitOperator',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation'
        ])->orderBy('entry_time', 'desc')->paginate($perPage);
    }

    /**
     * Get vehicle passage by ID with relationships
     *
     * @param int $id
     * @return VehiclePassage|null
     */
    public function getPassageByIdWithRelations(int $id): ?VehiclePassage
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'bundleSubscription',
            'paymentType',
            'entryOperator',
            'exitOperator',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation',
            'transactions',
            'receipts'
        ])->find($id);
    }

    /**
     * Get passage by passage number
     *
     * @param string $passageNumber
     * @return VehiclePassage|null
     */
    public function getPassageByNumber(string $passageNumber): ?VehiclePassage
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'bundleSubscription',
            'paymentType',
            'entryOperator',
            'exitOperator',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation'
        ])->where('passage_number', $passageNumber)->first();
    }

    /**
     * Create a new vehicle passage entry
     *
     * @param array $data
     * @return VehiclePassage
     */
    public function createPassageEntry(array $data): VehiclePassage
    {
        // Generate unique passage number
        $data['passage_number'] = $this->generatePassageNumber();

        // Set entry time if not provided
        if (!isset($data['entry_time'])) {
            $data['entry_time'] = now();
        }

        // Pricing is now calculated in VehiclePassageService and passed in $data
        // No need to recalculate here

        return $this->model->create($data);
    }

    /**
     * Complete a vehicle passage exit
     *
     * @param int $passageId
     * @param array $data
     * @return VehiclePassage|null
     */
    public function completePassageExit(int $passageId, array $data): ?VehiclePassage
    {
        $passage = $this->model->find($passageId);

        if (!$passage || $passage->isCompleted()) {
            return null;
        }

        // Set exit time if not provided
        if (!isset($data['exit_time'])) {
            $data['exit_time'] = now();
        }

        // Calculate duration
        $data['duration_minutes'] = $passage->entry_time->diffInMinutes($data['exit_time']);

        // For now, keep the same pricing as entry
        // Future enhancement: implement time-based pricing using PricingService
        // $pricing = $this->pricingService->calculatePricingForExit($passage, $data);
        // $data['base_amount'] = $pricing['base_amount'];
        // $data['discount_amount'] = $pricing['discount_amount'];
        // $data['total_amount'] = $pricing['total_amount'];

        $passage->update($data);
        return $passage->fresh();
    }

    /**
     * Get active passages (not completed)
     *
     * @return Collection
     */
    public function getActivePassages(): Collection
    {
        return $this->model->with([
            'vehicle.bodyType',
            'entryGate',
            'entryStation',
            'entryOperator'
        ])->whereNull('exit_time')->orderBy('entry_time', 'desc')->get();
    }

    /**
     * Get completed passages
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCompletedPassages(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'paymentType',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation'
        ])->whereNotNull('exit_time')->orderBy('exit_time', 'desc')->paginate($perPage);
    }

    /**
     * Get passages by vehicle ID
     *
     * @param int $vehicleId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPassagesByVehicle(int $vehicleId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'paymentType',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation'
        ])->where('vehicle_id', $vehicleId)->orderBy('entry_time', 'desc')->paginate($perPage);
    }

    /**
     * Get passages by station
     *
     * @param int $stationId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPassagesByStation(int $stationId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'paymentType',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation'
        ])->where(function ($query) use ($stationId) {
            $query->where('entry_station_id', $stationId)
                ->orWhere('exit_station_id', $stationId);
        })->orderBy('entry_time', 'desc')->paginate($perPage);
    }

    /**
     * Get passages by date range
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPassagesByDateRange(string $startDate, string $endDate, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'paymentType',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation'
        ])->whereBetween('entry_time', [$startDate, $endDate])->orderBy('entry_time', 'desc')->paginate($perPage);
    }

    /**
     * Search passages
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchPassages(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with([
            'vehicle.bodyType',
            'account',
            'paymentType',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation'
        ])->where(function ($query) use ($search) {
            $query->where('passage_number', 'like', "%{$search}%")
                ->orWhereHas('vehicle', function ($q) use ($search) {
                    $q->where('plate_number', 'like', "%{$search}%");
                });
        })->orderBy('entry_time', 'desc')->paginate($perPage);
    }

    /**
     * Get passage statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getPassageStatistics(string $startDate, string $endDate): array
    {
        $passages = $this->model->whereBetween('entry_time', [$startDate, $endDate]);

        return [
            'total_passages' => $passages->count(),
            'completed_passages' => $passages->whereNotNull('exit_time')->count(),
            'active_passages' => $passages->whereNull('exit_time')->count(),
            'total_revenue' => $passages->whereNotNull('exit_time')->sum('total_amount'),
            'toll_passages' => $passages->where('passage_type', 'toll')->count(),
            'free_passages' => $passages->where('passage_type', 'free')->count(),
            'exempted_passages' => $passages->where('passage_type', 'exempted')->count(),
        ];
    }

    /**
     * Check if vehicle has active passage
     *
     * @param int $vehicleId
     * @return VehiclePassage|null
     */
    public function getActivePassageByVehicle(int $vehicleId): ?VehiclePassage
    {
        return $this->model->where('vehicle_id', $vehicleId)
            ->whereNull('exit_time')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Update passage status
     *
     * @param int $passageId
     * @param string $status
     * @return VehiclePassage|null
     */
    public function updatePassageStatus(int $passageId, string $status): ?VehiclePassage
    {
        $passage = $this->model->find($passageId);
        if ($passage) {
            $passage->update(['status' => $status]);
            return $passage->fresh();
        }
        return null;
    }

    /**
     * Delete passage
     *
     * @param int $passageId
     * @return bool
     */
    public function deletePassage(int $passageId): bool
    {
        $passage = $this->model->find($passageId);
        if ($passage) {
            return $passage->delete();
        }
        return false;
    }

    /**
     * Generate unique passage number
     *
     * @return string
     */
    private function generatePassageNumber(): string
    {
        do {
            $passageNumber = 'PASS' . date('Ymd') . strtoupper(Str::random(6));
        } while ($this->model->where('passage_number', $passageNumber)->exists());

        return $passageNumber;
    }
}
