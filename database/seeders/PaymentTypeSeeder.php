<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentTypes = [
            [
                'name' => 'Cash',
                'description' => 'Cash payment at the gate (toll fee)',
            ],
            [
                'name' => 'Bundle',
                'description' => 'Bundle subscription payment (free passage)',
            ],
            [
                'name' => 'Exemption',
                'description' => 'Exempted from payment (emergency, official vehicles)',
            ],
        ];

        foreach ($paymentTypes as $paymentType) {
            PaymentType::updateOrCreate(
                ['name' => $paymentType['name']],
                [
                    'description' => $paymentType['description'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Payment types seeded successfully!');
    }
}
