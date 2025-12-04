<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Station permissions
            ['name' => 'stations.view', 'description' => 'View stations'],
            ['name' => 'stations.create', 'description' => 'Create new stations'],
            ['name' => 'stations.edit', 'description' => 'Edit existing stations'],
            ['name' => 'stations.delete', 'description' => 'Delete stations'],
            ['name' => 'stations.manage', 'description' => 'Full station management'],

            // Gate permissions
            ['name' => 'gates.view', 'description' => 'View gates'],
            ['name' => 'gates.create', 'description' => 'Create new gates'],
            ['name' => 'gates.edit', 'description' => 'Edit existing gates'],
            ['name' => 'gates.delete', 'description' => 'Delete gates'],
            ['name' => 'gates.manage', 'description' => 'Full gate management'],

            // Vehicle permissions
            ['name' => 'vehicles.view', 'description' => 'View vehicles'],
            ['name' => 'vehicles.create', 'description' => 'Create new vehicles'],
            ['name' => 'vehicles.edit', 'description' => 'Edit existing vehicles'],
            ['name' => 'vehicles.delete', 'description' => 'Delete vehicles'],
            ['name' => 'vehicles.manage', 'description' => 'Full vehicle management'],

            // Customer permissions
            ['name' => 'customers.view', 'description' => 'View customers'],
            ['name' => 'customers.create', 'description' => 'Create new customers'],
            ['name' => 'customers.edit', 'description' => 'Edit existing customers'],
            ['name' => 'customers.delete', 'description' => 'Delete customers'],
            ['name' => 'customers.manage', 'description' => 'Full customer management'],

            // Vehicle Body Type permissions
            ['name' => 'vehicle_body_types.view', 'description' => 'View vehicle body types'],
            ['name' => 'vehicle_body_types.create', 'description' => 'Create new vehicle body types'],
            ['name' => 'vehicle_body_types.edit', 'description' => 'Edit existing vehicle body types'],
            ['name' => 'vehicle_body_types.delete', 'description' => 'Delete vehicle body types'],
            ['name' => 'vehicle_body_types.manage', 'description' => 'Full vehicle body type management'],

            // Payment Type permissions
            ['name' => 'payment_types.view', 'description' => 'View payment types'],
            ['name' => 'payment_types.create', 'description' => 'Create new payment types'],
            ['name' => 'payment_types.edit', 'description' => 'Edit existing payment types'],
            ['name' => 'payment_types.delete', 'description' => 'Delete payment types'],
            ['name' => 'payment_types.manage', 'description' => 'Full payment type management'],

            // Bundle Type permissions
            ['name' => 'bundle_types.view', 'description' => 'View bundle types'],
            ['name' => 'bundle_types.create', 'description' => 'Create new bundle types'],
            ['name' => 'bundle_types.edit', 'description' => 'Edit existing bundle types'],
            ['name' => 'bundle_types.delete', 'description' => 'Delete bundle types'],
            ['name' => 'bundle_types.manage', 'description' => 'Full bundle type management'],

            // Vehicle Passage permissions
            ['name' => 'vehicle_passages.view', 'description' => 'View vehicle passages'],
            ['name' => 'vehicle_passages.create', 'description' => 'Create new vehicle passages'],
            ['name' => 'vehicle_passages.edit', 'description' => 'Edit existing vehicle passages'],
            ['name' => 'vehicle_passages.delete', 'description' => 'Delete vehicle passages'],
            ['name' => 'vehicle_passages.manage', 'description' => 'Full vehicle passage management'],

            // Transaction permissions
            ['name' => 'transactions.view', 'description' => 'View transactions'],
            ['name' => 'transactions.create', 'description' => 'Create new transactions'],
            ['name' => 'transactions.edit', 'description' => 'Edit existing transactions'],
            ['name' => 'transactions.delete', 'description' => 'Delete transactions'],
            ['name' => 'transactions.manage', 'description' => 'Full transaction management'],

            // Invoice permissions
            ['name' => 'invoices.view', 'description' => 'View invoices'],
            ['name' => 'invoices.create', 'description' => 'Create new invoices'],
            ['name' => 'invoices.edit', 'description' => 'Edit existing invoices'],
            ['name' => 'invoices.delete', 'description' => 'Delete invoices'],
            ['name' => 'invoices.manage', 'description' => 'Full invoice management'],

            // Receipt permissions
            ['name' => 'receipts.view', 'description' => 'View receipts'],
            ['name' => 'receipts.create', 'description' => 'Create new receipts'],
            ['name' => 'receipts.edit', 'description' => 'Edit existing receipts'],
            ['name' => 'receipts.delete', 'description' => 'Delete receipts'],
            ['name' => 'receipts.manage', 'description' => 'Full receipt management'],

            // Daily Report permissions
            ['name' => 'daily_reports.view', 'description' => 'View daily reports'],
            ['name' => 'daily_reports.create', 'description' => 'Create new daily reports'],
            ['name' => 'daily_reports.edit', 'description' => 'Edit existing daily reports'],
            ['name' => 'daily_reports.delete', 'description' => 'Delete daily reports'],
            ['name' => 'daily_reports.manage', 'description' => 'Full daily report management'],

            // System Setting permissions
            ['name' => 'system_settings.view', 'description' => 'View system settings'],
            ['name' => 'system_settings.create', 'description' => 'Create new system settings'],
            ['name' => 'system_settings.edit', 'description' => 'Edit existing system settings'],
            ['name' => 'system_settings.delete', 'description' => 'Delete system settings'],
            ['name' => 'system_settings.manage', 'description' => 'Full system setting management'],

            // User permissions
            ['name' => 'users.view', 'description' => 'View users'],
            ['name' => 'users.create', 'description' => 'Create new users'],
            ['name' => 'users.edit', 'description' => 'Edit existing users'],
            ['name' => 'users.delete', 'description' => 'Delete users'],
            ['name' => 'users.manage', 'description' => 'Full user management'],

            // Role permissions
            ['name' => 'roles.view', 'description' => 'View roles'],
            ['name' => 'roles.create', 'description' => 'Create new roles'],
            ['name' => 'roles.edit', 'description' => 'Edit existing roles'],
            ['name' => 'roles.delete', 'description' => 'Delete roles'],
            ['name' => 'roles.manage', 'description' => 'Full role management'],

            // Permission permissions
            ['name' => 'permissions.view', 'description' => 'View permissions'],
            ['name' => 'permissions.create', 'description' => 'Create new permissions'],
            ['name' => 'permissions.edit', 'description' => 'Edit existing permissions'],
            ['name' => 'permissions.delete', 'description' => 'Delete permissions'],
            ['name' => 'permissions.manage', 'description' => 'Full permission management'],

            // Account permissions
            ['name' => 'accounts.view', 'description' => 'View accounts'],
            ['name' => 'accounts.create', 'description' => 'Create new accounts'],
            ['name' => 'accounts.edit', 'description' => 'Edit existing accounts'],
            ['name' => 'accounts.delete', 'description' => 'Delete accounts'],
            ['name' => 'accounts.manage', 'description' => 'Full account management'],

            // Bundle permissions
            ['name' => 'bundles.view', 'description' => 'View bundles'],
            ['name' => 'bundles.create', 'description' => 'Create new bundles'],
            ['name' => 'bundles.edit', 'description' => 'Edit existing bundles'],
            ['name' => 'bundles.delete', 'description' => 'Delete bundles'],
            ['name' => 'bundles.manage', 'description' => 'Full bundle management'],

            // Bundle Subscription permissions
            ['name' => 'bundle_subscriptions.view', 'description' => 'View bundle subscriptions'],
            ['name' => 'bundle_subscriptions.create', 'description' => 'Create new bundle subscriptions'],
            ['name' => 'bundle_subscriptions.edit', 'description' => 'Edit existing bundle subscriptions'],
            ['name' => 'bundle_subscriptions.delete', 'description' => 'Delete bundle subscriptions'],
            ['name' => 'bundle_subscriptions.manage', 'description' => 'Full bundle subscription management'],

            // Vehicle Body Type Price permissions
            ['name' => 'vehicle_body_type_prices.view', 'description' => 'View vehicle body type prices'],
            ['name' => 'vehicle_body_type_prices.create', 'description' => 'Create new vehicle body type prices'],
            ['name' => 'vehicle_body_type_prices.edit', 'description' => 'Edit existing vehicle body type prices'],
            ['name' => 'vehicle_body_type_prices.delete', 'description' => 'Delete vehicle body type prices'],
            ['name' => 'vehicle_body_type_prices.manage', 'description' => 'Full vehicle body type price management'],

            // Account Vehicle permissions
            ['name' => 'account_vehicles.view', 'description' => 'View account vehicles'],
            ['name' => 'account_vehicles.create', 'description' => 'Create new account vehicles'],
            ['name' => 'account_vehicles.edit', 'description' => 'Edit existing account vehicles'],
            ['name' => 'account_vehicles.delete', 'description' => 'Delete account vehicles'],
            ['name' => 'account_vehicles.manage', 'description' => 'Full account vehicle management'],

            // Bundle Vehicle permissions
            ['name' => 'bundle_vehicles.view', 'description' => 'View bundle vehicles'],
            ['name' => 'bundle_vehicles.create', 'description' => 'Create new bundle vehicles'],
            ['name' => 'bundle_vehicles.edit', 'description' => 'Edit existing bundle vehicles'],
            ['name' => 'bundle_vehicles.delete', 'description' => 'Delete bundle vehicles'],
            ['name' => 'bundle_vehicles.manage', 'description' => 'Full bundle vehicle management'],

            // Dashboard and Analytics permissions
            ['name' => 'dashboard.view', 'description' => 'View dashboard'],
            ['name' => 'analytics.view', 'description' => 'View analytics and reports'],
            ['name' => 'reports.generate', 'description' => 'Generate reports'],
            ['name' => 'statistics.view', 'description' => 'View statistics'],

            // System permissions
            ['name' => 'system.admin', 'description' => 'Full system administration access'],
            ['name' => 'system.backup', 'description' => 'System backup and restore'],
            ['name' => 'system.logs', 'description' => 'View system logs'],
            ['name' => 'system.maintenance', 'description' => 'System maintenance mode'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'guard' => 'web',
                    'description' => $permission['description'],
                ]
            );
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
