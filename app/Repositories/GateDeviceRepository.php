<?php

namespace App\Repositories;

use App\Models\GateDevice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GateDeviceRepository
{
    protected $model;

    public function __construct(GateDevice $model)
    {
        $this->model = $model;
    }

    /**
     * Get all gate devices with pagination
     */
    public function getAllGateDevicesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['gate.station'])->paginate($perPage);
    }

    /**
     * Get gate device by ID with relationships
     */
    public function getGateDeviceByIdWithRelations(int $id): ?GateDevice
    {
        return $this->model->with(['gate.station'])->find($id);
    }

    /**
     * Get gate devices by gate ID
     */
    public function getGateDevicesByGate(int $gateId): Collection
    {
        return $this->model->byGate($gateId)->with(['gate.station'])->get();
    }

    /**
     * Get gate devices by type
     */
    public function getGateDevicesByType(string $type): Collection
    {
        return $this->model->byType($type)->with(['gate.station'])->get();
    }

    /**
     * Get active gate devices
     */
    public function getActiveGateDevices(): Collection
    {
        return $this->model->active()->with(['gate.station'])->get();
    }

    /**
     * Search gate devices
     */
    public function searchGateDevices(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->where('name', 'like', "%{$search}%")
            ->orWhere('ip_address', 'like', "%{$search}%")
            ->orWhere('device_type', 'like', "%{$search}%")
            ->orWhereHas('gate', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with(['gate.station'])
            ->paginate($perPage);
    }

    /**
     * Create a new gate device
     */
    public function createGateDevice(array $data): GateDevice
    {
        return $this->model->create($data);
    }

    /**
     * Update a gate device
     */
    public function updateGateDevice(int $id, array $data): ?GateDevice
    {
        $device = $this->model->find($id);
        if ($device) {
            $device->update($data);
            return $device->fresh(['gate.station']);
        }
        return null;
    }

    /**
     * Delete a gate device
     */
    public function deleteGateDevice(int $id): bool
    {
        $device = $this->model->find($id);
        if ($device) {
            return $device->delete();
        }
        return false;
    }
}

