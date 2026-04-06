<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * ORDER MATTERS — each seeder depends on the one above it:
     *
     *   1. ModuleSeeder          → creates modules + module_controls
     *   2. SuperAdminRoleSeeder  → creates super_admin & student roles,
     *                              grants ALL controls to super_admin
     *   3. SuperUserSeeder       → creates the superuser account,
     *                              assigns super_admin role
     *
     * Run with:
     *   php artisan db:seed
     *
     * Or individually:
     *   php artisan db:seed --class=ModuleSeeder
     *   php artisan db:seed --class=SuperAdminRoleSeeder
     *   php artisan db:seed --class=SuperUserSeeder
     *
     * Fresh migrate + seed:
     *   php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🌱 Starting TRA Tax Club database seeding...');
        $this->command->info('');

        $this->call([
            ModuleSeeder::class,
            SuperAdminRoleSeeder::class,
            SuperUserSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('🎉 All seeders completed successfully!');
        $this->command->info('');
    }
}