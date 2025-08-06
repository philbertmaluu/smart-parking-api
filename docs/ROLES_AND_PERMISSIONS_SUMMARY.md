# Smart Parking System - Roles and Permissions Summary

## Overview
This document summarizes the roles, permissions, and seeders created for the Smart Parking System.

## Roles Created

### 1. System Administrator
- **Level**: 1 (Highest)
- **Description**: Full system access with all permissions. Can manage users, roles, permissions, and all system settings.
- **Permissions**: 118 (All permissions)
- **Default User**: admin@smartparking.com

### 2. Stations Manager
- **Level**: 2 (Medium)
- **Description**: Manages stations, gates, and operational aspects. Can view reports and manage station-level settings.
- **Permissions**: 97 (Limited administrative permissions)
- **Default User**: manager@smartparking.com

### 3. Gate Operator
- **Level**: 3 (Lowest)
- **Description**: Operates gates, processes vehicle passages, and handles basic transactions. Limited to operational tasks.
- **Permissions**: 27 (Operational permissions only)
- **Default Users**: 
  - operator1@smartparking.com
  - operator2@smartparking.com
  - operator3@smartparking.com

## Permissions Structure

### CRUD Permissions for Each Entity
Each entity has 5 basic permissions:
- `entity.view` - View records
- `entity.create` - Create new records
- `entity.edit` - Edit existing records
- `entity.delete` - Delete records
- `entity.manage` - Full management (includes all CRUD operations)

### Entities Covered
1. **Stations** - Toll station management
2. **Gates** - Gate management
3. **Vehicles** - Vehicle registration and management
4. **Customers** - Customer management
5. **Vehicle Body Types** - Vehicle classification
6. **Payment Types** - Payment method management
7. **Bundle Types** - Subscription bundle management
8. **Vehicle Passages** - Entry/exit records
9. **Transactions** - Payment transactions
10. **Invoices** - Invoice management
11. **Receipts** - Receipt generation
12. **Daily Reports** - Daily operational reports
13. **System Settings** - System configuration
14. **Users** - User management
15. **Roles** - Role management
16. **Permissions** - Permission management
17. **Accounts** - Customer accounts
18. **Bundles** - Bundle management
19. **Bundle Subscriptions** - Subscription management
20. **Vehicle Body Type Prices** - Pricing management
21. **Account Vehicles** - Vehicle-account associations
22. **Bundle Vehicles** - Vehicle-bundle associations

### Special Permissions
- **Dashboard**: `dashboard.view`
- **Analytics**: `analytics.view`, `reports.generate`, `statistics.view`
- **System**: `system.admin`, `system.backup`, `system.logs`, `system.maintenance`

## Seeders Created

### 1. PermissionSeeder
- Creates 118 permissions covering all CRUD operations
- Uses `updateOrCreate` to prevent duplicates
- Includes descriptions for each permission

### 2. RoleSeeder
- Creates 3 main roles with appropriate levels
- Sets Gate Operator as default role
- Includes detailed descriptions

### 3. RolePermissionSeeder
- Assigns permissions to roles based on responsibilities
- System Administrator: All permissions (118)
- Stations Manager: Operational + limited admin (97)
- Gate Operator: Operational only (27)

### 4. UserSeeder
- Creates sample users for each role
- System Administrator: 1 user
- Stations Manager: 1 user
- Gate Operator: 3 users
- All users have password: `password123`

### 5. VehicleBodyTypeSeeder
- Creates 8 common vehicle body types
- Motorcycle, Sedan, SUV, Truck, Bus, Van, Pickup, Trailer

### 6. PaymentTypeSeeder
- Creates 8 payment types
- Cash, Credit Card, Debit Card, Mobile Money, Bank Transfer, Account Balance, Bundle Subscription, Exempted

## Database Structure

### Tables Created
1. **roles** - Role definitions
2. **permissions** - Permission definitions
3. **role_has_permissions** - Role-permission relationships
4. **user_has_permissions** - User-permission relationships (existing)

### Relationships
- Role ↔ Permission (Many-to-Many via role_has_permissions)
- User ↔ Permission (Many-to-Many via user_has_permissions)
- User → Role (Many-to-One)

## Usage

### Running Seeders
```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder
```

### Default Login Credentials
- **System Administrator**: admin@smartparking.com / password123
- **Stations Manager**: manager@smartparking.com / password123
- **Gate Operators**: 
  - operator1@smartparking.com / password123
  - operator2@smartparking.com / password123
  - operator3@smartparking.com / password123

## Permission Checking

### In Controllers
```php
// Check if user has specific permission
if ($user->hasPermission('stations.create')) {
    // Allow station creation
}

// Check if user has specific role
if ($user->hasRole('System Administrator')) {
    // Allow admin operations
}
```

### In Blade Templates
```php
@if(auth()->user()->hasPermission('stations.create'))
    <button>Create Station</button>
@endif

@if(auth()->user()->hasRole('System Administrator'))
    <div>Admin Panel</div>
@endif
```

## Security Features

1. **Role-based Access Control (RBAC)**: Users are assigned roles with predefined permissions
2. **Permission-based Access Control**: Fine-grained control over specific actions
3. **Hierarchical Roles**: Role levels determine access hierarchy
4. **Audit Trail**: Activity logging for all permission changes
5. **Soft Deletes**: Prevents accidental data loss

## Maintenance

### Adding New Permissions
1. Add permission to `PermissionSeeder`
2. Run `php artisan db:seed --class=PermissionSeeder`
3. Assign to appropriate roles in `RolePermissionSeeder`
4. Run `php artisan db:seed --class=RolePermissionSeeder`

### Adding New Roles
1. Add role to `RoleSeeder`
2. Define permissions in `RolePermissionSeeder`
3. Run both seeders

### Adding New Users
1. Add user to `UserSeeder` or create via application
2. Assign appropriate role
3. Permissions will be automatically assigned based on role 
