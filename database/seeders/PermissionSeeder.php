<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Dashboard
            'dashboard.view',

            // User Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Role Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Permission Management
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $superAdmin = Role::findByName('Super Admin');
        $admin = Role::findByName('Admin');
        $principal = Role::findByName('Principal');
        $teacher = Role::findByName('Teacher');
        $accounts = Role::findByName('Accounts');
        $parent = Role::findByName('Parent');

        // Super Admin gets all permissions
        $superAdmin->givePermissionTo(Permission::all());

        // Admin permissions
        $admin->givePermissionTo([
            'dashboard.view',
            'users.view',
            'users.create',
            'users.edit',
        ]);

        // Principal permissions
        $principal->givePermissionTo([
            'dashboard.view',
        ]);

        // Teacher permissions
        $teacher->givePermissionTo([
            'dashboard.view',
        ]);

        // Accounts permissions
        $accounts->givePermissionTo([
            'dashboard.view',
        ]);

        // Parent permissions
        $parent->givePermissionTo([
            'dashboard.view',
        ]);
    }
}
