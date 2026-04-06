<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperAdminRoleSeeder extends Seeder
{
    /**
     * Creates the Super Admin role and grants it every single
     * module control in the system.
     *
     * Must run AFTER ModuleSeeder (controls must exist first).
     */
    public function run(): void
    {
        $now = now();

        // ── 1. Create Super Admin role ─────────────────────────────
        $existing = DB::table('roles')->where('slug', 'super_admin')->first();

        if (! $existing) {
            $roleId = (string) Str::uuid();

            DB::table('roles')->insert([
                'role_id'     => $roleId,
                'name'        => 'Super Admin',
                'slug'        => 'super_admin',
                'description' => 'Full unrestricted access to all modules and controls.',
                'is_default'  => false, // never auto-assign super admin to new registrations
                'is_active'   => true,
                'created_by'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } else {
            $roleId = $existing->role_id;
            $this->command->warn('⚠️  Super Admin role already exists — skipping creation, re-syncing controls.');
        }

        // ── 2. Create Student role (default for new registrations) ─
        $studentExists = DB::table('roles')->where('slug', 'student')->first();

        if (! $studentExists) {
            DB::table('roles')->insert([
                'role_id'     => (string) Str::uuid(),
                'name'        => 'Student',
                'slug'        => 'student',
                'description' => 'Default role for all newly registered students. Read-only access to learning content.',
                'is_default'  => true,  // auto-assigned on registration
                'is_active'   => true,
                'created_by'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $this->command->info('✅ Student (default) role seeded.');
        } else {
            $this->command->warn('⚠️  Student role already exists — skipping.');
        }

        // ── 3. Grant ALL controls to Super Admin ───────────────────
        $allControls = DB::table('module_controls')->get();

        if ($allControls->isEmpty()) {
            $this->command->error('❌ No module controls found. Run ModuleSeeder first.');
            return;
        }

        foreach ($allControls as $control) {
            DB::table('role_controls')->insertOrIgnore([
                'role_id'    => $roleId,
                'control_id' => $control->control_id,
                'granted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $count = $allControls->count();
        $this->command->info("✅ Super Admin role granted {$count} controls.");
    }
}