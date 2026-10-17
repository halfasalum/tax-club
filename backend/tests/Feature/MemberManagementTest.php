<?php

namespace Tests\Feature;

use App\Livewire\Admin\Coordinators;
use App\Livewire\Admin\Members\Index;
use App\Livewire\Admin\Members\Show;
use App\Livewire\Admin\Registrations;
use App\Models\CoordinatorAssignment;
use App\Models\Institution;
use App\Models\MemberStatusChange;
use App\Models\User;
use App\Support\MemberAccounts;
use App\Support\MemberRegistration;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PatronRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function nationalOfficer(): User
    {
        return $this->officerWith(['view_members', 'change_member_status', 'manage_alumni', 'view_all_registrations']);
    }

    public function test_officer_suspends_and_reactivates_a_member_with_reasons(): void
    {
        $officer = $this->nationalOfficer();
        $member = User::factory()->create(['full_name' => 'Neema Ally']);
        $member->createToken('mobile_app');

        $page = Livewire::actingAs($officer)->test(Show::class, ['member' => $member])
            ->call('startStatusChange', User::STATUS_SUSPENDED)
            ->call('changeStatus')
            ->assertHasErrors('statusReason')
            ->set('statusReason', 'Repeated rude posts in the forum')
            ->call('changeStatus')
            ->assertHasNoErrors()
            ->assertSee('Account suspended');

        $member->refresh();
        $this->assertSame(User::STATUS_SUSPENDED, $member->account_status);
        $this->assertSame('Repeated rude posts in the forum', $member->status_reason);
        $this->assertSame(0, $member->tokens()->count());
        $this->assertDatabaseHas('sms_messages', ['user_id' => $member->user_id, 'purpose' => 'account_status_changed']);

        $page->call('startStatusChange', User::STATUS_ACTIVE)
            ->set('statusReason', 'Spoke with the patron')
            ->call('changeStatus');

        $this->assertTrue($member->fresh()->isActive());
        $this->assertSame(
            [User::STATUS_ACTIVE, User::STATUS_SUSPENDED],
            $member->statusHistory()->pluck('to_value')->all(),
        );
    }

    public function test_status_rules_are_enforced(): void
    {
        $officer = $this->nationalOfficer();

        $this->expectException(\InvalidArgumentException::class);
        MemberAccounts::changeStatus($officer, User::STATUS_SUSPENDED, 'Testing myself', $officer);
    }

    public function test_deactivated_members_cannot_be_suspended(): void
    {
        $member = User::factory()->create(['account_status' => User::STATUS_DEACTIVATED]);

        $this->assertSame([User::STATUS_ACTIVE], MemberAccounts::allowedStatuses($member));
        $this->expectException(\InvalidArgumentException::class);
        MemberAccounts::changeStatus($member, User::STATUS_SUSPENDED, 'Not allowed here', $this->nationalOfficer());
    }

    public function test_approval_and_rejection_are_part_of_the_history(): void
    {
        $officer = User::factory()->create();
        $member = User::factory()->pending()->create();

        MemberRegistration::approve($member, $officer);

        $change = $member->statusHistory()->sole();
        $this->assertSame([User::STATUS_PENDING_VERIFICATION, User::STATUS_ACTIVE], [$change->from_value, $change->to_value]);
    }

    public function test_member_asks_to_become_alumni_and_officer_approves(): void
    {
        $officer = $this->nationalOfficer();
        $member = User::factory()->create(['user_type' => 'university', 'total_points' => 120]);
        $number = $member->membership_id;

        MemberAccounts::requestAlumni($member);

        Livewire::actingAs($officer)->test(Index::class)
            ->set('alumniRequests', true)
            ->assertSee($member->full_name);

        Livewire::actingAs($officer)->test(Show::class, ['member' => $member])->call('makeAlumni')->assertSee('is now alumni');

        $member->refresh();
        $this->assertSame('alumni', $member->user_type);
        $this->assertSame($number, $member->membership_id);
        $this->assertSame(120, $member->total_points);
        $this->assertNull($member->alumni_requested_at);
        $this->assertDatabaseHas('member_status_changes', ['user_id' => $member->user_id, 'field' => MemberStatusChange::FIELD_CATEGORY, 'from_value' => 'university', 'to_value' => 'alumni']);
        $this->assertDatabaseHas('sms_messages', ['user_id' => $member->user_id, 'purpose' => 'alumni_status_granted']);
    }

    public function test_a_graduating_class_moves_to_alumni_in_one_step(): void
    {
        $officer = $this->nationalOfficer();
        $school = Institution::factory()->create();
        $class = User::factory()->count(3)->create(['institution_id' => $school->institution_id]);
        $alreadyAlumni = User::factory()->create(['institution_id' => $school->institution_id, 'user_type' => 'alumni']);

        Livewire::actingAs($officer)->test(Index::class)
            ->set('institution', $school->institution_id)
            ->call('selectPage')
            ->assertCount('selected', 3)
            ->call('moveSelectedToAlumni')
            ->assertSee('3 moved to alumni');

        $this->assertSame(4, User::where('institution_id', $school->institution_id)->where('user_type', 'alumni')->count());
        $this->assertTrue($class->every(fn (User $member) => $member->fresh()->alumni_since !== null));
        $this->assertNull($alreadyAlumni->fresh()->alumni_since);
    }

    public function test_coordinators_see_only_their_regions(): void
    {
        $this->seed([ModuleSeeder::class, PatronRoleSeeder::class]);
        $admin = $this->officerWith(['assign_coordinator'], ['user_type' => 'staff']);
        $mwanza = Institution::factory()->create(['region' => 'Mwanza', 'name' => 'Mwanza Secondary']);
        $arusha = Institution::factory()->create(['region' => 'Arusha', 'name' => 'Arusha Secondary']);
        $inMwanza = User::factory()->create(['institution_id' => $mwanza->institution_id, 'full_name' => 'Mwanza Member']);
        $inArusha = User::factory()->create(['institution_id' => $arusha->institution_id, 'full_name' => 'Arusha Member']);
        User::factory()->pending()->create(['institution_id' => $arusha->institution_id, 'full_name' => 'Arusha Applicant']);

        Livewire::actingAs($admin)->test(Coordinators::class)
            ->set('region', 'Mwanza')
            ->set('phone', '0754 999 000')
            ->set('fullName', 'Bwana Coordinator')
            ->call('assign')
            ->assertHasNoErrors()
            ->assertSee('New coordinator account created');

        $coordinator = User::where('phone', '+255754999000')->sole();
        $this->assertTrue($coordinator->hasRole('coordinator'));
        $this->assertContains($mwanza->institution_id, $coordinator->institutionScope());
        $this->assertNotContains($arusha->institution_id, $coordinator->institutionScope());
        $this->assertDatabaseHas('sms_messages', ['user_id' => $coordinator->user_id, 'purpose' => 'coordinator_assigned']);

        Livewire::actingAs($coordinator)->test(Index::class)->assertSee('Mwanza Member')->assertDontSee('Arusha Member');
        Livewire::actingAs($coordinator)->test(Registrations::class)->assertDontSee('Arusha Applicant');
        $this->actingAs($coordinator)->get(route('admin.members.show', $inArusha))->assertNotFound();
        $this->actingAs($coordinator)->get(route('admin.institutions.show', $arusha))->assertNotFound();
        $this->actingAs($coordinator)->get(route('admin.members.show', $inMwanza))->assertOk();

        $assignment = CoordinatorAssignment::sole();
        Livewire::actingAs($admin)->test(Coordinators::class)
            ->call('startEnding', $assignment->id)
            ->set('endReason', 'Transferred to Dodoma office')
            ->call('endAssignment');

        $this->assertSame([], $coordinator->fresh()->institutionScope());
        $this->assertFalse($coordinator->fresh()->hasRole('coordinator'));
    }

    public function test_members_list_needs_permission(): void
    {
        $this->actingAs($this->officerWith(['view_registrations']))->get(route('admin.members.index'))->assertForbidden();
    }
}
