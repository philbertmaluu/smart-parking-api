<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'System Administrator',
                'description' => 'Full system access with all permissions. Can manage users, roles, permissions, and all system settings.',
                'level' => 1,
                'is_default' => false,
            ],
            [
                'name' => 'Stations Manager',
                'description' => 'Manages stations, gates, and operational aspects. Can view reports and manage station-level settings.',
                'level' => 2,
                'is_default' => false,
            ],
            [
                'name' => 'Gate Operator',
                'description' => 'Operates gates, processes vehicle passages, and handles basic transactions. Limited to operational tasks.',
                'level' => 3,
                'is_default' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                [
                    'description' => $role['description'],
                    'level' => $role['level'],
                    'is_default' => $role['is_default'],
                ]
            );
        }

        $this->command->info('Roles seeded successfully!');
    }
}
