<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get roles
        $systemAdmin = Role::where('name', 'System Administrator')->first();
        $stationsManager = Role::where('name', 'Stations Manager')->first();
        $gateOperator = Role::where('name', 'Gate Operator')->first();

        // Get permissions
        $permissions = Permission::all()->keyBy('name');

        // System Administrator - All permissions
        if ($systemAdmin) {
            $systemAdmin->permissions()->sync($permissions->pluck('id'));
        }

        // Stations Manager permissions
        if ($stationsManager) {
            $stationsManagerPermissions = [
                // Station management
                'stations.view',
                'stations.create',
                'stations.edit',
                'stations.delete',
                'stations.manage',

                // Gate management
                'gates.view',
                'gates.create',
                'gates.edit',
                'gates.delete',
                'gates.manage',

                // Vehicle management
                'vehicles.view',
                'vehicles.create',
                'vehicles.edit',
                'vehicles.delete',
                'vehicles.manage',

                // Customer management
                'customers.view',
                'customers.create',
                'customers.edit',
                'customers.delete',
                'customers.manage',

                // Vehicle body type management
                'vehicle_body_types.view',
                'vehicle_body_types.create',
                'vehicle_body_types.edit',
                'vehicle_body_types.delete',
                'vehicle_body_types.manage',

                // Payment type management
                'payment_types.view',
                'payment_types.create',
                'payment_types.edit',
                'payment_types.delete',
                'payment_types.manage',

                // Bundle type management
                'bundle_types.view',
                'bundle_types.create',
                'bundle_types.edit',
                'bundle_types.delete',
                'bundle_types.manage',

                // Vehicle passage management
                'vehicle_passages.view',
                'vehicle_passages.create',
                'vehicle_passages.edit',
                'vehicle_passages.delete',
                'vehicle_passages.manage',

                // Transaction management
                'transactions.view',
                'transactions.create',
                'transactions.edit',
                'transactions.delete',
                'transactions.manage',

                // Invoice management
                'invoices.view',
                'invoices.create',
                'invoices.edit',
                'invoices.delete',
                'invoices.manage',

                // Receipt management
                'receipts.view',
                'receipts.create',
                'receipts.edit',
                'receipts.delete',
                'receipts.manage',

                // Daily report management
                'daily_reports.view',
                'daily_reports.create',
                'daily_reports.edit',
                'daily_reports.delete',
                'daily_reports.manage',

                // Account management
                'accounts.view',
                'accounts.create',
                'accounts.edit',
                'accounts.delete',
                'accounts.manage',

                // Bundle management
                'bundles.view',
                'bundles.create',
                'bundles.edit',
                'bundles.delete',
                'bundles.manage',

                // Bundle subscription management
                'bundle_subscriptions.view',
                'bundle_subscriptions.create',
                'bundle_subscriptions.edit',
                'bundle_subscriptions.delete',
                'bundle_subscriptions.manage',

                // Vehicle body type price management
                'vehicle_body_type_prices.view',
                'vehicle_body_type_prices.create',
                'vehicle_body_type_prices.edit',
                'vehicle_body_type_prices.delete',
                'vehicle_body_type_prices.manage',

                // Account vehicle management
                'account_vehicles.view',
                'account_vehicles.create',
                'account_vehicles.edit',
                'account_vehicles.delete',
                'account_vehicles.manage',

                // Bundle vehicle management
                'bundle_vehicles.view',
                'bundle_vehicles.create',
                'bundle_vehicles.edit',
                'bundle_vehicles.delete',
                'bundle_vehicles.manage',

                // Dashboard and analytics
                'dashboard.view',
                'analytics.view',
                'reports.generate',
                'statistics.view',

                // Limited user management (view only)
                'users.view',

                // Limited system settings (view and edit only)
                'system_settings.view',
                'system_settings.edit',
            ];

            $stationsManagerPermissionIds = $permissions->whereIn('name', $stationsManagerPermissions)->pluck('id');
            $stationsManager->permissions()->sync($stationsManagerPermissionIds);
        }

        // Gate Operator permissions
        if ($gateOperator) {
            $gateOperatorPermissions = [
                // View stations and gates
                'stations.view',
                'gates.view',

                // Vehicle passage operations
                'vehicle_passages.view',
                'vehicle_passages.create',
                'vehicle_passages.edit',

                // Transaction operations
                'transactions.view',
                'transactions.create',
                'transactions.edit',

                // Receipt operations
                'receipts.view',
                'receipts.create',
                'receipts.edit',

                // Vehicle operations
                'vehicles.view',
                'vehicles.create',
                'vehicles.edit',

                // Customer operations
                'customers.view',
                'customers.create',
                'customers.edit',

                // Payment type view
                'payment_types.view',

                // Vehicle body type view
                'vehicle_body_types.view',

                // Bundle type view
                'bundle_types.view',

                // Account view
                'accounts.view',

                // Bundle view
                'bundles.view',

                // Bundle subscription view
                'bundle_subscriptions.view',

                // Account vehicle view
                'account_vehicles.view',

                // Bundle vehicle view
                'bundle_vehicles.view',

                // Dashboard view
                'dashboard.view',

                // Statistics view
                'statistics.view',
            ];

            $gateOperatorPermissionIds = $permissions->whereIn('name', $gateOperatorPermissions)->pluck('id');
            $gateOperator->permissions()->sync($gateOperatorPermissionIds);
        }

        $this->command->info('Role permissions assigned successfully!');
    }
}
