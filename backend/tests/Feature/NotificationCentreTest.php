<?php

namespace Tests\Feature;

use App\Livewire\Admin\NotificationSettings;
use App\Livewire\Admin\SmsLog;
use App\Livewire\Member\Inbox;
use App\Models\NotificationTemplate;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Support\MemberRegistration;
use App\Support\Notify;
use App\Support\PasswordRecovery;
use App\Support\Sms\SmsGateway;
use App\Support\Sms\SmsRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class NotificationCentreTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_approval_lands_in_the_inbox_as_well_as_by_sms(): void
    {
        $member = User::factory()->pending()->create();
        MemberRegistration::approve($member, User::factory()->create());

        $this->assertSame(1, $member->unreadNotifications()->count());
        $this->assertSame('registration_approved', $member->notifications()->first()->data['key']);
        $this->assertDatabaseHas('sms_messages', ['user_id' => $member->user_id, 'purpose' => 'registration_approved']);

        $member->refresh();
        Livewire::actingAs($member)->test(Inbox::class)
            ->assertSee('Your membership is approved')
            ->call('open', $member->notifications()->first()->id)
            ->assertRedirect(route('member.card'));

        $this->assertSame(0, $member->unreadNotifications()->count());
    }

    public function test_codes_never_go_to_the_inbox(): void
    {
        $member = User::factory()->create(['phone' => '+255712345678']);
        PasswordRecovery::requestCode('0712345678');

        $this->assertSame(0, $member->notifications()->count());
        $this->assertDatabaseHas('sms_messages', ['user_id' => $member->user_id, 'purpose' => 'password_reset_code']);
    }

    public function test_admin_edits_wording_and_turns_sms_off(): void
    {
        $admin = $this->officerWith(['manage_notification_templates']);

        Livewire::actingAs($admin)->test(NotificationSettings::class)
            ->call('edit', 'registration_approved')
            ->set('bodyEn', 'Welcome to Tax Club, :name! Number: :number')
            ->set('smsEnabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $template = NotificationTemplate::find('registration_approved');
        $this->assertFalse($template->sms_enabled);
        $this->assertNull($template->body_sw, 'Unchanged Kiswahili wording is stored as the default.');

        $member = User::factory()->create(['preferred_locale' => 'en', 'full_name' => 'Juma Ally']);
        $member->notify(new RegistrationApproved);

        $this->assertSame(0, SmsMessage::where('user_id', $member->user_id)->count());
        $this->assertSame('Welcome to Tax Club, Juma A.! Number: '.$member->membership_id, $member->notifications()->first()->data['message']);
    }

    public function test_code_messages_must_keep_the_code_and_their_sms(): void
    {
        $admin = $this->officerWith(['manage_notification_templates']);

        Livewire::actingAs($admin)->test(NotificationSettings::class)
            ->call('edit', 'password_reset_code')
            ->set('bodyEn', 'Your reset code expires in :minutes minutes.')
            ->set('smsEnabled', false)
            ->call('save')
            ->assertHasErrors('bodyEn');

        $this->assertNull(NotificationTemplate::find('password_reset_code'));
    }

    public function test_failed_sms_can_be_sent_again_but_codes_cannot(): void
    {
        config(['services.sms.driver' => 'beem', 'services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        $this->app->forgetInstance(SmsGateway::class);
        Http::fakeSequence('apisms.beem.africa/*')
            ->push(['successful' => false, 'message' => 'Insufficient balance'])
            ->push(['successful' => false, 'message' => 'Insufficient balance'])
            ->push(['successful' => true, 'request_id' => 99]);

        $member = User::factory()->create(['phone' => '+255712345678']);
        Notify::send($member, new RegistrationApproved);
        PasswordRecovery::requestCode('0712345678');

        $failed = SmsMessage::where('purpose', 'registration_approved')->sole();
        $code = SmsMessage::where('purpose', 'password_reset_code')->sole();
        $this->assertSame(SmsMessage::STATUS_FAILED, $failed->status);
        $this->assertFalse(SmsRetry::canRetry($code));
        $this->assertStringNotContainsString(preg_replace('/\D/', '', $code->message), SmsRetry::displayText($code));

        Livewire::actingAs($this->officerWith(['view_sms_log']))->test(SmsLog::class)
            ->assertSee('Insufficient balance')
            ->call('retry', $failed->id)
            ->assertSee('The message was sent.');

        $this->assertSame(SmsMessage::STATUS_SENT, $failed->fresh()->status);
        $this->assertSame('99', $failed->fresh()->provider_reference);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sms.retried']);
    }

    public function test_api_inbox(): void
    {
        $member = User::factory()->create();
        $member->notify(new RegistrationApproved);
        $id = $member->notifications()->first()->id;

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.key', 'registration_approved');

        $this->actingAs($member, 'sanctum')->postJson("/api/v1/notifications/{$id}/read")->assertJsonPath('data.unread_count', 0);
    }
}
