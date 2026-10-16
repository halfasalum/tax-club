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
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'user_type' => 'college',
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

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/verify')->assertStatus(410);

        $this->assertSame(User::STATUS_PENDING_VERIFICATION, $user->fresh()->account_status);
    }
}
