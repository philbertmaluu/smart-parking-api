<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Station;
use App\Models\Gate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OperatorRepository
{
    protected $userModel;

    public function __construct(User $userModel)
    {
        $this->userModel = $userModel;
    }

    /**
     * Get all operators (users with Gate Operator role) with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllOperatorsPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userModel
            ->byRole('Gate Operator')
            ->with(['role', 'assignedStations'])
            ->paginate($perPage);
    }

    /**
     * Get all operators (users with Gate Operator role)
     *
     * @return Collection
     */
    public function getAllOperators(): Collection
    {
        return $this->userModel
            ->byRole('Gate Operator')
            ->with(['role', 'assignedStations'])
            ->get();
    }

    /**
     * Get operator by ID with relationships
     *
     * @param int $id
     * @return User|null
     */
    public function getOperatorByIdWithRelations(int $id): ?User
    {
        return $this->userModel
            ->byRole('Gate Operator')
            ->with(['role', 'assignedStations'])
            ->find($id);
    }

    /**
     * Assign operator to a station
     *
     * @param int $userId
     * @param int $stationId
     * @param int|null $assignedBy
     * @return bool
     */
    public function assignOperatorToStation(int $userId, int $stationId, ?int $assignedBy = null): bool
    {
        $user = $this->userModel->find($userId);
        if (!$user || !$user->hasRole('Gate Operator')) {
            return false;
        }

        // Check if already assigned
        if ($user->assignedStations()->where('station_id', $stationId)->exists()) {
            // Update existing assignment
            $user->assignedStations()->updateExistingPivot($stationId, [
                'is_active' => true,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]);
        } else {
            // Create new assignment
            $user->assignedStations()->attach($stationId, [
                'is_active' => true,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * Unassign operator from a station
     *
     * @param int $userId
     * @param int $stationId
     * @return bool
     */
    public function unassignOperatorFromStation(int $userId, int $stationId): bool
    {
        $user = $this->userModel->find($userId);
        if (!$user || !$user->hasRole('Gate Operator')) {
            return false;
        }

        $user->assignedStations()->updateExistingPivot($stationId, [
            'is_active' => false,
        ]);

        return true;
    }

    /**
     * Get operators assigned to a specific station
     *
     * @param int $stationId
     * @return Collection
     */
    public function getOperatorsByStation(int $stationId): Collection
    {
        return $this->userModel
            ->byRole('Gate Operator')
            ->whereHas('assignedStations', function ($query) use ($stationId) {
                $query->where('station_id', $stationId)
                    ->where('operator_station.is_active', true);
            })
            ->with(['role', 'assignedStations' => function ($query) use ($stationId) {
                $query->where('station_id', $stationId);
            }])
            ->get();
    }

    /**
     * Get stations assigned to an operator
     *
     * @param int $userId
     * @return Collection
     */
    public function getStationsByOperator(int $userId): Collection
    {
        $user = $this->userModel->find($userId);
        if (!$user || !$user->hasRole('Gate Operator')) {
            return collect([]);
        }

        return $user->assignedStations()
            ->where('operator_station.is_active', true)
            ->get();
    }

    /**
     * Get available gates for an operator at a station
     *
     * @param int $userId
     * @param int $stationId
     * @return Collection
     */
    public function getAvailableGatesForOperator(int $userId, int $stationId): Collection
    {
        $user = $this->userModel->find($userId);
        if (!$user || !$user->hasRole('Gate Operator')) {
            return collect([]);
        }

        // Check if operator is assigned to this station
        $isAssigned = $user->assignedStations()
            ->where('station_id', $stationId)
            ->where('operator_station.is_active', true)
            ->exists();

        if (!$isAssigned) {
            return collect([]);
        }

        // Get all active gates for this station
        return Gate::where('station_id', $stationId)
            ->where('is_active', true)
            ->with('station')
            ->get();
    }

    /**
     * Search operators
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchOperators(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->userModel
            ->byRole('Gate Operator')
            ->where(function ($query) use ($search) {
                $query->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->with(['role', 'assignedStations'])
            ->paginate($perPage);
    }
}

