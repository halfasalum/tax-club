<?php

namespace Tests\Feature;

use App\Livewire\Admin\Registrations;
use App\Livewire\Auth\Register;
use App\Models\ClubPatron;
use App\Models\Institution;
use App\Models\TaxClub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class PatronScopeTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    /**
     * @return array{0: User, 1: Institution, 2: Institution}
     */
    private function patronOfOneSchool(): array
    {
        $own = Institution::factory()->create(['name' => 'Own School']);
        $other = Institution::factory()->create(['name' => 'Other School']);
        $club = TaxClub::factory()->create(['institution_id' => $own->institution_id]);

        $patron = $this->officerWith(['view_registrations', 'verify_registration'], ['user_type' => 'staff']);
        ClubPatron::create(['tax_club_id' => $club->tax_club_id, 'user_id' => $patron->user_id, 'starts_on' => now()->subMonth()->toDateString()]);

        return [$patron, $own, $other];
    }

    public function test_patron_sees_and_decides_only_their_own_institution(): void
    {
        [$patron, $own, $other] = $this->patronOfOneSchool();
        $ownStudent = User::factory()->pending()->create(['full_name' => 'Asha Own', 'institution_id' => $own->institution_id]);
        $otherStudent = User::factory()->pending()->create(['full_name' => 'Juma Other', 'institution_id' => $other->institution_id]);

        $component = Livewire::actingAs($patron)
            ->test(Registrations::class)
            ->assertSee('Asha Own')
            ->assertDontSee('Juma Other');

        $component->call('approve', $ownStudent->user_id)->assertHasNoErrors();
        $this->assertSame(User::STATUS_ACTIVE, $ownStudent->fresh()->account_status);

        $component->call('approve', $otherStudent->user_id)->assertNotFound();
        $this->assertSame(User::STATUS_PENDING_VERIFICATION, $otherStudent->fresh()->account_status);
    }

    public function test_patron_whose_assignment_ended_sees_nothing(): void
    {
        [$patron, $own] = $this->patronOfOneSchool();
        ClubPatron::query()->update(['ends_on' => now()->subDay()->toDateString()]);
        User::factory()->pending()->create(['full_name' => 'Asha Own', 'institution_id' => $own->institution_id]);

        Livewire::actingAs($patron)
            ->test(Registrations::class)
            ->assertDontSee('Asha Own')
            ->assertSee('You are not a patron or coordinator yet');
    }

    public function test_tra_officer_with_view_all_permission_sees_every_institution(): void
    {
        [, $own, $other] = $this->patronOfOneSchool();
        User::factory()->pending()->create(['full_name' => 'Asha Own', 'institution_id' => $own->institution_id]);
        User::factory()->pending()->create(['full_name' => 'Juma Other', 'institution_id' => $other->institution_id]);
        $officer = $this->officerWith(['view_registrations', 'verify_registration', 'view_all_registrations']);

        Livewire::actingAs($officer)
            ->test(Registrations::class)
            ->assertSee('Asha Own')
            ->assertSee('Juma Other');
    }

    public function test_registration_refuses_an_inactive_institution(): void
    {
        $closed = Institution::factory()->inactive()->create();

        Livewire::test(Register::class)
            ->set('full_name', 'Asha Mohamed')
            ->set('phone', '0712345678')
            ->set('institution_id', $closed->institution_id)
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('accepted_terms', true)
            ->call('register')
            ->assertHasErrors(['institution_id']);
    }
}
