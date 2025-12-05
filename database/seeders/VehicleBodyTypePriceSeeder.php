<?php

namespace Database\Seeders;

use App\Models\VehicleBodyTypePrice;
use App\Models\VehicleBodyType;
use App\Models\Station;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VehicleBodyTypePriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $station = Station::first();
        
        if (!$station) {
            $this->command->warn('No station found. Please create a station first.');
            return;
        }

        $bodyTypes = VehicleBodyType::all();
        
        if ($bodyTypes->isEmpty()) {
            $this->command->warn('No vehicle body types found. Please run VehicleBodyTypeSeeder first.');
            return;
        }

        // Define pricing per vehicle type (in Tanzanian Shillings)
        $pricing = [
            'Motorcycle' => 2000,      // 2,000 TZS per day
            'Car' => 5000,              // 5,000 TZS per day
            'SUV' => 7000,              // 7,000 TZS per day
            'Van' => 8000,              // 8,000 TZS per day
            'Minibus' => 10000,         // 10,000 TZS per day
            'Bus' => 15000,             // 15,000 TZS per day
            'Truck' => 20000,           // 20,000 TZS per day
            'Pickup' => 6000,           // 6,000 TZS per day
        ];

        $effectiveFrom = Carbon::today()->toDateString(); // Use date string format
        $createdCount = 0;

        foreach ($bodyTypes as $bodyType) {
            $basePrice = $pricing[$bodyType->name] ?? 5000; // Default to 5,000 TZS if not specified

            // Check if price already exists
            $existingPrice = VehicleBodyTypePrice::where('body_type_id', $bodyType->id)
                ->where('station_id', $station->id)
                ->where('effective_from', $effectiveFrom)
                ->first();

            if (!$existingPrice) {
                VehicleBodyTypePrice::create([
                    'body_type_id' => $bodyType->id,
                    'station_id' => $station->id,
                    'base_price' => $basePrice,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null, // Open-ended
                    'is_active' => true,
                ]);
                $createdCount++;
                $this->command->info("Created pricing for {$bodyType->name}: TZS {$basePrice}");
            } else {
                // Update existing price
                $existingPrice->update([
                    'base_price' => $basePrice,
                    'is_active' => true,
                ]);
                $this->command->info("Updated pricing for {$bodyType->name}: TZS {$basePrice}");
            }
        }

        $this->command->info("Vehicle body type pricing seeded successfully! Created/Updated {$createdCount} pricing records.");
    }
}
