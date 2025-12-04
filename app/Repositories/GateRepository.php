<?php

namespace App\Repositories;

use App\Models\Gate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GateRepository
{
    protected $model;

    public function __construct(Gate $model)
    {
        $this->model = $model;
    }

    /**
     * Get all gates with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllGatesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['station', 'vehiclePassagesAsEntry', 'vehiclePassagesAsExit'])->paginate($perPage);
    }

    /**
     * Get all active gates
     *
     * @return Collection
     */
    public function getAllActiveGates(): Collection
    {
        return $this->model->active()->with(['station'])->get();
    }

    /**
     * Get gate by ID with relationships
     *
     * @param int $id
     * @return Gate|null
     */
    public function getGateByIdWithRelations(int $id): ?Gate
    {
        return $this->model->with(['station', 'vehiclePassagesAsEntry', 'vehiclePassagesAsExit'])->find($id);
    }

    /**
     * Get gates by station ID
     *
     * @param int $stationId
     * @return Collection
     */
    public function getGatesByStation(int $stationId): Collection
    {
        return $this->model->byStation($stationId)->with(['station'])->get();
    }

    /**
     * Get gates by type
     *
     * @param string $type
     * @return Collection
     */
    public function getGatesByType(string $type): Collection
    {
        return $this->model->byType($type)->with(['station'])->get();
    }

    /**
     * Get entry gates
     *
     * @return Collection
     */
    public function getEntryGates(): Collection
    {
        return $this->model->byType('entry')->with(['station'])->get();
    }

    /**
     * Get exit gates
     *
     * @return Collection
     */
    public function getExitGates(): Collection
    {
        return $this->model->byType('exit')->with(['station'])->get();
    }

    /**
     * Get both entry and exit gates
     *
     * @return Collection
     */
    public function getBothGates(): Collection
    {
        return $this->model->byType('both')->with(['station'])->get();
    }

    /**
     * Create a new gate
     *
     * @param array $data
     * @return Gate
     */
    public function createGate(array $data): Gate
    {
        return $this->model->create($data);
    }

    /**
     * Update gate by ID
     *
     * @param int $id
     * @param array $data
     * @return Gate|null
     */
    public function updateGate(int $id, array $data): ?Gate
    {
        $gate = $this->model->find($id);
        if ($gate) {
            $gate->update($data);
            return $gate->fresh();
        }
        return null;
    }

    /**
     * Delete gate by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteGate(int $id): bool
    {
        $gate = $this->model->find($id);
        if ($gate) {
            return $gate->delete();
        }
        return false;
    }

    /**
     * Get gates with vehicle passage count
     *
     * @return Collection
     */
    public function getGatesWithVehiclePassageCount(): Collection
    {
        return $this->model->withCount(['vehiclePassagesAsEntry', 'vehiclePassagesAsExit'])->get();
    }

    /**
     * Search gates by name or station
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchGates(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhereHas('station', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->with(['station', 'vehiclePassagesAsEntry', 'vehiclePassagesAsExit'])
            ->paginate($perPage);
    }

    /**
     * Get gates for dropdown/select
     *
     * @return Collection
     */
    public function getGatesForSelect(): Collection
    {
        return $this->model->select('id', 'name', 'gate_type', 'station_id')
            ->with('station:id,name,code')
            ->active()
            ->orderBy('station_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get gates by station for dropdown/select
     *
     * @param int $stationId
     * @return Collection
     */
    public function getGatesByStationForSelect(int $stationId): Collection
    {
        return $this->model->select('id', 'name', 'gate_type')
            ->byStation($stationId)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get gate statistics
     *
     * @param int $gateId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getGateStatistics(int $gateId, string $startDate, string $endDate): array
    {
        $gate = $this->model->with(['vehiclePassagesAsEntry' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('entry_time', [$startDate, $endDate]);
        }, 'vehiclePassagesAsExit' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('exit_time', [$startDate, $endDate]);
        }])->find($gateId);

        if (!$gate) {
            return [];
        }

        $entryPassages = $gate->vehiclePassagesAsEntry;
        $exitPassages = $gate->vehiclePassagesAsExit;

        return [
            'entry_passages' => $entryPassages->count(),
            'exit_passages' => $exitPassages->count(),
            'total_passages' => $entryPassages->count() + $exitPassages->count(),
            'entry_revenue' => $entryPassages->sum('total_amount'),
            'exit_revenue' => $exitPassages->sum('total_amount'),
            'total_revenue' => $entryPassages->sum('total_amount') + $exitPassages->sum('total_amount'),
        ];
    }

    /**
     * Get gates with recent activity
     *
     * @param int $days
     * @return Collection
     */
    public function getGatesWithRecentActivity(int $days = 7): Collection
    {
        return $this->model->withCount(['vehiclePassagesAsEntry' => function ($query) use ($days) {
            $query->where('entry_time', '>=', now()->subDays($days));
        }, 'vehiclePassagesAsExit' => function ($query) use ($days) {
            $query->where('exit_time', '>=', now()->subDays($days));
        }])->active()->get();
    }

    /**
     * Get active gates by station and type
     *
     * @param int $stationId
     * @param string $type
     * @return Collection
     */
    public function getActiveGatesByStationAndType(int $stationId, string $type): Collection
    {
        return $this->model->byStation($stationId)
            ->byType($type)
            ->active()
            ->with('station')
            ->get();
    }
}
