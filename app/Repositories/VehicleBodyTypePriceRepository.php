<?php

namespace App\Repositories;

use App\Models\VehicleBodyTypePrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class VehicleBodyTypePriceRepository
{
    protected $model;

    public function __construct(VehicleBodyTypePrice $model)
    {
        $this->model = $model;
    }

    /**
     * Get all vehicle body type prices with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllVehicleBodyTypePricesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['bodyType', 'station'])->paginate($perPage);
    }

    /**
     * Get all active vehicle body type prices
     *
     * @return Collection
     */
    public function getAllActiveVehicleBodyTypePrices(): Collection
    {
        return $this->model->active()->with(['bodyType', 'station'])->get();
    }

    /**
     * Get vehicle body type price by ID with relationships
     *
     * @param int $id
     * @return VehicleBodyTypePrice|null
     */
    public function getVehicleBodyTypePriceByIdWithRelations(int $id): ?VehicleBodyTypePrice
    {
        return $this->model->with(['bodyType', 'station'])->find($id);
    }

    /**
     * Get current effective price for a body type and station
     *
     * @param int $bodyTypeId
     * @param int $stationId
     * @param string|null $date
     * @return VehicleBodyTypePrice|null
     */
    public function getCurrentPrice(int $bodyTypeId, int $stationId, ?string $date = null): ?VehicleBodyTypePrice
    {
        $date = $date ?? now()->toDateString();

        return $this->model->where('body_type_id', $bodyTypeId)
            ->where('station_id', $stationId)
            ->active()
            ->effective($date)
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Get all current effective prices for a station
     *
     * @param int $stationId
     * @param string|null $date
     * @return Collection
     */
    public function getCurrentPricesForStation(int $stationId, ?string $date = null): Collection
    {
        $date = $date ?? now()->toDateString();

        return $this->model->where('station_id', $stationId)
            ->active()
            ->effective($date)
            ->with(['bodyType'])
            ->orderBy('body_type_id')
            ->get();
    }

    /**
     * Get all current effective prices for a body type
     *
     * @param int $bodyTypeId
     * @param string|null $date
     * @return Collection
     */
    public function getCurrentPricesForBodyType(int $bodyTypeId, ?string $date = null): Collection
    {
        $date = $date ?? now()->toDateString();

        return $this->model->where('body_type_id', $bodyTypeId)
            ->active()
            ->effective($date)
            ->with(['station'])
            ->orderBy('station_id')
            ->get();
    }

    /**
     * Create a new vehicle body type price
     *
     * @param array $data
     * @return VehicleBodyTypePrice
     */
    public function createVehicleBodyTypePrice(array $data): VehicleBodyTypePrice
    {
        return $this->model->create($data);
    }

    /**
     * Update vehicle body type price by ID
     *
     * @param int $id
     * @param array $data
     * @return VehicleBodyTypePrice|null
     */
    public function updateVehicleBodyTypePrice(int $id, array $data): ?VehicleBodyTypePrice
    {
        $vehicleBodyTypePrice = $this->model->find($id);
        if ($vehicleBodyTypePrice) {
            $vehicleBodyTypePrice->update($data);
            return $vehicleBodyTypePrice->fresh();
        }
        return null;
    }

    /**
     * Delete vehicle body type price by ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteVehicleBodyTypePrice(int $id): bool
    {
        $vehicleBodyTypePrice = $this->model->find($id);
        if ($vehicleBodyTypePrice) {
            return $vehicleBodyTypePrice->delete();
        }
        return false;
    }

    /**
     * Bulk update prices for multiple body types and stations
     *
     * @param array $prices
     * @return bool
     */
    public function bulkUpdatePrices(array $prices): bool
    {
        try {
            foreach ($prices as $price) {
                $this->model->updateOrCreate(
                    [
                        'body_type_id' => $price['body_type_id'],
                        'station_id' => $price['station_id'],
                        'effective_from' => $price['effective_from'],
                    ],
                    [
                        'base_price' => $price['base_price'],
                        'effective_to' => $price['effective_to'] ?? null,
                        'is_active' => $price['is_active'] ?? true,
                    ]
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get pricing history for a body type and station
     *
     * @param int $bodyTypeId
     * @param int $stationId
     * @return Collection
     */
    public function getPricingHistory(int $bodyTypeId, int $stationId): Collection
    {
        return $this->model->where('body_type_id', $bodyTypeId)
            ->where('station_id', $stationId)
            ->orderBy('effective_from', 'desc')
            ->get();
    }

    /**
     * Get all prices effective on a specific date
     *
     * @param string $date
     * @return Collection
     */
    public function getPricesEffectiveOnDate(string $date): Collection
    {
        return $this->model->active()
            ->effective($date)
            ->with(['bodyType', 'station'])
            ->orderBy('station_id')
            ->orderBy('body_type_id')
            ->get();
    }

    /**
     * Search vehicle body type prices
     *
     * @param string $search
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchVehicleBodyTypePrices(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->whereHas('bodyType', function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%");
        })
            ->orWhereHas('station', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with(['bodyType', 'station'])
            ->paginate($perPage);
    }

    /**
     * Get pricing summary for dashboard
     *
     * @return array
     */
    public function getPricingSummary(): array
    {
        $totalPrices = $this->model->count();
        $activePrices = $this->model->active()->count();
        $currentPrices = $this->model->active()->effective()->count();

        return [
            'total_prices' => $totalPrices,
            'active_prices' => $activePrices,
            'current_prices' => $currentPrices,
            'inactive_prices' => $totalPrices - $activePrices,
        ];
    }

    /**
     * Get price comparison between stations
     *
     * @param int $bodyTypeId
     * @return Collection
     */
    public function getPriceComparison(int $bodyTypeId): Collection
    {
        return $this->model->where('body_type_id', $bodyTypeId)
            ->active()
            ->effective()
            ->with(['station'])
            ->orderBy('base_price', 'desc')
            ->get();
    }
}
