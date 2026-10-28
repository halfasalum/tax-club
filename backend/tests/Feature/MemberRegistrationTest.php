<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registration_creates_a_pending_account_with_a_reference(): void
    {
        $institution = Institution::factory()->create();

        Livewire::test(Register::class)
            ->set('full_name', 'Asha Mohamed')
            ->set('phone', '0712 345 678')
            ->set('date_of_birth', now()->subYears(19)->toDateString())
            ->set('user_type', 'secondary')
            ->call('selectInstitution', $institution->institution_id)
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('accepted_terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertSee('REG-');

        $user = User::where('phone', '+255712345678')->firstOrFail();

        $this->assertSame(User::STATUS_PENDING_VERIFICATION, $user->account_status);
        $this->assertFalse($user->is_verified);
        $this->assertNull($user->membership_id);
        $this->assertNull($user->email);
        $this->assertMatchesRegularExpression('/^REG-\d{4}-[A-Z0-9]{6}$/', $user->registration_reference);
        $this->assertGuest();
    }

    public function test_registration_rejects_a_phone_number_already_registered_in_another_format(): void
    {
        User::factory()->create(['phone' => '+255712345678']);
        $institution = Institution::factory()->create();

        Livewire::test(Register::class)
            ->set('full_name', 'Second Person')
            ->set('phone', '0712345678')
            ->call('selectInstitution', $institution->institution_id)
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('accepted_terms', true)
            ->call('register')
            ->assertHasErrors(['phone' => 'unique']);
    }

    public function test_registration_requires_institution_and_consent(): void
    {
        Livewire::test(Register::class)
            ->set('full_name', 'Asha Mohamed')
            ->set('phone', '0712345678')
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->call('register')
            ->assertHasErrors(['institution_id', 'accepted_terms']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_pending_member_cannot_sign_in_and_is_told_why(): void
    {
        $user = User::factory()->pending()->create(['phone' => '+255712345678']);

        Livewire::test(Login::class)
            ->set('identifier', '0712345678')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('identifier')
            ->assertSee($user->registration_reference);

        $this->assertGuest();
    }

    public function test_active_member_signs_in_with_phone_and_reaches_the_member_dashboard(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);

        Livewire::test(Login::class)
            ->set('identifier', '0712 345 678')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('member.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get('/app')->assertOk()->assertSee($user->membership_id);
    }

    public function test_api_registration_no_longer_issues_a_token(): void
    {
        $institution = Institution::factory()->create();

        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Juma Ally',
            'phone' => '0754000111',
            'date_of_birth' => now()->subYears(20)->toDateString(),
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'user_type' => 'college',
            'tin' => '123-456-789',
            'institution_id' => $institution->institution_id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.account_status', User::STATUS_PENDING_VERIFICATION)
            ->assertJsonMissingPath('data.token');

        $this->postJson('/api/v1/auth/login', ['identifier' => '0754000111', 'password' => 'secret-pass-1'])
            ->assertForbidden();
    }

    public function test_api_members_cannot_verify_themselves(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/verify')->assertForbidden();

        $this->assertSame(User::STATUS_PENDING_VERIFICATION, $user->fresh()->account_status);
    }

    public function test_college_and_university_students_must_give_a_tin(): void
    {
        $college = Institution::factory()->create(['type' => 'college']);

        $form = Livewire::test(Register::class)
            ->set('full_name', 'Neema Paul')
            ->set('phone', '0713 222 333')
            ->set('date_of_birth', now()->subYears(20)->toDateString())
            ->set('user_type', 'college')
            ->assertSee(__('Register for a TIN'))
            ->assertSee('https://idras-ept.tra.go.tz/ept/reg/tpap/reg-tpap0101004')
            ->call('selectInstitution', $college->institution_id)
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('accepted_terms', true)
            ->call('register')
            ->assertHasErrors(['tin' => 'required']);

        $form->set('tin', '12345')->call('register')->assertHasErrors(['tin' => 'digits']);
        $form->set('tin', '123 456 789')->call('register')->assertHasNoErrors();

        $this->assertSame('123456789', User::where('phone', '+255713222333')->value('tin'));

        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Juma Ally',
            'phone' => '0754000111',
            'date_of_birth' => now()->subYears(21)->toDateString(),
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'user_type' => 'university',
            'tin' => '123-456-789',
            'institution_id' => $college->institution_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('tin');
    }

    public function test_primary_school_pupils_can_join_without_a_tin(): void
    {
        $school = Institution::factory()->create(['type' => 'primary']);

        Livewire::test(Register::class)
            ->set('user_type', 'primary')
            ->assertSee(__('Primary school'))
            ->assertDontSee(__('Register for a TIN'))
            ->set('full_name', 'Baraka John')
            ->set('phone', '0714 555 666')
            ->set('date_of_birth', now()->subYears(12)->toDateString())
            ->set('institution_search', substr($school->name, 0, 4))
            ->assertSee($school->name)
            ->call('selectInstitution', $school->institution_id)
            ->set('tin', '999999999')
            ->set('guardian_name', 'Rehema John')
            ->set('guardian_phone', '0715 777 888')
            ->set('guardian_consent', true)
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('accepted_terms', true)
            ->call('register')
            ->assertHasNoErrors();

        $pupil = User::where('phone', '+255714555666')->firstOrFail();
        $this->assertSame('primary', $pupil->user_type);
        $this->assertNull($pupil->tin);
    }

    public function test_pupils_can_join_from_age_six(): void
    {
        $school = Institution::factory()->create(['type' => 'primary']);
        $join = fn (string $dateOfBirth, string $phone) => $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Neema Ali',
            'phone' => $phone,
            'date_of_birth' => $dateOfBirth,
            'guardian_name' => 'Ali Hamisi',
            'guardian_relationship' => 'parent',
            'guardian_phone' => '0715 777 888',
            'guardian_consent' => true,
            'accepted_terms' => true,
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'user_type' => 'primary',
            'institution_id' => $school->institution_id,
        ]);

        $join(now()->subYears(6)->addDay()->toDateString(), '0716 111 222')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_of_birth');

        $join(now()->subYears(6)->toDateString(), '0716 111 333')->assertCreated();
    }
}
