<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Vehicle;
use App\Models\VehiclePassage;
use App\Models\VehicleBodyTypePrice;
use App\Models\VehicleBodyType;
use App\Repositories\VehiclePassageRepository;

class ExitCalculationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function calculates_free_reentry_when_paid_until_in_future()
    {
        $bodyType = VehicleBodyType::forceCreate(['name' => 'Car']);
        $station = \App\Models\Station::forceCreate(['name' => 'Test Station', 'code' => 'TS-1']);
        $price = VehicleBodyTypePrice::forceCreate([
            'body_type_id' => $bodyType->id,
            'base_price' => 1000,
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'station_id' => $station->id,
        ]);

        $vehicle = Vehicle::forceCreate(['plate_number' => 'TEST-1', 'body_type_id' => $bodyType->id, 'paid_until' => now()->addHours(12)]);

        $paymentType = \App\Models\PaymentType::forceCreate(['name' => 'cash']);

        $entryTime = now()->subHours(2);

        $repo = $this->app->make(VehiclePassageRepository::class);
        $preview = $repo->calculateExitAmountForData($vehicle->id, 1000, $entryTime, now());

        $this->assertNotNull($preview);
        $this->assertEquals(0, $preview['amount']);
        $this->assertTrue($preview['is_free_reentry']);
    }

    /** @test */
    public function charges_one_day_when_within_24_hours_and_not_previously_paid()
    {
        $bodyType = VehicleBodyType::forceCreate(['name' => 'Car']);
        $station = \App\Models\Station::forceCreate(['name' => 'Test Station 2', 'code' => 'TS-2']);
        $price = VehicleBodyTypePrice::forceCreate([
            'body_type_id' => $bodyType->id,
            'base_price' => 1500,
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'station_id' => $station->id,
        ]);

        $vehicle = Vehicle::forceCreate(['plate_number' => 'TEST-2', 'body_type_id' => $bodyType->id, 'paid_until' => null]);

        $paymentType = \App\Models\PaymentType::forceCreate(['name' => 'cash']);

        $entryTime = now()->subHours(6);

        $repo = $this->app->make(VehiclePassageRepository::class);
        $preview = $repo->calculateExitAmountForData($vehicle->id, 1500, $entryTime, now());

        $this->assertNotNull($preview);
        $this->assertEquals(1500, $preview['amount']);
        $this->assertEquals(1, $preview['days']);
        $this->assertFalse($preview['is_free_reentry']);
    }
}
