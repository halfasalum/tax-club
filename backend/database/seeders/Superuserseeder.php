<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperUserSeeder extends Seeder
{
    /**
     * Creates the default Super Admin user.
     *
     * Credentials:
     *   Email    : superadmin@tra.go.tz
     *   Password : password  (CHANGE THIS IN PRODUCTION)
     *
     * Must run AFTER SuperAdminRoleSeeder (role must exist).
     */
    public function run(): void
    {
        $now   = now();
        $email = 'superadmin@taxclub.go.tz';

        // ── 1. Create or fetch the super user ──────────────────────
        $existing = DB::table('users')->where('email', $email)->first();

        if ($existing) {
            $this->command->warn("⚠️  Super user ({$email}) already exists — skipping creation.");
            $userId = $existing->user_id;
        } else {
            $userId = (string) Str::uuid();

            DB::table('users')->insert([
                'user_id'       => $userId,
                'full_name'     => 'TRA TAX CLUB Super Admin',
                'email'         => $email,
                'phone'         => null,
                'password_hash' => Hash::make('password'), // ⚠️ Change before production
                'user_type'     => 'alumni',
                'institution_id'=> null,
                'membership_id' => 'TRA-SUPERADMIN',
                'is_verified'   => true,
                'created_at'    => $now,
                'last_login'    => null,
            ]);

            $this->command->info("✅ Super user created: {$email}");
        }

        // ── 2. Fetch the super_admin role ──────────────────────────
        $superAdminRole = DB::table('roles')->where('slug', 'super_admin')->first();

        if (! $superAdminRole) {
            $this->command->error('❌ Super Admin role not found. Run SuperAdminRoleSeeder first.');
            return;
        }

        // ── 3. Assign super_admin role to the user ─────────────────
        $alreadyAssigned = DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $superAdminRole->role_id)
            ->exists();

        if (! $alreadyAssigned) {
            DB::table('user_roles')->insert([
                'user_id'     => $userId,
                'role_id'     => $superAdminRole->role_id,
                'assigned_by' => null,  // system assigned
                'assigned_at' => $now,
            ]);

            $this->command->info('✅ Super Admin role assigned to super user.');
        } else {
            $this->command->warn('⚠️  Super Admin role already assigned — skipping.');
        }

        $this->command->newLine();
        $this->command->line('─────────────────────────────────────────');
        $this->command->line('  Super User Credentials');
        $this->command->line('─────────────────────────────────────────');
        $this->command->line("  Email    : {$email}");
        $this->command->line('  Password : password');
        $this->command->line('─────────────────────────────────────────');
        $this->command->warn('  ⚠️  Remember to change the password in production!');
        $this->command->newLine();
    }
}