<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatronRoleSeeder extends Seeder
{
    /**
     * Programme roles. Must run after ModuleSeeder; safe to re-run, it only
     * adds missing permissions.
     *
     *  - Patron: looks after one Tax Club; sees its own institution only
     *    (INS-REQ05, REG-REQ02).
     *  - Coordinator: TRA officer for one or more regions; sees every
     *    institution in those regions (INS-REQ06, decision D-09).
     */
    public const ROLES = [
        'patron' => [
            'name' => 'Patron',
            'description' => 'Tax Club patron: confirms registrations and looks after members of their own institution.',
            'controls' => ['view_registrations', 'verify_registration', 'view_members', 'manage_alumni'],
        ],
        'coordinator' => [
            'name' => 'Coordinator',
            'description' => 'TRA Tax Club coordinator: oversees institutions, clubs and members in assigned regions.',
            'controls' => [
                'view_registrations', 'verify_registration', 'view_institutions', 'view_members',
                'change_member_status', 'manage_alumni', 'verify_membership', 'view_statistics',
            ],
        ],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::ROLES as $slug => $definition) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('role_id');

            if (! $roleId) {
                $roleId = (string) Str::uuid();

                DB::table('roles')->insert([
                    'role_id' => $roleId,
                    'name' => $definition['name'],
                    'slug' => $slug,
                    'description' => $definition['description'],
                    'is_default' => false,
                    'is_active' => true,
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (DB::table('module_controls')->whereIn('slug', $definition['controls'])->pluck('control_id') as $controlId) {
                DB::table('role_controls')->insertOrIgnore([
                    'role_id' => $roleId,
                    'control_id' => $controlId,
                    'granted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command?->info('✅ Patron and Coordinator roles seeded.');
    }
}
