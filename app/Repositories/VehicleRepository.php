<?php

namespace App\Repositories;

use App\Models\Vehicle;
use App\Models\VehicleBodyTypePrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VehicleRepository
{
    protected $model;

    public function __construct(Vehicle $model)
    {
        $this->model = $model;
    }

    /**
     * Get all vehicles with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllVehiclesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['bodyType', 'vehiclePassages'])->paginate($perPage);
    }

    /**
     * Get all active vehicles
     *
     * @return Collection
     */
    public function getAllActiveVehicles(): Collection
    {
        return $this->model->registered()->with(['bodyType'])->get();
    }

    /**
     * Get vehicle by ID with relationships
     *
     * @param int $id
     * @return Vehicle|null
     */
    public function getVehicleByIdWithRelations(int $id): ?Vehicle
    {
        return $this->model->with(['bodyType', 'vehiclePassages'])->find($id);
    }

    /**
     * Get vehicles by body type
     *
     * @param int $bodyTypeId
     * @return Collection
     */
    public function getVehiclesByBodyType(int $bodyTypeId): Collection
    {
        return $this->model->byBodyType($bodyTypeId)->with(['bodyType'])->get();
    }

    /**
     * Create a new vehicle
     *
     * @param array $data
     * @return Vehicle
     */
    public function createVehicle(array $data): Vehicle
    {
        $vehicle = $this->model->create($data);
        return $vehicle->load('bodyType');
    }

    /**
     * Update vehicle by ID
     *
     * @param int $id
     * @param array $data
     * @return Vehicle|null
     */
    public function updateVehicle(int $id, array $data): ?Vehicle
    {
        $vehicle = $this->model->find($id);
        if ($vehicle) {
            $vehicle->update($data);
            return $vehicle->fresh();
        }
        return null;
    }

    /**
     * Delete vehicle by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteVehicle(int $id): bool
    {
        $vehicle = $this->model->find($id);
        if ($vehicle) {
            return $vehicle->delete();
        }
        return false;
    }

    /**
     * Search vehicles by plate number, brand, or model
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchVehicles(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('plate_number', 'like', "%{$search}%")
            ->orWhere('make', 'like', "%{$search}%")
            ->orWhere('model', 'like', "%{$search}%")
            ->with(['bodyType', 'vehiclePassages'])
            ->paginate($perPage);
    }

    /**
     * Get vehicles for dropdown/select
     *
     * @return Collection
     */
    public function getActiveVehiclesForSelect(): Collection
    {
        return $this->model->select('id', 'plate_number', 'make', 'model', 'body_type_id')
            ->with('bodyType:id,name')
            ->registered()
            ->orderBy('plate_number')
            ->get();
    }

    /**
     * Get vehicles by body type for dropdown/select
     *
     * @param int $bodyTypeId
     * @return Collection
     */
    public function getVehiclesByBodyTypeForSelect(int $bodyTypeId): Collection
    {
        return $this->model->select('id', 'plate_number', 'make', 'model')
            ->byBodyType($bodyTypeId)
            ->registered()
            ->orderBy('plate_number')
            ->get();
    }

    /**
     * Get vehicle statistics
     *
     * @param int $vehicleId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getVehicleStatistics(int $vehicleId, string $startDate, string $endDate): array
    {
        $vehicle = $this->model->with(['vehiclePassages' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('entry_time', [$startDate, $endDate]);
        }])->find($vehicleId);

        if (!$vehicle) {
            return [];
        }

        $passages = $vehicle->vehiclePassages;

        return [
            'total_passages' => $passages->count(),
            'total_amount' => $passages->sum('total_amount'),
            'completed_passages' => $passages->where('status', 'completed')->count(),
            'pending_passages' => $passages->where('status', 'pending')->count(),
            'cancelled_passages' => $passages->where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Get vehicles with recent activity
     *
     * @param int $days
     * @return Collection
     */
    public function getVehiclesWithRecentActivity(int $days = 7): Collection
    {
        return $this->model->withCount(['vehiclePassages' => function ($query) use ($days) {
            $query->where('entry_time', '>=', now()->subDays($days));
        }])->registered()->get();
    }

    /**
     * Search vehicle by plate number
     *
     * @param string $plateNumber
     * @return Vehicle|null
     */
    public function searchByPlateNumber(string $plateNumber): ?Vehicle
    {
        return $this->model->where('plate_number', 'like', "%{$plateNumber}%")
            ->with(['bodyType', 'bodyType.prices'])
            ->with(['vehiclePassages'])
            ->first();
    }

    /**
     * Lookup vehicle by exact plate number
     *
     * @param string $plateNumber
     * @return Vehicle|null
     */
    public function lookupByPlateNumber(string $plateNumber): ?Vehicle
    {
        return $this->model->where('plate_number', $plateNumber)
            ->with(['bodyType'])
            ->first();
    }

    /**
     * Get all registered vehicles
     *
     * @return Collection
     */
    public function getRegisteredVehicles(): Collection
    {
        return $this->model->registered()
            ->with(['bodyType'])
            ->orderBy('plate_number')
            ->get();
    }

    /**
     * Get all unregistered vehicles
     *
     * @return Collection
     */
    public function getUnregisteredVehicles(): Collection
    {
        return $this->model->where('is_registered', false)
            ->with(['bodyType'])
            ->orderBy('plate_number')
            ->get();
    }

    /**
     * Get active vehicles list (for dropdown/select)
     *
     * @return Collection
     */
    public function getActiveVehiclesList(): Collection
    {
        return $this->model->select('id', 'plate_number', 'make', 'model', 'body_type_id', 'is_registered')
            ->with('bodyType:id,name')
            ->orderBy('plate_number')
            ->get();
    }
}
