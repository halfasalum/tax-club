<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\PasswordRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function lastCodeSentTo(User $user): string
    {
        $sms = SmsMessage::where('user_id', $user->user_id)->where('purpose', 'password_reset_code')->latest('id')->firstOrFail();
        preg_match('/\b(\d{6})\b/', $sms->message, $matches);

        return $matches[1];
    }

    public function test_member_resets_password_with_the_sms_code(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);
        $user->createToken('mobile_app');

        $component = Livewire::test(ForgotPassword::class)
            ->set('identifier', '0712 345 678')
            ->call('sendCode')
            ->assertSet('codeSent', true);

        $component->set('code', $this->lastCodeSentTo($user))
            ->set('password', 'brand-new-pass')
            ->set('password_confirmation', 'brand-new-pass')
            ->call('resetPassword')
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $user->password_hash));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('sms_messages', ['user_id' => $user->user_id, 'purpose' => 'password_changed']);
    }

    public function test_unknown_numbers_get_the_same_answer_and_no_sms(): void
    {
        Livewire::test(ForgotPassword::class)
            ->set('identifier', '0799 999 999')
            ->call('sendCode')
            ->assertSet('codeSent', true)
            ->assertHasNoErrors();

        $this->assertSame(0, SmsMessage::count());
    }

    public function test_wrong_codes_are_limited_to_five_attempts(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);
        PasswordRecovery::requestCode('0712345678');
        $code = $this->lastCodeSentTo($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < PasswordRecovery::MAX_ATTEMPTS; $i++) {
            try {
                PasswordRecovery::reset('0712345678', $wrong, 'brand-new-pass');
            } catch (\InvalidArgumentException) {
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        PasswordRecovery::reset('0712345678', $code, 'brand-new-pass');
    }

    public function test_expired_and_replaced_codes_are_refused(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);

        PasswordRecovery::requestCode('0712345678');
        $first = $this->lastCodeSentTo($user);
        PasswordRecovery::requestCode('0712345678');
        $second = $this->lastCodeSentTo($user);

        try {
            PasswordRecovery::reset('0712345678', $first, 'brand-new-pass');
            $this->assertSame($first, $second, 'The replaced code should not work.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        DB::table('password_reset_codes')->update(['expires_at' => now()->subMinute()]);

        $this->expectException(\InvalidArgumentException::class);
        PasswordRecovery::reset('0712345678', $second, 'brand-new-pass');
    }

    public function test_code_requests_are_rate_limited(): void
    {
        User::factory()->create(['phone' => '+255712345678']);

        for ($i = 0; $i < PasswordRecovery::MAX_REQUESTS; $i++) {
            PasswordRecovery::requestCode('0712345678');
        }

        Livewire::test(ForgotPassword::class)
            ->set('identifier', '0712345678')
            ->call('sendCode')
            ->assertHasErrors('identifier');
    }

    public function test_api_recovery_flow(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);

        $this->postJson('/api/v1/auth/recover', ['identifier' => '0712345678'])->assertOk();

        $this->postJson('/api/v1/auth/recover/reset', [
            'identifier' => '0712345678',
            'code' => $this->lastCodeSentTo($user),
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', ['identifier' => '0712345678', 'password' => 'brand-new-pass'])->assertOk();
    }
}
