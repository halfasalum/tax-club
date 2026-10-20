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
            'name' => 'Registrations',
            'description' => 'Verify membership registrations submitted by students.',
            'controls' => [
                ['name' => 'View Registrations',  'description' => 'View the registration verification queue.'],
                ['name' => 'Verify Registration', 'description' => 'Approve or reject a submitted registration.'],
                ['name' => 'View All Registrations', 'description' => 'Nationwide access: registrations, members and institutions in every region, not only those the user looks after as patron or coordinator.'],
                ['name' => 'Verify Membership', 'description' => 'See full member details when checking a membership card.'],
            ],
        ],
        [
            'name' => 'Users',
            'description' => 'Manage system users and their accounts.',
            'controls' => [
                ['name' => 'View Users',   'description' => 'View and list all users.'],
                ['name' => 'Add User',     'description' => 'Create a new user manually.'],
                ['name' => 'Edit User',    'description' => 'Update user profile information.'],
                ['name' => 'Delete User',  'description' => 'Delete a user account.'],
                ['name' => 'Assign Role',  'description' => 'Assign or remove roles from a user.'],
            ],
        ],
        [
            'name' => 'Roles',
            'description' => 'Manage roles and their assigned permissions.',
            'controls' => [
                ['name' => 'View Roles',   'description' => 'View and list all roles.'],
                ['name' => 'Add Role',     'description' => 'Create a new role.'],
                ['name' => 'Edit Role',    'description' => 'Update a role and its controls.'],
                ['name' => 'Delete Role',  'description' => 'Delete a role from the system.'],
            ],
        ],
        [
            'name' => 'Modules',
            'description' => 'Manage system modules and their access controls.',
            'controls' => [
                ['name' => 'View Modules',   'description' => 'View and list all modules.'],
                ['name' => 'Add Module',     'description' => 'Create a new module.'],
                ['name' => 'Edit Module',    'description' => 'Update a module or its controls.'],
                ['name' => 'Delete Module',  'description' => 'Delete a module from the system.'],
            ],
        ],
        [
            'name' => 'Institutions',
            'description' => 'Manage registered schools, colleges and universities.',
            'controls' => [
                ['name' => 'Add Institution',    'description' => 'Register a new institution.'],
                ['name' => 'Edit Institution',   'description' => 'Update institution details.'],
                ['name' => 'Delete Institution', 'description' => 'Remove an institution.'],
                ['name' => 'View Institutions', 'description' => 'Search and view institutions and their Tax Clubs.'],
                ['name' => 'Change Institution Status', 'description' => 'Activate or deactivate an institution.'],
                ['name' => 'Manage Tax Clubs', 'description' => 'Establish Tax Clubs and change their status.'],
                ['name' => 'Assign Patron', 'description' => 'Assign or end Tax Club patrons.'],
                ['name' => 'Import Institutions', 'description' => 'Import institutions from a CSV file.'],
                ['name' => 'Assign Coordinator', 'description' => 'Assign or end TRA Tax Club coordinators for regions.'],
                ['name' => 'View Statistics', 'description' => 'See membership statistics and download reports.'],
            ],
        ],
        [
            'name' => 'Members',
            'description' => 'Look after member accounts and their membership status.',
            'controls' => [
                ['name' => 'View Members', 'description' => 'Search and view members within the user\'s scope.'],
                ['name' => 'Change Member Status', 'description' => 'Suspend, deactivate or reactivate member accounts.'],
                ['name' => 'Manage Alumni', 'description' => 'Approve alumni requests and move graduating members to alumni.'],
            ],
        ],
        [
            'name' => 'Notifications',
            'description' => 'Configure notifications and follow SMS delivery.',
            'controls' => [
                ['name' => 'Manage Notification Templates', 'description' => 'Edit message wording and turn channels on or off.'],
                ['name' => 'View SMS Log', 'description' => 'See SMS delivery status and retry failed messages.'],
                ['name' => 'Create Broadcast', 'description' => 'Write targeted or broadcast notifications and send them for approval.'],
                ['name' => 'Approve Broadcast', 'description' => 'Approve, schedule or send broadcast notifications.'],
                ['name' => 'Manage Reminders', 'description' => 'Turn automatic reminders on or off and set their timing.'],
            ],
        ],
        [
            'name' => 'Gamification',
            'description' => 'Points rules, badges and leaderboards.',
            'controls' => [
                ['name' => 'Manage Gamification', 'description' => 'Change point values, badges and leaderboard settings.'],
                ['name' => 'Adjust Points', 'description' => 'Add or remove a member\'s points with a reason.'],
            ],
        ],
        [
            'name' => 'Feedback',
            'description' => 'Feedback, suggestions and concerns from members.',
            'controls' => [
                ['name' => 'View Feedback', 'description' => 'See submitted feedback and its history.'],
                ['name' => 'Manage Feedback', 'description' => 'Classify, prioritise and assign feedback to officers.'],
                ['name' => 'Respond To Feedback', 'description' => 'Reply to feedback, record actions and close it.'],
                ['name' => 'Handle Safeguarding Concerns', 'description' => 'See and handle feedback marked as a safety or safeguarding concern.'],
            ],
        ],
        [
            'name' => 'Audit',
            'description' => 'Review the record of changes and sign-ins.',
            'controls' => [
                ['name' => 'View Audit Log', 'description' => 'See who changed what, and sign-in activity.'],
            ],
        ],
        [
            'name' => 'Content',
            'description' => 'Manage learning content — videos, articles, infographics.',
            'controls' => [
                ['name' => 'Upload Content', 'description' => 'Upload or create new content.'],
                ['name' => 'Edit Content',   'description' => 'Update existing content.'],
                ['name' => 'Delete Content', 'description' => 'Remove content from the system.'],
                ['name' => 'Review Content', 'description' => 'Approve, return or reject submitted learning content.'],
                ['name' => 'Publish Content', 'description' => 'Publish, schedule, unpublish and archive approved content and modules.'],
                ['name' => 'Manage Content Categories', 'description' => 'Create and edit learning categories.'],
            ],
        ],
        [
            'name' => 'Quizzes',
            'description' => 'Manage quizzes and view attempt results.',
            'controls' => [
                ['name' => 'Create Quiz',  'description' => 'Create a new quiz.'],
                ['name' => 'Edit Quiz',    'description' => 'Update quiz details.'],
                ['name' => 'Delete Quiz',  'description' => 'Delete a quiz.'],
                ['name' => 'View Results', 'description' => 'View all user quiz attempts and scores.'],
                ['name' => 'Review Quiz', 'description' => 'Approve, return or reject submitted quizzes.'],
                ['name' => 'Publish Quiz', 'description' => 'Publish, schedule and close approved quizzes.'],
            ],
        ],
        [
            'name' => 'Opportunities',
            'description' => 'Manage job listings, internships, and volunteer opportunities.',
            'controls' => [
                ['name' => 'Add Opportunity',    'description' => 'Post a new opportunity listing.'],
                ['name' => 'Edit Opportunity',   'description' => 'Update an opportunity listing.'],
                ['name' => 'Delete Opportunity', 'description' => 'Remove an opportunity listing.'],
                ['name' => 'Review Opportunity', 'description' => 'Approve, return or reject submitted opportunities.'],
                ['name' => 'Publish Opportunity', 'description' => 'Publish, schedule, close, withdraw and archive approved opportunities.'],
                ['name' => 'View Opportunity Reports', 'description' => 'See engagement with opportunities and career resources.'],
            ],
        ],
        [
            'name' => 'Forum',
            'description' => 'Manage community forum posts and discussions.',
            'controls' => [
                ['name' => 'Create Forum Post',  'description' => 'Start a new forum discussion thread.'],
                ['name' => 'Delete Forum Post',  'description' => 'Remove any forum post or reply.'],
                ['name' => 'Manage Community Spaces', 'description' => 'Create discussion spaces and collaboration groups, and choose their members and moderators.'],
                ['name' => 'Moderate Community', 'description' => 'Handle reports and held posts, and hide or restore content in every space.'],
            ],
        ],
        [
            'name' => 'Safety',
            'description' => 'Moderation, account restrictions and safety reporting.',
            'controls' => [
                ['name' => 'Restrict Users', 'description' => 'Warn, restrict or suspend members who break community or safeguarding rules.'],
                ['name' => 'View Safety Reports', 'description' => 'See moderation and safeguarding statistics.'],
                ['name' => 'Manage Safety Settings', 'description' => 'Change the content filter and automatic hiding rules.'],
            ],
        ],
        [
            'name' => 'Ask TRA',
            'description' => 'Member tax questions, expert answers and the knowledge base.',
            'controls' => [
                ['name' => 'Triage Questions', 'description' => 'Categorise questions and assign them to experts.'],
                ['name' => 'Answer Questions', 'description' => 'Write answers to questions assigned to you.'],
                ['name' => 'Review Answers', 'description' => 'Approve, return or publish answers to the knowledge base.'],
            ],
        ],
        [
            'name' => 'Competitions',
            'description' => 'Competitions, judging, results, rewards and certificates.',
            'controls' => [
                ['name' => 'Create Competition', 'description' => 'Create and edit competitions.'],
                ['name' => 'Review Competition', 'description' => 'Approve, return or reject proposed competitions.'],
                ['name' => 'Publish Competition', 'description' => 'Publish, schedule and cancel approved competitions.'],
                ['name' => 'Judge Competition', 'description' => 'Score competition entries.'],
                ['name' => 'Approve Results', 'description' => 'Approve and publish competition results.'],
                ['name' => 'Manage Rewards', 'description' => 'Keep the list of rewards and set competition prizes.'],
                ['name' => 'Award Rewards', 'description' => 'Give rewards to members and track their delivery.'],
                ['name' => 'Approve Rewards', 'description' => 'Approve rewards that cost money before they are issued.'],
                ['name' => 'Issue Certificates', 'description' => 'Issue and revoke certificates.'],
            ],
        ],
        [
            'name' => 'Alumni and Mentorship',
            'description' => 'Alumni stories and the mentorship programme.',
            'controls' => [
                ['name' => 'Review Alumni Stories', 'description' => 'Approve or return stories written by alumni.'],
                ['name' => 'Review Mentors', 'description' => 'Approve mentor applications and record background checks.'],
                ['name' => 'Manage Mentorships', 'description' => 'Match mentors and mentees, and follow and close mentorships.'],
            ],
        ],
        [
            'name' => 'Events',
            'description' => 'Manage TRA Tax Club events and registrations.',
            'controls' => [
                ['name' => 'Add Event',              'description' => 'Create a new event.'],
                ['name' => 'Edit Event',             'description' => 'Update event details.'],
                ['name' => 'Delete Event',           'description' => 'Delete an event.'],
                ['name' => 'View Event Attendees',   'description' => 'View registered attendees for an event.'],
                ['name' => 'Review Event', 'description' => 'Approve, return or reject proposed events and campaigns.'],
                ['name' => 'Publish Event', 'description' => 'Publish, schedule, postpone, cancel and close approved events.'],
                ['name' => 'Check In Attendees', 'description' => 'Record attendance by scanning membership cards or ticking the list.'],
            ],
        ],
    ];

    public function run(): void
    {
        $now = now();

        foreach ($this->modules as $moduleData) {
            $moduleId = (string) Str::uuid();

            DB::table('modules')->insertOrIgnore([
                'module_id' => $moduleId,
                'name' => $moduleData['name'],
                'slug' => Str::slug($moduleData['name'], '_'),
                'description' => $moduleData['description'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Re-fetch in case insertOrIgnore skipped (already exists)
            $module = DB::table('modules')->where('name', $moduleData['name'])->first();

            foreach ($moduleData['controls'] as $control) {
                $slug = Str::slug($control['name'], '_');

                DB::table('module_controls')->insertOrIgnore([
                    'control_id' => (string) Str::uuid(),
                    'name' => $control['name'],
                    'slug' => $slug,
                    'module_id' => $module->module_id,
                    'description' => $control['description'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command->info('✅ Modules and controls seeded successfully.');
    }
}
