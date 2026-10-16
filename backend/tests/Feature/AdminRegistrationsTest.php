<?php

namespace Tests\Feature;

use App\Livewire\Admin\Registrations;
use App\Livewire\Auth\Login;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRegistrationsTest extends TestCase
{
    use RefreshDatabase;

    private function officerWith(array $controlSlugs): User
    {
        $this->seed(ModuleSeeder::class);

        $role = Role::create([
            'role_id' => (string) Str::uuid(),
            'name' => 'Coordinator '.Str::random(4),
            'slug' => 'coordinator_'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);

        $controls = DB::table('module_controls')->whereIn('slug', $controlSlugs)->pluck('control_id');
        $role->controls()->attach($controls->all());

        $officer = User::factory()->create();
        $officer->roles()->attach($role->role_id, ['assigned_at' => now()]);

        return $officer;
    }

    public function test_members_without_admin_permissions_are_sent_to_their_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertRedirect(route('member.dashboard'));
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/admin/registrations')->assertRedirect(route('login'));
    }

    public function test_officer_approves_a_registration_and_a_membership_number_is_issued(): void
    {
        $officer = $this->officerWith(['view_registrations', 'verify_registration']);
        $applicant = User::factory()->pending()->create();

        $this->actingAs($officer)->get('/admin/registrations')->assertOk()->assertSee($applicant->full_name);

        Livewire::actingAs($officer)
            ->test(Registrations::class)
            ->call('approve', $applicant->user_id)
            ->assertHasNoErrors();

        $applicant->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $applicant->account_status);
        $this->assertTrue($applicant->is_verified);
        $this->assertSame($officer->user_id, $applicant->verified_by);
        $this->assertNotNull($applicant->verified_at);
        $this->assertMatchesRegularExpression('/^TC-\d{2}-[A-Z0-9]{8}$/', $applicant->membership_id);
    }

    public function test_rejection_requires_a_reason_and_records_it(): void
    {
        $officer = $this->officerWith(['view_registrations', 'verify_registration']);
        $applicant = User::factory()->pending()->create();

        $component = Livewire::actingAs($officer)
            ->test(Registrations::class)
            ->call('startRejecting', $applicant->user_id)
            ->call('reject')
            ->assertHasErrors('rejectionReason');

        $component->set('rejectionReason', 'Not a student at this school')->call('reject')->assertHasNoErrors();

        $applicant->refresh();
        $this->assertSame(User::STATUS_REJECTED, $applicant->account_status);
        $this->assertSame('Not a student at this school', $applicant->status_reason);
    }

    public function test_viewing_the_queue_does_not_grant_approval(): void
    {
        $officer = $this->officerWith(['view_registrations']);
        $applicant = User::factory()->pending()->create();

        Livewire::actingAs($officer)
            ->test(Registrations::class)
            ->call('approve', $applicant->user_id)
            ->assertForbidden();

        $this->assertSame(User::STATUS_PENDING_VERIFICATION, $applicant->fresh()->account_status);
    }

    public function test_approving_an_already_decided_registration_shows_an_error(): void
    {
        $officer = $this->officerWith(['view_registrations', 'verify_registration']);
        $applicant = User::factory()->create();

        Livewire::actingAs($officer)
            ->test(Registrations::class)
            ->call('approve', $applicant->user_id)
            ->assertHasErrors('decision');
    }

    public function test_seeded_super_admin_can_sign_in_to_the_administration_portal(): void
    {
        config(['app.env' => 'testing']);
        putenv('SUPERADMIN_PASSWORD=Strong-pass-123');
        $this->seed();
        putenv('SUPERADMIN_PASSWORD');

        $admin = User::where('email', 'superadmin@taxclub.go.tz')->firstOrFail();

        $this->assertTrue($admin->isActive());

        Livewire::test(Login::class)
            ->set('identifier', 'superadmin@taxclub.go.tz')
            ->set('password', 'Strong-pass-123')
            ->call('login')
            ->assertRedirect(route('admin.dashboard'));
    }
}
