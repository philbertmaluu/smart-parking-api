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
use Illuminate\Support\Facades\Log;
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

        $vehicle = $passage->vehicle;
        
        // Before calculating, ensure base_amount is up to date with vehicle's current body type
        // This handles cases where vehicle type was added after entry
        if ($vehicle && $vehicle->body_type_id && $passage->base_amount == 0) {
            // Vehicle type was added after entry, update base_amount
            // Use current effective price for that body type (station-agnostic fallback)
            $bodyTypePrice = \App\Models\VehicleBodyTypePrice::where('body_type_id', $vehicle->body_type_id)
                ->where('is_active', true)
                ->orderBy('effective_from', 'desc')
                ->first();

            if ($bodyTypePrice) {
                $passage->base_amount = $bodyTypePrice->base_price;
                Log::info('Updated passage base_amount from vehicle body type', [
                    'passage_id' => $passage->id,
                    'vehicle_id' => $vehicle->id,
                    'body_type_id' => $vehicle->body_type_id,
                    'base_amount' => $passage->base_amount
                ]);
            }
        }

        // Use centralized calculation to determine billing
        $calculation = $this->calculateExitAmount($passageId, $data['exit_time']);

        if ($calculation['is_free_reentry']) {
            $data['total_amount'] = 0;
            $data['passage_type'] = 'free';
            $data['notes'] = ($passage->notes ?? '') . " [Free re-entry within paid window or 24h cycle]";
            Log::info('Free re-entry (centralized)', [
                'passage_id' => $passage->id,
                'vehicle_id' => $vehicle?->id,
                'first_entry_time' => $calculation['first_entry_time'] ?? null,
                'exit_time' => $data['exit_time']
            ]);
        } else {
            $data['total_amount'] = $calculation['amount'];
            Log::info('Charging exit (centralized)', [
                'passage_id' => $passage->id,
                'vehicle_id' => $vehicle?->id,
                'first_entry_time' => $calculation['first_entry_time'] ?? null,
                'exit_time' => $data['exit_time'],
                'days_to_charge' => $calculation['days'],
                'amount' => $calculation['amount']
            ]);
        }

        $passage->update($data);
        return $passage->fresh();
    }

    /**
     * Calculate an exit preview for a given passage (without persisting changes).
     *
     * @param int $passageId
     * @param \Carbon\Carbon|null $referenceTime
     * @return array|null
     */
    public function calculateExitPreview(int $passageId, $referenceTime = null): ?array
    {
        $calculation = $this->calculateExitAmount($passageId, $referenceTime);
        if (is_null($calculation)) {
            return null;
        }

        return [
            'passage_id' => $calculation['passage_id'],
            'vehicle_id' => $calculation['vehicle_id'],
            'base_amount' => (float) $calculation['base_amount'],
            'days' => $calculation['days'],
            'amount' => (float) $calculation['amount'],
            'first_entry_time' => $calculation['first_entry_time'] ?? null,
            'is_free_reentry' => $calculation['is_free_reentry'],
        ];
    }

    /**
     * Centralized calculation for exit amount. Uses vehicle.paid_until when present.
     * Returns array with keys: passage_id, vehicle_id, base_amount, days, amount, first_entry_time, is_free_reentry
     */
    public function calculateExitAmount(int $passageId, $referenceTime = null): ?array
    {
        $passage = $this->model->with('vehicle')->find($passageId);
        if (!$passage) {
            return null;
        }

        $now = $referenceTime ? (\Carbon\Carbon::parse($referenceTime)) : now();
        $vehicle = $passage->vehicle;

        // Ensure base_amount
        $baseAmount = $passage->base_amount;
        if ($vehicle && $vehicle->body_type_id && $baseAmount == 0) {
            $bodyTypePrice = VehicleBodyTypePrice::where('body_type_id', $vehicle->body_type_id)
                ->where('is_active', true)
                ->orderBy('effective_from', 'desc')
                ->first();

            if ($bodyTypePrice) {
                $baseAmount = $bodyTypePrice->base_price;
            }
        }

        // If vehicle has an active paid window that covers now, it's a free re-entry
        if ($vehicle && $vehicle->paid_until && $vehicle->paid_until->greaterThanOrEqualTo($now)) {
            return [
                'passage_id' => $passage->id,
                'vehicle_id' => $vehicle->id,
                'base_amount' => $baseAmount,
                'days' => 0,
                'amount' => 0,
                'first_entry_time' => $vehicle->paid_until,
                'is_free_reentry' => true,
            ];
        }

        // Find the FIRST ENTRY within the last 24 hours for this vehicle
        $firstEntryIn24h = null;
        if ($vehicle) {
            $firstEntryIn24h = VehiclePassage::where('vehicle_id', $vehicle->id)
                ->where('entry_time', '>=', now()->subHours(24))
                ->orderBy('entry_time', 'asc')
                ->first();
        }

        if (!$firstEntryIn24h) {
            $firstEntryIn24h = $passage;
        }

        // Check if there has been an exit since the first entry (within this 24h window)
        $hasExitedSinceFirstEntry = false;
        if ($vehicle) {
            $hasExitedSinceFirstEntry = VehiclePassage::where('vehicle_id', $vehicle->id)
                ->where('id', '!=', $passage->id)
                ->whereNotNull('exit_time')
                ->where('entry_time', '>=', $firstEntryIn24h->entry_time)
                ->exists();
        }

        if ($hasExitedSinceFirstEntry) {
            return [
                'passage_id' => $passage->id,
                'vehicle_id' => $vehicle?->id,
                'base_amount' => $baseAmount,
                'days' => 0,
                'amount' => 0,
                'first_entry_time' => $firstEntryIn24h->entry_time,
                'is_free_reentry' => true,
            ];
        }

        // Determine charge start time (if paid_until exists and is after first entry, start counting from paid_until)
        $chargeStart = $firstEntryIn24h->entry_time;
        if ($vehicle && $vehicle->paid_until && $vehicle->paid_until->greaterThan($chargeStart)) {
            $chargeStart = $vehicle->paid_until;
        }

        $hoursSpent = $chargeStart->diffInHours($now, true);
        $daysToCharge = 1;
        if ($hoursSpent >= 24) {
            $daysToCharge = (int) ceil($hoursSpent / 24);
        }

        $calculatedAmount = $baseAmount * $daysToCharge;

        return [
            'passage_id' => $passage->id,
            'vehicle_id' => $vehicle?->id,
            'base_amount' => $baseAmount,
            'days' => $daysToCharge,
            'amount' => $calculatedAmount,
            'first_entry_time' => $firstEntryIn24h->entry_time,
            'is_free_reentry' => false,
        ];
    }

    /**
     * Calculate exit amount based on provided data (useful for tests or preview without a passage record).
     * Parameters: vehicleId, baseAmount, entryTime, referenceTime
     */
    public function calculateExitAmountForData(int $vehicleId, float $baseAmount, $entryTime, $referenceTime = null): array
    {
        $vehicle = \App\Models\Vehicle::find($vehicleId);
        $now = $referenceTime ? (\Carbon\Carbon::parse($referenceTime)) : now();

        if ($vehicle && $vehicle->paid_until && $vehicle->paid_until->greaterThanOrEqualTo($now)) {
            return [
                'vehicle_id' => $vehicle->id,
                'base_amount' => $baseAmount,
                'days' => 0,
                'amount' => 0,
                'first_entry_time' => $entryTime,
                'is_free_reentry' => true,
            ];
        }

        $entry = \Carbon\Carbon::parse($entryTime);
        $hoursSpent = $entry->diffInHours($now, true);
        $daysToCharge = 1;
        if ($hoursSpent >= 24) {
            $daysToCharge = (int) ceil($hoursSpent / 24);
        }

        $calculatedAmount = $baseAmount * $daysToCharge;

        return [
            'vehicle_id' => $vehicle?->id,
            'base_amount' => $baseAmount,
            'days' => $daysToCharge,
            'amount' => $calculatedAmount,
            'first_entry_time' => $entry,
            'is_free_reentry' => false,
        ];
    }

    /**
     * Calculate total billable days based on parking time using rolling 24-hour periods.
     *
     * Charging Rules:
     * - Minimum charge: 1 day (even if parked < 24 hours)
     * - Multiple days: charge for each full 24-hour period
     * - Round up: partial days are rounded up to next full day
     *
     * @param \Carbon\Carbon $entryTime
     * @param \Carbon\Carbon $exitTime
     * @return int  Billable days to charge
     */
    private function calculateDaysToCharge(\Carbon\Carbon $entryTime, \Carbon\Carbon $exitTime): int
    {
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

        // Entries today (vehicles that entered today)
        $entriesToday = $this->model
            ->whereDate('entry_time', $today->toDateString())
            ->count();

        // Exits today (completed passages - exited today)
        $exitsToday = $this->model->whereNotNull('exit_time')
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
            'entries_today' => $entriesToday,
            'exits_today' => $exitsToday,
            'completed_today' => $exitsToday,
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
