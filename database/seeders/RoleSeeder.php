<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $buyerRequester = Role::firstOrCreate(['name' => 'buyer_requester']);
        $supplierProcessor = Role::firstOrCreate(['name' => 'supplier_processor']);
        $supplierApprover = Role::firstOrCreate(['name' => 'supplier_approver']);

        // Create permissions
        Permission::firstOrCreate(['name' => 'view catalog']);
        Permission::firstOrCreate(['name' => 'create order']);
        Permission::firstOrCreate(['name' => 'confirm receipt']);
        Permission::firstOrCreate(['name' => 'process orders']);
        Permission::firstOrCreate(['name' => 'approve internal order']);
        Permission::firstOrCreate(['name' => 'manage products']);

        // Assign permissions to roles
        $admin->syncPermissions([
            'view catalog',
            'create order',
            'confirm receipt',
            'process orders',
            'approve internal order',
            'manage products'
        ]);

        $buyerRequester->syncPermissions([
            'view catalog',
            'create order',
            'confirm receipt'
        ]);

        $supplierProcessor->syncPermissions([
            'process orders',
            'manage products'
        ]);

        $supplierApprover->syncPermissions([
            'process orders',
            'approve internal order',
            'manage products'
        ]);
    }
}
