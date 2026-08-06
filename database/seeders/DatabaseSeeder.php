<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed users, roles and permissions
        $this->call([
            UserSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);

        // Assign Super Admin role to admin@jzcms.edu.pk
        $adminUser = User::where('email', 'admin@jzcms.edu.pk')->first();

        if ($adminUser) {
            $adminUser->assignRole('Super Admin');
        }
    }
}
