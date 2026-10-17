<?php

namespace Tests\Feature;

use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Support\MemberRegistration;
use App\Support\Sms\BeemSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function useBeem(): void
    {
        config([
            'services.sms.driver' => 'beem',
            'services.beem.api_key' => 'test-key',
            'services.beem.secret_key' => 'test-secret',
            'services.beem.sender_id' => 'TRA-CLUB',
        ]);
        $this->app->forgetInstance(SmsGateway::class);
    }

    public function test_approval_sends_an_sms_in_the_members_language_and_logs_it(): void
    {
        $officer = User::factory()->create();
        $member = User::factory()->pending()->create(['preferred_locale' => 'sw', 'full_name' => 'Asha Juma']);

        MemberRegistration::approve($member, $officer);

        $sms = SmsMessage::where('user_id', $member->user_id)->where('purpose', 'registration_approved')->firstOrFail();
        $this->assertSame(SmsMessage::STATUS_SENT, $sms->status);
        $this->assertSame($member->phone, $sms->to);
        $this->assertStringContainsString('Hongera Asha J.', $sms->message);
        $this->assertStringContainsString($member->fresh()->membership_id, $sms->message);
    }

    public function test_beem_receives_the_documented_request(): void
    {
        $this->useBeem();
        Http::fake([
            'apisms.beem.africa/*' => Http::response(['successful' => true, 'request_id' => 4567, 'code' => 100, 'message' => 'Message Submitted Successfully'], 200),
        ]);

        $member = User::factory()->create(['phone' => '+255712345678']);
        $member->notify(new RegistrationApproved);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://apisms.beem.africa/v1/send'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-key:test-secret'))
                && $body['source_addr'] === 'TRA-CLUB'
                && $body['encoding'] === 0
                && $body['recipients'] === [['recipient_id' => 1, 'dest_addr' => '255712345678']]
                && str_contains($body['message'], 'TRA Tax Club');
        });

        $sms = SmsMessage::firstOrFail();
        $this->assertSame('beem', $sms->provider);
        $this->assertSame('4567', $sms->provider_reference);
        $this->assertSame(SmsMessage::STATUS_SENT, $sms->status);
    }

    public function test_a_beem_failure_is_logged_without_breaking_the_approval(): void
    {
        $this->useBeem();
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => false, 'code' => 102, 'message' => 'Insufficient balance'], 200)]);

        $member = User::factory()->pending()->create();
        MemberRegistration::approve($member, User::factory()->create());

        $this->assertSame(User::STATUS_ACTIVE, $member->fresh()->account_status);
        $sms = SmsMessage::where('purpose', 'registration_approved')->firstOrFail();
        $this->assertSame(SmsMessage::STATUS_FAILED, $sms->status);
        $this->assertStringContainsString('Insufficient balance', $sms->error);
        $this->assertSame(1, $sms->attempts);
    }

    public function test_missing_beem_credentials_fail_clearly(): void
    {
        $result = (new BeemSmsGateway(['api_key' => null, 'secret_key' => null, 'sender_id' => 'INFO', 'url' => 'https://apisms.beem.africa/v1/send', 'timeout' => 5]))
            ->send('+255712345678', 'Hello');

        $this->assertFalse($result->accepted);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function test_a_retried_notification_is_not_sent_twice(): void
    {
        $this->useBeem();
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'request_id' => 1], 200)]);

        $member = User::factory()->create();
        $notification = new RegistrationApproved;
        $notification->id = '01a14919-fde6-7179-b9c4-51534d3cbf98';

        $member->notifyNow($notification);
        $member->notifyNow($notification);

        Http::assertSentCount(1);
        $this->assertSame(1, SmsMessage::count());
    }
}
