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
                'description' => 'Cash payment at the gate',
            ],
            [
                'name' => 'Credit Card',
                'description' => 'Credit card payment',
            ],
            [
                'name' => 'Debit Card',
                'description' => 'Debit card payment',
            ],
            [
                'name' => 'Mobile Money',
                'description' => 'Mobile money transfer',
            ],
            [
                'name' => 'Bank Transfer',
                'description' => 'Direct bank transfer',
            ],
            [
                'name' => 'Account Balance',
                'description' => 'Payment from account balance',
            ],
            [
                'name' => 'Bundle Subscription',
                'description' => 'Payment covered by bundle subscription',
            ],
            [
                'name' => 'Exempted',
                'description' => 'Exempted from payment (emergency, official, etc.)',
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
