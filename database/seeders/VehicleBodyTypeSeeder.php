<?php

namespace Database\Seeders;

use App\Models\VehicleBodyType;
use Illuminate\Database\Seeder;

class VehicleBodyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleBodyTypes = [
            [
                'name' => 'Motorcycle',
                'description' => 'Two-wheeled motorized vehicle',
            ],
            [
                'name' => 'Sedan',
                'description' => 'Four-door passenger car',
            ],
            [
                'name' => 'SUV',
                'description' => 'Sport Utility Vehicle',
            ],
            [
                'name' => 'Truck',
                'description' => 'Commercial truck for cargo transport',
            ],
            [
                'name' => 'Bus',
                'description' => 'Large passenger vehicle for public transport',
            ],
            [
                'name' => 'Van',
                'description' => 'Multi-purpose vehicle for passengers or cargo',
            ],
            [
                'name' => 'Pickup',
                'description' => 'Truck with open cargo area',
            ],
            [
                'name' => 'Trailer',
                'description' => 'Non-motorized vehicle towed by another vehicle',
            ],
        ];

        foreach ($vehicleBodyTypes as $bodyType) {
            VehicleBodyType::updateOrCreate(
                ['name' => $bodyType['name']],
                [
                    'description' => $bodyType['description'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Vehicle body types seeded successfully!');
    }
}
