<?php

namespace Tests\Feature;

use App\Livewire\Admin\Registrations;
use App\Livewire\Auth\Register;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class RegistrationAgeAndGuardianTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function form(int $age): Testable
    {
        $institution = Institution::factory()->create();

        return Livewire::test(Register::class)
            ->set('full_name', 'Asha Mohamed')
            ->set('phone', '0712345678')
            ->set('date_of_birth', now()->subYears($age)->subDays(10)->toDateString())
            ->call('selectInstitution', $institution->institution_id)
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('accepted_terms', true);
    }

    public function test_adult_registers_without_guardian_details(): void
    {
        $this->form(19)->assertSet('isMinor', false)->call('register')->assertHasNoErrors();

        $user = User::where('phone', '+255712345678')->firstOrFail();
        $this->assertSame(User::AGE_ADULT, $user->age_band);
        $this->assertNull($user->guardian_name);
        $this->assertSame('en', $user->preferred_locale);
    }

    public function test_minor_must_give_guardian_details_and_consent(): void
    {
        $this->form(15)
            ->assertSet('isMinor', true)
            ->assertSee('Parent or guardian')
            ->call('register')
            ->assertHasErrors(['guardian_name', 'guardian_phone', 'guardian_consent']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_minor_registration_stores_guardian_and_age_band(): void
    {
        $this->form(15)
            ->set('guardian_name', 'Mama Asha')
            ->set('guardian_phone', '0754 222 333')
            ->set('guardian_relationship', 'parent')
            ->set('guardian_consent', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('phone', '+255712345678')->firstOrFail();
        $this->assertSame(User::AGE_MINOR, $user->age_band);
        $this->assertSame('+255754222333', $user->guardian_phone);
        $this->assertNotNull($user->guardian_consent_at);
    }

    public function test_age_limits_are_enforced(): void
    {
        $this->form(5)->call('register')->assertHasErrors(['date_of_birth' => 'before_or_equal']);
        $this->form(36)->call('register')->assertHasErrors(['date_of_birth' => 'after']);
    }

    public function test_minor_can_only_be_approved_after_guardian_consent_is_confirmed(): void
    {
        $officer = $this->officerWith(['view_registrations', 'verify_registration', 'view_all_registrations']);
        $minor = User::factory()->pending()->minor()->create();

        $component = Livewire::actingAs($officer)
            ->test(Registrations::class)
            ->assertSee('Under 18')
            ->assertSee($minor->guardian_name)
            ->call('approve', $minor->user_id)
            ->assertHasErrors('guardian.'.$minor->user_id);

        $this->assertSame(User::STATUS_PENDING_VERIFICATION, $minor->fresh()->account_status);

        $component->set('guardianConfirmed.'.$minor->user_id, true)->call('approve', $minor->user_id)->assertHasNoErrors();

        $minor->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $minor->account_status);
        $this->assertSame($officer->user_id, $minor->guardian_consent_confirmed_by);
    }

    public function test_members_move_to_adult_band_on_their_18th_birthday(): void
    {
        $turned18 = User::factory()->minor()->create(['date_of_birth' => now()->subYears(18)->toDateString()]);
        $stillMinor = User::factory()->minor()->create(['date_of_birth' => now()->subYears(18)->addDay()->toDateString()]);

        $this->artisan('members:refresh-age-bands')->assertSuccessful();

        $this->assertSame(User::AGE_ADULT, $turned18->fresh()->age_band);
        $this->assertSame(User::AGE_MINOR, $stillMinor->fresh()->age_band);
    }

    public function test_leaderboard_is_for_members_only_and_shortens_names(): void
    {
        User::factory()->minor()->create(['full_name' => 'Asha Juma Mohamed', 'total_points' => 50, 'membership_id' => 'TC-26-SECRET01']);

        $this->getJson('/api/v1/leaderboard')->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/leaderboard')
            ->assertOk()
            ->assertJsonPath('data.leaderboard.0.display_name', 'Asha M.')
            ->assertJsonMissing(['full_name' => 'Asha Juma Mohamed'])
            ->assertJsonMissing(['membership_id' => 'TC-26-SECRET01']);
    }

    public function test_api_registration_requires_guardian_for_minors(): void
    {
        $institution = Institution::factory()->create();

        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Juma Ally',
            'phone' => '0754000111',
            'date_of_birth' => now()->subYears(14)->toDateString(),
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'user_type' => 'secondary',
            'institution_id' => $institution->institution_id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['guardian_name', 'guardian_phone', 'guardian_consent'], 'errors');
    }
}
