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

        // Calculate total amount based on duration (days based on rolling 24-hour periods)
        $entryTime = $passage->entry_time;
        $exitTime = $data['exit_time'];
        
        // Calculate days to charge based on rolling 24-hour periods from entry time
        // - Minimum charge: 1 day (if parked < 24 hours, still charge 1 day)
        // - Multiple days: if parked > 24 hours, charge number of full 24-hour periods
        // - Round up: if partial day exceeds, round up to next full day
        $daysToCharge = $this->calculateDaysToCharge($entryTime, $exitTime);
        
        // Calculate total amount: base_amount * days_to_charge
        // base_amount now represents daily rate
        $data['total_amount'] = $passage->base_amount * $daysToCharge;

        $passage->update($data);
        return $passage->fresh();
    }

    /**
     * Calculate total billable days based on parking time using rolling 24-hour periods.
     *
     * Charging Rules (Daily - Rolling 24-hour periods):
     * - Minimum charge — always 1 day
     *   Even if someone parks for 5 minutes or 23 hours, they must pay for 1 day.
     * - Rolling 24-hour periods — each day starts from entry_time, not calendar day
     *   Example: Entry at 10 AM Jan 1, exit at 2 PM Jan 1 = 1 day (within 24 hours)
     *   Example: Entry at 10 AM Jan 1, exit at 11 AM Jan 2 = 2 days (exceeded 24 hours)
     * - Round up — if partial day exceeds 24 hours, round up to next full day
     *
     * @param \Carbon\Carbon $entryTime  Entry timestamp
     * @param \Carbon\Carbon $exitTime  Exit timestamp
     * @return int  Billable days to charge
     */
    private function calculateDaysToCharge(\Carbon\Carbon $entryTime, \Carbon\Carbon $exitTime): int
    {
        // Calculate total hours spent
        $hoursSpent = $entryTime->diffInHours($exitTime, true);
        
        // Minimum charge — always 1 day
        if ($hoursSpent <= 0) {
            return 1;
        }

        // If parked less than 24 hours, charge 1 day
        if ($hoursSpent < 24) {
            return 1;
        }

        // If parked 24 hours or more, calculate number of full 24-hour periods
        // Round up to next full day if there's any partial day
        $daysSpent = $hoursSpent / 24;
        return (int) ceil($daysSpent);
    }

    /**
     * Get active passages (not completed)
     *
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getActivePassages(?int $perPage = null)
    {
        $query = $this->model->with([
            'vehicle.bodyType',
            'entryGate',
            'entryStation',
            'entryOperator'
        ])->whereNull('exit_time')->orderBy('entry_time', 'desc');
        
        if ($perPage) {
            return $query->paginate($perPage);
        }
        
        return $query->get();
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
     * Get dashboard summary statistics
     * Provides comprehensive stats for dashboard cards
     *
     * @return array
     */
    public function getDashboardSummary(): array
    {
        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        // Total passages (all time)
        $totalPassages = $this->model->count();

        // Active passages (currently parked)
        $activePassages = $this->model->whereNull('exit_time')->count();

        // Completed today (exited today)
        $completedToday = $this->model->whereNotNull('exit_time')
            ->whereDate('exit_time', $today->toDateString())
            ->count();

        // Total revenue (all time from completed passages)
        $totalRevenue = $this->model->whereNotNull('exit_time')
            ->sum('total_amount');

        // Revenue today
        $revenueToday = $this->model->whereNotNull('exit_time')
            ->whereDate('exit_time', $today->toDateString())
            ->sum('total_amount');

        // Active passages count (for display)
        $activeNow = $this->model->whereNull('exit_time')->count();

        return [
            'total_passages' => $totalPassages,
            'active_passages' => $activeNow,
            'completed_today' => $completedToday,
            'total_revenue' => (float) $totalRevenue,
            'revenue_today' => (float) $revenueToday,
        ];
    }

    /**
     * Get recent vehicles (parked and exited) for a specific operator
     *
     * @param int $operatorId
     * @param int $limit
     * @return array
     */
    public function getRecentVehiclesForOperator(int $operatorId, int $limit = 20): array
    {
        // Get parked vehicles (active passages) where operator is entry operator
        $parkedVehicles = $this->model->with([
            'vehicle.bodyType',
            'entryGate',
            'entryStation',
            'paymentType'
        ])
        ->where('entry_operator_id', $operatorId)
        ->whereNull('exit_time')
        ->orderBy('entry_time', 'desc')
        ->limit($limit)
        ->get();

        // Get recently exited vehicles where operator is entry or exit operator
        $exitedVehicles = $this->model->with([
            'vehicle.bodyType',
            'entryGate',
            'exitGate',
            'entryStation',
            'exitStation',
            'paymentType'
        ])
        ->where(function ($query) use ($operatorId) {
            $query->where('entry_operator_id', $operatorId)
                  ->orWhere('exit_operator_id', $operatorId);
        })
        ->whereNotNull('exit_time')
        ->orderBy('exit_time', 'desc')
        ->limit($limit)
        ->get();

        return [
            'parked' => $parkedVehicles,
            'exited' => $exitedVehicles,
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
