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

            // Institution Settings
            //
            // Two, not four. The settings are a single global record with
            // no create and no delete, so "view" and "update" are the only
            // two things anybody can do to them.
            'settings.view',
            'settings.update',
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
            // The institution's own details are the administrator's to
            // keep. Nobody below Admin gets either half.
            'settings.view',
            'settings.update',
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
