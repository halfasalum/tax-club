<?php

namespace Tests\Feature;

use App\Livewire\Admin\AuditLog as AuditLogPage;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class AuditAndSignInTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function lastStaffCode(User $user): string
    {
        $message = SmsMessage::where('user_id', $user->user_id)->where('purpose', 'staff_login_code')->latest('id')->value('message');
        preg_match('/\b(\d{6})\b/', (string) $message, $matches);

        return $matches[1];
    }

    public function test_changes_are_recorded_with_before_and_after_values(): void
    {
        $officer = User::factory()->create();
        $this->actingAs($officer);

        $institution = Institution::factory()->create(['name' => 'Old Name School']);
        $institution->update(['name' => 'New Name School']);

        $record = AuditLog::where('action', 'institution.updated')->sole();
        $this->assertSame($officer->user_id, $record->actor_id);
        $this->assertSame(['name' => 'Old Name School'], $record->before);
        $this->assertSame(['name' => 'New Name School'], $record->after);
    }

    public function test_secrets_are_never_stored_in_the_audit_log(): void
    {
        $user = User::factory()->create();
        $user->update(['password_hash' => bcrypt('another-pass'), 'last_login' => now()]);

        $record = AuditLog::where('action', 'user.updated')->sole();
        $this->assertSame(['password_hash' => '[changed]'], $record->after);
    }

    public function test_audit_records_cannot_be_changed_or_deleted(): void
    {
        $record = AuditLog::create(['action' => 'test.event']);

        $this->expectException(LogicException::class);
        $record->update(['action' => 'tampered']);
    }

    public function test_member_sign_in_and_failures_are_audited(): void
    {
        $member = User::factory()->create(['phone' => '+255712345678']);

        Livewire::test(Login::class)->set('identifier', '0712345678')->set('password', 'wrong')->call('login')->assertHasErrors('identifier');
        Livewire::test(Login::class)->set('identifier', '0712345678')->set('password', 'password')->call('login')->assertRedirect(route('member.dashboard'));

        $failure = AuditLog::where('action', 'auth.login_failed')->sole();
        $this->assertSame('071••••678', $failure->after['identifier']);
        $this->assertSame(['channel' => 'web', 'two_factor' => false], AuditLog::where('action', 'auth.login')->sole()->after);
        $this->assertNotNull($member->fresh()->last_login);
    }

    public function test_staff_must_enter_an_sms_code_to_sign_in(): void
    {
        $officer = $this->officerWith(['view_registrations'], ['phone' => '+255754000111']);

        $component = Livewire::test(Login::class)
            ->set('identifier', '0754000111')
            ->set('password', 'password')
            ->call('login')
            ->assertSet('awaitingCode', true)
            ->assertNoRedirect();
        $this->assertGuest();

        $component->set('code', $this->lastStaffCode($officer) === '000000' ? '111111' : '000000')
            ->call('confirmCode')
            ->assertHasErrors('code');
        $this->assertGuest();

        $component->set('code', $this->lastStaffCode($officer))->call('confirmCode')->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($officer);
        $this->assertSame(['channel' => 'web', 'two_factor' => true], AuditLog::where('action', 'auth.login')->sole()->after);
    }

    public function test_staff_api_sign_in_needs_the_sms_code(): void
    {
        $officer = $this->officerWith(['view_registrations'], ['phone' => '+255754000111']);

        $challenge = $this->postJson('/api/v1/auth/login', ['identifier' => '0754000111', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonMissingPath('data.token')
            ->json('data.challenge');

        $this->postJson('/api/v1/auth/login/verify', ['challenge' => 'forged', 'code' => $this->lastStaffCode($officer)])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login/verify', ['challenge' => $challenge, 'code' => $this->lastStaffCode($officer)])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'expires_at']]);
    }

    public function test_suspended_accounts_lose_access_immediately(): void
    {
        $member = User::factory()->create();
        $token = $member->createToken('mobile_app')->plainTextToken;

        $this->actingAs($member)->get('/app')->assertOk();

        $member->update(['account_status' => User::STATUS_SUSPENDED]);

        $this->get('/app')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_audit_log_page_needs_permission_and_filters_by_area(): void
    {
        $this->actingAs($this->officerWith(['view_registrations']))->get('/admin/audit')->assertForbidden();

        $officer = $this->officerWith(['view_audit_log']);
        Institution::factory()->create(['name' => 'Kibaha Secondary']);
        AuditLog::create(['action' => 'auth.login_failed']);

        Livewire::actingAs($officer)
            ->test(AuditLogPage::class)
            ->assertSee('Failed sign-in')
            ->set('area', 'institutions')
            ->assertDontSee('Failed sign-in')
            ->assertSee('Kibaha Secondary');
    }
}
