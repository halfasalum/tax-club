<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleSeeder extends Seeder
{
    /**
     * All system modules and their controls.
     * Controls slugs are used directly in:
     *   - route middleware: ->middleware('permission:add_user')
     *   - User::hasControl('add_user')
     */
    private array $modules = [
        [
            'name'        => 'Users',
            'description' => 'Manage system users and their accounts.',
            'controls'    => [
                ['name' => 'View Users',   'description' => 'View and list all users.'],
                ['name' => 'Add User',     'description' => 'Create a new user manually.'],
                ['name' => 'Edit User',    'description' => 'Update user profile information.'],
                ['name' => 'Delete User',  'description' => 'Delete a user account.'],
                ['name' => 'Assign Role',  'description' => 'Assign or remove roles from a user.'],
            ],
        ],
        [
            'name'        => 'Roles',
            'description' => 'Manage roles and their assigned permissions.',
            'controls'    => [
                ['name' => 'View Roles',   'description' => 'View and list all roles.'],
                ['name' => 'Add Role',     'description' => 'Create a new role.'],
                ['name' => 'Edit Role',    'description' => 'Update a role and its controls.'],
                ['name' => 'Delete Role',  'description' => 'Delete a role from the system.'],
            ],
        ],
        [
            'name'        => 'Modules',
            'description' => 'Manage system modules and their access controls.',
            'controls'    => [
                ['name' => 'View Modules',   'description' => 'View and list all modules.'],
                ['name' => 'Add Module',     'description' => 'Create a new module.'],
                ['name' => 'Edit Module',    'description' => 'Update a module or its controls.'],
                ['name' => 'Delete Module',  'description' => 'Delete a module from the system.'],
            ],
        ],
        [
            'name'        => 'Institutions',
            'description' => 'Manage registered schools, colleges and universities.',
            'controls'    => [
                ['name' => 'Add Institution',    'description' => 'Register a new institution.'],
                ['name' => 'Edit Institution',   'description' => 'Update institution details.'],
                ['name' => 'Delete Institution', 'description' => 'Remove an institution.'],
            ],
        ],
        [
            'name'        => 'Content',
            'description' => 'Manage learning content — videos, articles, infographics.',
            'controls'    => [
                ['name' => 'Upload Content', 'description' => 'Upload or create new content.'],
                ['name' => 'Edit Content',   'description' => 'Update existing content.'],
                ['name' => 'Delete Content', 'description' => 'Remove content from the system.'],
            ],
        ],
        [
            'name'        => 'Quizzes',
            'description' => 'Manage quizzes and view attempt results.',
            'controls'    => [
                ['name' => 'Create Quiz',  'description' => 'Create a new quiz.'],
                ['name' => 'Edit Quiz',    'description' => 'Update quiz details.'],
                ['name' => 'Delete Quiz',  'description' => 'Delete a quiz.'],
                ['name' => 'View Results', 'description' => 'View all user quiz attempts and scores.'],
            ],
        ],
        [
            'name'        => 'Opportunities',
            'description' => 'Manage job listings, internships, and volunteer opportunities.',
            'controls'    => [
                ['name' => 'Add Opportunity',    'description' => 'Post a new opportunity listing.'],
                ['name' => 'Edit Opportunity',   'description' => 'Update an opportunity listing.'],
                ['name' => 'Delete Opportunity', 'description' => 'Remove an opportunity listing.'],
            ],
        ],
        [
            'name'        => 'Forum',
            'description' => 'Manage community forum posts and discussions.',
            'controls'    => [
                ['name' => 'Create Forum Post',  'description' => 'Start a new forum discussion thread.'],
                ['name' => 'Delete Forum Post',  'description' => 'Remove any forum post or reply.'],
            ],
        ],
        [
            'name'        => 'Events',
            'description' => 'Manage TRA Tax Club events and registrations.',
            'controls'    => [
                ['name' => 'Add Event',              'description' => 'Create a new event.'],
                ['name' => 'Edit Event',             'description' => 'Update event details.'],
                ['name' => 'Delete Event',           'description' => 'Delete an event.'],
                ['name' => 'View Event Attendees',   'description' => 'View registered attendees for an event.'],
            ],
        ],
    ];

    public function run(): void
    {
        $now = now();

        foreach ($this->modules as $moduleData) {
            $moduleId = (string) Str::uuid();

            DB::table('modules')->insertOrIgnore([
                'module_id'   => $moduleId,
                'name'        => $moduleData['name'],
                'slug'        => Str::slug($moduleData['name'], '_'),
                'description' => $moduleData['description'],
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            // Re-fetch in case insertOrIgnore skipped (already exists)
            $module = DB::table('modules')->where('name', $moduleData['name'])->first();

            foreach ($moduleData['controls'] as $control) {
                $slug = Str::slug($control['name'], '_');

                DB::table('module_controls')->insertOrIgnore([
                    'control_id'  => (string) Str::uuid(),
                    'name'        => $control['name'],
                    'slug'        => $slug,
                    'module_id'   => $module->module_id,
                    'description' => $control['description'],
                    'is_active'   => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        $this->command->info('✅ Modules and controls seeded successfully.');
    }
}