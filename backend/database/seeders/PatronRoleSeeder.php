<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatronRoleSeeder extends Seeder
{
    /**
     * The Patron role: confirms registrations for the patron's own
     * institution only (INS-REQ05, REG-REQ02). Must run after ModuleSeeder.
     */
    public function run(): void
    {
        $now = now();
        $role = DB::table('roles')->where('slug', 'patron')->first();

        if (! $role) {
            $roleId = (string) Str::uuid();

            DB::table('roles')->insert([
                'role_id' => $roleId,
                'name' => 'Patron',
                'slug' => 'patron',
                'description' => 'Tax Club patron: confirms student registrations for their own institution.',
                'is_default' => false,
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $roleId = $role->role_id;
        }

        $controls = DB::table('module_controls')->whereIn('slug', ['view_registrations', 'verify_registration'])->pluck('control_id');

        foreach ($controls as $controlId) {
            DB::table('role_controls')->insertOrIgnore([
                'role_id' => $roleId,
                'control_id' => $controlId,
                'granted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info('✅ Patron role seeded.');
    }
}
