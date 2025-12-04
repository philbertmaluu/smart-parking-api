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
                    ->wherePivot('is_active', true);
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
            ->wherePivot('is_active', true)
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
            ->wherePivot('is_active', true)
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
     * Get available gates for logged-in operator (excluding gates selected by other operators)
     *
     * @param int $userId
     * @return Collection
     */
    public function getAvailableGatesForLoggedInOperator(int $userId): Collection
    {
        $user = $this->userModel->find($userId);
        
        if (!$user || !$user->hasRole('Gate Operator')) {
            return collect([]);
        }

        // Get operator's assigned stations using the relationship with pivot filtering
        // Use wherePivot for pivot table columns
        $assignedStations = $user->assignedStations()
            ->wherePivot('is_active', true)
            ->get();

        if ($assignedStations->isEmpty()) {
            return collect([]);
        }

        $stationIds = $assignedStations->pluck('id')->toArray();
        
        if (empty($stationIds)) {
            return collect([]);
        }
        
        // Get gates that are currently selected by other operators in the same stations
        $occupiedGateIds = DB::table('operator_station')
            ->whereIn('station_id', $stationIds)
            ->where('user_id', '!=', $userId)
            ->where('is_active', true)
            ->whereNotNull('current_gate_id')
            ->pluck('current_gate_id')
            ->unique()
            ->toArray();

        // Get all active gates for assigned stations, excluding occupied ones
        $availableGates = Gate::whereIn('station_id', $stationIds)
            ->where('is_active', true)
            ->when(!empty($occupiedGateIds), function ($query) use ($occupiedGateIds) {
                return $query->whereNotIn('id', $occupiedGateIds);
            })
            ->with('station')
            ->get();

        return $availableGates;
    }

    /**
     * Get the currently selected gate for an operator
     *
     * @param int $userId
     * @return Gate|null
     */
    public function getSelectedGateForOperator(int $userId): ?Gate
    {
        $user = $this->userModel->find($userId);
        if (!$user || !$user->hasRole('Gate Operator')) {
            return null;
        }

        // Get operator's assigned stations with current gate using wherePivot
        $assignedStation = $user->assignedStations()
            ->wherePivot('is_active', true)
            ->wherePivotNotNull('current_gate_id')
            ->first();

        if (!$assignedStation || !$assignedStation->pivot->current_gate_id) {
            return null;
        }

        $gateId = $assignedStation->pivot->current_gate_id;
        return Gate::with('station')->find($gateId);
    }

    /**
     * Select a gate for an operator at a station
     *
     * @param int $userId
     * @param int $stationId
     * @param int $gateId
     * @return bool
     */
    public function selectGateForOperator(int $userId, int $stationId, int $gateId): bool
    {
        $user = $this->userModel->find($userId);
        if (!$user || !$user->hasRole('Gate Operator')) {
            return false;
        }

        // Verify operator is assigned to this station
        $isAssigned = $user->assignedStations()
            ->where('station_id', $stationId)
            ->wherePivot('is_active', true)
            ->exists();

        if (!$isAssigned) {
            return false;
        }

        // Verify gate belongs to this station
        $gate = Gate::where('id', $gateId)
            ->where('station_id', $stationId)
            ->where('is_active', true)
            ->first();

        if (!$gate) {
            return false;
        }

        // Check if gate is already selected by another operator
        $isOccupied = DB::table('operator_station')
            ->where('station_id', $stationId)
            ->where('user_id', '!=', $userId)
            ->where('is_active', true)
            ->whereNotNull('current_gate_id')
            ->where('current_gate_id', $gateId)
            ->exists();

        if ($isOccupied) {
            return false;
        }

        // Update operator's current gate
        $user->assignedStations()->updateExistingPivot($stationId, [
            'current_gate_id' => $gateId,
            'gate_selected_at' => now(),
        ]);

        return true;
    }

    /**
     * Deselect gate for an operator (clear current_gate_id)
     *
     * @param int $userId
     * @return bool
     */
    public function deselectGateForOperator(int $userId): bool
    {
        $user = $this->userModel->find($userId);
        if (!$user || !$user->hasRole('Gate Operator')) {
            return false;
        }

        // Get all assigned stations with current gate
        $assignedStations = $user->assignedStations()
            ->wherePivot('is_active', true)
            ->wherePivotNotNull('current_gate_id')
            ->get();

        if ($assignedStations->isEmpty()) {
            return false;
        }

        // Clear current_gate_id for all assigned stations
        foreach ($assignedStations as $station) {
            $user->assignedStations()->updateExistingPivot($station->id, [
                'current_gate_id' => null,
                'gate_selected_at' => null,
            ]);
        }

        return true;
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

