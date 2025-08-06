<?php

namespace App\Repositories;

use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class StationRepository
{
    protected $model;

    public function __construct(Station $model)
    {
        $this->model = $model;
    }

    /**
     * Get all stations with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllStationsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['gates', 'vehicleBodyTypePrices'])->paginate($perPage);
    }

    /**
     * Get all active stations
     *
     * @return Collection
     */
    public function getAllActiveStations(): Collection
    {
        return $this->model->active()->with(['gates'])->get();
    }

    /**
     * Get station by ID with relationships
     *
     * @param int $id
     * @return Station|null
     */
    public function getStationByIdWithRelations(int $id): ?Station
    {
        return $this->model->with(['gates', 'vehicleBodyTypePrices'])->find($id);
    }

    /**
     * Get stations by code
     *
     * @param string $code
     * @return Collection
     */
    public function getStationsByCode(string $code): Collection
    {
        return $this->model->byCode($code)->with(['gates'])->get();
    }

    /**
     * Create a new station
     *
     * @param array $data
     * @return Station
     */
    public function createStation(array $data): Station
    {
        return $this->model->create($data);
    }

    /**
     * Update station by ID
     *
     * @param int $id
     * @param array $data
     * @return Station|null
     */
    public function updateStation(int $id, array $data): ?Station
    {
        $station = $this->model->find($id);
        if ($station) {
            $station->update($data);
            return $station->fresh();
        }
        return null;
    }

    /**
     * Delete station by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteStation(int $id): bool
    {
        $station = $this->model->find($id);
        if ($station) {
            return $station->delete();
        }
        return false;
    }

    /**
     * Search stations by name, location, or code
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchStations(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('location', 'like', "%{$search}%")
            ->orWhere('code', 'like', "%{$search}%")
            ->with(['gates', 'vehicleBodyTypePrices'])
            ->paginate($perPage);
    }

    /**
     * Get stations for dropdown/select
     *
     * @return Collection
     */
    public function getActiveStationsForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'code', 'location')
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get station statistics
     *
     * @param int $stationId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getStationStatistics(int $stationId, string $startDate, string $endDate): array
    {
        $station = $this->model->with(['vehiclePassagesAsEntry' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('entry_time', [$startDate, $endDate]);
        }, 'vehiclePassagesAsExit' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('exit_time', [$startDate, $endDate]);
        }])->find($stationId);

        if (!$station) {
            return [];
        }

        $entryPassages = $station->vehiclePassagesAsEntry;
        $exitPassages = $station->vehiclePassagesAsExit;

        return [
            'total_gates' => $station->gates->count(),
            'active_gates' => $station->gates->where('is_active', true)->count(),
            'entry_passages' => $entryPassages->count(),
            'exit_passages' => $exitPassages->count(),
            'total_passages' => $entryPassages->count() + $exitPassages->count(),
            'total_revenue' => $entryPassages->sum('total_amount') + $exitPassages->sum('total_amount'),
        ];
    }

    /**
     * Get stations with recent activity
     *
     * @param int $days
     * @return Collection
     */
    public function getStationsWithRecentActivity(int $days = 7): Collection
    {
        return $this->model->withCount(['vehiclePassagesAsEntry' => function ($query) use ($days) {
            $query->where('entry_time', '>=', now()->subDays($days));
        }, 'vehiclePassagesAsExit' => function ($query) use ($days) {
            $query->where('exit_time', '>=', now()->subDays($days));
        }])->active()->get();
    }
}
