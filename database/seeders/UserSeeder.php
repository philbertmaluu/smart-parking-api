<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get roles
        $systemAdminRole = Role::where('name', 'System Administrator')->first();
        $stationsManagerRole = Role::where('name', 'Stations Manager')->first();
        $gateOperatorRole = Role::where('name', 'Gate Operator')->first();

        // Create System Administrator
        $systemAdmin = User::updateOrCreate(
            ['email' => 'admin@smartparking.com'],
            [
                'username' => 'admin',
                'email' => 'admin@smartparking.com',
                'phone' => '+1234567890',
                'password' => Hash::make('password123'),
                'address' => '123 Admin Street, City, State 12345',
                'gender' => 'male',
                'date_of_birth' => '1985-01-15',
                'role_id' => $systemAdminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create Stations Manager
        $stationsManager = User::updateOrCreate(
            ['email' => 'manager@smartparking.com'],
            [
                'username' => 'manager',
                'email' => 'manager@smartparking.com',
                'phone' => '+1234567891',
                'password' => Hash::make('password123'),
                'address' => '456 Manager Avenue, City, State 12345',
                'gender' => 'female',
                'date_of_birth' => '1990-05-20',
                'role_id' => $stationsManagerRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create Gate Operators
        $gateOperators = [
            [
                'username' => 'operator1',
                'email' => 'operator1@smartparking.com',
                'phone' => '+1234567892',
                'address' => '789 Operator Lane, City, State 12345',
                'gender' => 'male',
                'date_of_birth' => '1992-08-10',
            ],
            [
                'username' => 'operator2',
                'email' => 'operator2@smartparking.com',
                'phone' => '+1234567893',
                'address' => '321 Operator Road, City, State 12345',
                'gender' => 'female',
                'date_of_birth' => '1988-12-05',
            ],
            [
                'username' => 'operator3',
                'email' => 'operator3@smartparking.com',
                'phone' => '+1234567894',
                'address' => '654 Operator Drive, City, State 12345',
                'gender' => 'male',
                'date_of_birth' => '1995-03-25',
            ],
        ];

        foreach ($gateOperators as $index => $operatorData) {
            $operator = User::updateOrCreate(
                ['email' => $operatorData['email']],
                array_merge($operatorData, [
                    'password' => Hash::make('password123'),
                    'role_id' => $gateOperatorRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ])
            );
        }

        // Assign permissions to users based on their roles
        $this->assignUserPermissions($systemAdmin, $systemAdminRole);
        $this->assignUserPermissions($stationsManager, $stationsManagerRole);

        // Assign permissions to gate operators
        $gateOperatorUsers = User::where('role_id', $gateOperatorRole->id)->get();
        foreach ($gateOperatorUsers as $operator) {
            $this->assignUserPermissions($operator, $gateOperatorRole);
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('Default password for all users: password123');
    }

    /**
     * Assign permissions to user based on their role
     */
    private function assignUserPermissions(User $user, Role $role): void
    {
        // Get all permissions for the role
        $rolePermissions = $role->permissions;

        // Assign permissions to user
        $user->permissions()->sync($rolePermissions->pluck('id'));
    }
}
