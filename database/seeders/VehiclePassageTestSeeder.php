<?php

namespace Database\Seeders;

use App\Models\Gate;
use App\Models\PaymentType;
use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBodyType;
use App\Models\VehicleBodyTypePrice;
use App\Models\VehiclePassage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class VehiclePassageTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test station
        $station = Station::firstOrCreate([
            'code' => 'TEST_STATION_001',
        ], [
            'name' => 'Test Parking Station',
            'location' => 'Test Location',
            'is_active' => true,
        ]);

        // Get existing users from UserSeeder (don't create new ones)
        $operator1 = User::where('email', 'admin@chatoparking.go.tz')->first();
        $operator2 = User::where('email', 'manager@chatoparking.go.tz')->first();

        // Fallback in case users don't exist
        if (!$operator1 || !$operator2) {
            $this->command->error('Users not found. Please run UserSeeder first.');
            return;
        }

        // Create test gates
        $entryGate = Gate::firstOrCreate(
            ['name' => 'Entry Gate 1'],
            [
                'gate_type' => 'entry',
                'station_id' => $station->id,
                'is_active' => true,
            ]
        );

        $exitGate = Gate::firstOrCreate(
            ['name' => 'Exit Gate 1'],
            [
                'gate_type' => 'exit',
                'station_id' => $station->id,
                'is_active' => true,
            ]
        );

        // Get or create body types
        $carType = VehicleBodyType::firstOrCreate(
            ['name' => 'Car'],
            ['category' => 'car', 'is_active' => true]
        );

        $truckType = VehicleBodyType::firstOrCreate(
            ['name' => 'Truck'],
            ['category' => 'truck', 'is_active' => true]
        );

        // Create pricing for body types
        VehicleBodyTypePrice::firstOrCreate(
            [
                'body_type_id' => $carType->id,
                'station_id' => $station->id,
            ],
            [
                'base_price' => 5000, // 5000 Tsh per day
                'effective_from' => Carbon::now()->startOfDay(),
                'is_active' => true,
            ]
        );

        VehicleBodyTypePrice::firstOrCreate(
            [
                'body_type_id' => $truckType->id,
                'station_id' => $station->id,
            ],
            [
                'base_price' => 10000, // 10000 Tsh per day
                'effective_from' => Carbon::now()->startOfDay(),
                'is_active' => true,
            ]
        );

        // Get payment types
        $paymentType = PaymentType::firstOrCreate(
            ['name' => 'Cash'],
            ['is_active' => true]
        );

        // Create test vehicles with various scenarios
        $vehicles = [
            // Scenario 1: Vehicle with recent entry, no exit yet (current passage)
            [
                'plate_number' => 'TST-001-A',
                'body_type_id' => $carType->id,
                'make' => 'Toyota',
                'model' => 'Corolla',
                'color' => 'Silver',
            ],
            // Scenario 2: Vehicle with paid_until in future (within 24h free window)
            [
                'plate_number' => 'TST-002-B',
                'body_type_id' => $carType->id,
                'make' => 'Honda',
                'model' => 'Civic',
                'color' => 'Black',
                'paid_until' => Carbon::now()->addHours(2),
            ],
            // Scenario 3: Vehicle with paid_until in past (needs charging)
            [
                'plate_number' => 'TST-003-C',
                'body_type_id' => $truckType->id,
                'make' => 'Isuzu',
                'model' => 'Truck',
                'color' => 'Blue',
                'paid_until' => Carbon::now()->subHours(5),
            ],
            // Scenario 4: Vehicle with temporary body type (for testing set-vehicle-type)
            [
                'plate_number' => 'TST-004-D',
                'body_type_id' => $carType->id,
                'make' => 'Nissan',
                'model' => 'Altima',
                'color' => 'Red',
            ],
        ];

        foreach ($vehicles as $vehicleData) {
            Vehicle::firstOrCreate(
                ['plate_number' => $vehicleData['plate_number']],
                $vehicleData
            );
        }

        $vehicle1 = Vehicle::where('plate_number', 'TST-001-A')->first();
        $vehicle2 = Vehicle::where('plate_number', 'TST-002-B')->first();
        $vehicle3 = Vehicle::where('plate_number', 'TST-003-C')->first();
        $vehicle4 = Vehicle::where('plate_number', 'TST-004-D')->first();

        // Create passages for testing
        // Passage 1: Current active passage (recent entry, no exit)
        VehiclePassage::firstOrCreate(
            [
                'vehicle_id' => $vehicle1->id,
                'passage_number' => 'PASS-001-' . Carbon::now()->format('YmdHi'),
            ],
            [
                'account_id' => null,
                'payment_type_id' => $paymentType->id,
                'entry_time' => Carbon::now()->subHours(3), // 3 hours ago
                'entry_operator_id' => $operator1->id,
                'entry_gate_id' => $entryGate->id,
                'entry_station_id' => $station->id,
                'exit_time' => null,
                'exit_operator_id' => null,
                'exit_gate_id' => null,
                'exit_station_id' => null,
                'base_amount' => 5000,
                'discount_amount' => 0,
                'total_amount' => 0,
                'passage_type' => 'toll',
                'is_exempted' => false,
            ]
        );

        // Passage 2: Within 24h, should be free re-entry
        VehiclePassage::firstOrCreate(
            [
                'vehicle_id' => $vehicle2->id,
                'passage_number' => 'PASS-002-' . Carbon::now()->format('YmdHi'),
            ],
            [
                'account_id' => null,
                'payment_type_id' => $paymentType->id,
                'entry_time' => Carbon::now()->subHours(20), // 20 hours ago
                'entry_operator_id' => $operator1->id,
                'entry_gate_id' => $entryGate->id,
                'entry_station_id' => $station->id,
                'exit_time' => null,
                'exit_operator_id' => null,
                'exit_gate_id' => null,
                'exit_station_id' => null,
                'base_amount' => 5000,
                'discount_amount' => 0,
                'total_amount' => 0,
                'passage_type' => 'toll',
                'is_exempted' => false,
            ]
        );

        // Passage 3: Multi-day entry (should charge for 2 days)
        VehiclePassage::firstOrCreate(
            [
                'vehicle_id' => $vehicle3->id,
                'passage_number' => 'PASS-003-' . Carbon::now()->format('YmdHi'),
            ],
            [
                'account_id' => null,
                'payment_type_id' => $paymentType->id,
                'entry_time' => Carbon::now()->subDays(2)->subHours(5), // 2.2 days ago
                'entry_operator_id' => $operator2->id,
                'entry_gate_id' => $entryGate->id,
                'entry_station_id' => $station->id,
                'exit_time' => null,
                'exit_operator_id' => null,
                'exit_gate_id' => null,
                'exit_station_id' => null,
                'base_amount' => 10000,
                'discount_amount' => 0,
                'total_amount' => 0,
                'passage_type' => 'toll',
                'is_exempted' => false,
            ]
        );

        // Passage 4: Vehicle without body type (for set-vehicle-type testing)
        VehiclePassage::firstOrCreate(
            [
                'vehicle_id' => $vehicle4->id,
                'passage_number' => 'PASS-004-' . Carbon::now()->format('YmdHi'),
            ],
            [
                'account_id' => null,
                'payment_type_id' => $paymentType->id,
                'entry_time' => Carbon::now()->subHours(2),
                'entry_operator_id' => $operator1->id,
                'entry_gate_id' => $entryGate->id,
                'entry_station_id' => $station->id,
                'exit_time' => null,
                'exit_operator_id' => null,
                'exit_gate_id' => null,
                'exit_station_id' => null,
                'base_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'passage_type' => 'toll',
                'is_exempted' => false,
            ]
        );

        $this->command->info('Vehicle Passage Test data seeded successfully!');
        $this->command->info('Created: 1 station, 2 operators, 2 gates, 4 vehicles, 4 passages');
    }
}

