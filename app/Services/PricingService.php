<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\Station;
use App\Models\Account;
use App\Models\BundleSubscription;
use App\Models\PaymentType;
use App\Models\VehicleBodyTypePrice;
use App\Repositories\VehicleBodyTypePriceRepository;
use Illuminate\Support\Facades\Log;

class PricingService
{
    protected $vehicleBodyTypePriceRepository;

    public function __construct(VehicleBodyTypePriceRepository $vehicleBodyTypePriceRepository)
    {
        $this->vehicleBodyTypePriceRepository = $vehicleBodyTypePriceRepository;
    }

    /**
     * Calculate pricing for vehicle entry
     *
     * @param Vehicle $vehicle
     * @param Station $station
     * @param Account|null $account
     * @return array
     */
    public function calculatePricing(Vehicle $vehicle, Station $station, ?Account $account = null): array
    {
        try {
            // Step 1: Determine payment type
            $paymentType = $this->determinePaymentType($vehicle, $account);

            // Step 2: Calculate pricing based on payment type
            return match ($paymentType->name) {
                'Exemption' => $this->calculateExemptionPricing($vehicle),
                'Bundle' => $this->calculateBundlePricing($vehicle, $account),
                'Cash' => $this->calculateCashPricing($vehicle, $station),
                default => $this->calculateCashPricing($vehicle, $station),
            };
        } catch (\Exception $e) {
            Log::error('Error calculating pricing', [
                'vehicle_id' => $vehicle->id,
                'station_id' => $station->id,
                'error' => $e->getMessage()
            ]);

            // Fallback to cash pricing
            return $this->calculateCashPricing($vehicle, $station);
        }
    }

    /**
     * Determine payment type for vehicle
     *
     * @param Vehicle $vehicle
     * @param Account|null $account
     * @return PaymentType
     */
    public function determinePaymentType(Vehicle $vehicle, ?Account $account = null): PaymentType
    {
        // Step 1: Check if vehicle is exempted
        if ($vehicle->isCurrentlyExempted()) {
            return PaymentType::where('name', 'Exemption')->first();
        }

        // Step 2: Check for active bundle subscription
        if ($account && $this->hasActiveBundleSubscription($account)) {
            return PaymentType::where('name', 'Bundle')->first();
        }

        // Step 3: Default to cash payment
        return PaymentType::where('name', 'Cash')->first();
    }

    /**
     * Calculate exemption pricing (free)
     *
     * @param Vehicle $vehicle
     * @return array
     */
    private function calculateExemptionPricing(Vehicle $vehicle): array
    {
        return [
            'amount' => 0,
            'payment_type' => 'Exemption',
            'payment_type_id' => PaymentType::where('name', 'Exemption')->first()->id,
            'requires_payment' => false,
            'description' => 'Exempted vehicle: ' . ($vehicle->exemption_reason ?? 'No reason specified'),
            'base_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
        ];
    }

    /**
     * Calculate bundle pricing (free)
     *
     * @param Vehicle $vehicle
     * @param Account $account
     * @return array
     */
    private function calculateBundlePricing(Vehicle $vehicle, Account $account): array
    {
        $bundleSubscription = $this->getActiveBundleSubscription($account);

        return [
            'amount' => 0,
            'payment_type' => 'Bundle',
            'payment_type_id' => PaymentType::where('name', 'Bundle')->first()->id,
            'requires_payment' => false,
            'description' => 'Bundle subscription active: ' . ($bundleSubscription->bundle->name ?? 'Unknown bundle'),
            'base_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'bundle_subscription_id' => $bundleSubscription->id,
        ];
    }

    /**
     * Calculate cash pricing (toll fee)
     *
     * @param Vehicle $vehicle
     * @param Station $station
     * @return array
     */
    private function calculateCashPricing(Vehicle $vehicle, Station $station): array
    {
        // Get base price from VehicleBodyTypePrice
        $basePrice = $this->getBasePrice($vehicle->body_type_id, $station->id);

        if (!$basePrice) {
            Log::warning('No pricing found for vehicle body type and station', [
                'body_type_id' => $vehicle->body_type_id,
                'station_id' => $station->id,
                'vehicle_id' => $vehicle->id
            ]);

            return [
                'amount' => 0,
                'payment_type' => 'Cash',
                'payment_type_id' => PaymentType::where('name', 'Cash')->first()->id,
                'requires_payment' => false,
                'description' => 'No pricing configured for this vehicle type and station',
                'base_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
            ];
        }

        return [
            'amount' => $basePrice->base_price,
            'payment_type' => 'Cash',
            'payment_type_id' => PaymentType::where('name', 'Cash')->first()->id,
            'requires_payment' => true,
            'description' => 'Toll fee required',
            'base_amount' => $basePrice->base_price,
            'discount_amount' => 0,
            'total_amount' => $basePrice->base_price,
            'pricing_id' => $basePrice->id,
        ];
    }

    /**
     * Get base price for vehicle body type and station
     *
     * @param int $bodyTypeId
     * @param int $stationId
     * @return VehicleBodyTypePrice|null
     */
    public function getBasePrice(int $bodyTypeId, int $stationId): ?VehicleBodyTypePrice
    {
        return $this->vehicleBodyTypePriceRepository->getCurrentPrice($bodyTypeId, $stationId);
    }

    /**
     * Check if account has active bundle subscription
     *
     * @param Account $account
     * @return bool
     */
    public function hasActiveBundleSubscription(Account $account): bool
    {
        return $account->bundleSubscriptions()
            ->where('status', 'active')
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now())
            ->exists();
    }

    /**
     * Get active bundle subscription for account
     *
     * @param Account $account
     * @return BundleSubscription|null
     */
    public function getActiveBundleSubscription(Account $account): ?BundleSubscription
    {
        return $account->bundleSubscriptions()
            ->where('status', 'active')
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now())
            ->first();
    }

    /**
     * Calculate pricing for multiple vehicles
     *
     * @param array $vehicles
     * @param Station $station
     * @param Account|null $account
     * @return array
     */
    public function calculateBulkPricing(array $vehicles, Station $station, ?Account $account = null): array
    {
        $results = [];

        foreach ($vehicles as $vehicle) {
            $results[] = [
                'vehicle' => $vehicle,
                'pricing' => $this->calculatePricing($vehicle, $station, $account)
            ];
        }

        return $results;
    }

    /**
     * Get pricing summary for station
     *
     * @param int $stationId
     * @return array
     */
    public function getStationPricingSummary(int $stationId): array
    {
        $prices = $this->vehicleBodyTypePriceRepository->getCurrentPricesForStation($stationId);

        $summary = [
            'station_id' => $stationId,
            'total_vehicle_types' => $prices->count(),
            'price_range' => [
                'min' => $prices->min('base_price'),
                'max' => $prices->max('base_price'),
                'average' => $prices->avg('base_price'),
            ],
            'prices_by_body_type' => $prices->groupBy('body_type_id')->map(function ($group) {
                return [
                    'body_type' => $group->first()->bodyType,
                    'price' => $group->first()->base_price,
                ];
            }),
        ];

        return $summary;
    }

    /**
     * Validate pricing configuration
     *
     * @param int $stationId
     * @return array
     */
    public function validatePricingConfiguration(int $stationId): array
    {
        $prices = $this->vehicleBodyTypePriceRepository->getCurrentPricesForStation($stationId);
        $allBodyTypes = \App\Models\VehicleBodyType::active()->get();

        $missingPricing = $allBodyTypes->whereNotIn('id', $prices->pluck('body_type_id'));

        return [
            'station_id' => $stationId,
            'total_body_types' => $allBodyTypes->count(),
            'configured_pricing' => $prices->count(),
            'missing_pricing' => $missingPricing->count(),
            'missing_body_types' => $missingPricing->values(),
            'is_complete' => $missingPricing->isEmpty(),
        ];
    }
}
