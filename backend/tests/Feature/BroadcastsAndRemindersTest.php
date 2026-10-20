<?php

namespace Tests\Feature;

use App\Livewire\Admin\Broadcasts\Form;
use App\Livewire\Admin\Broadcasts\Show;
use App\Livewire\Admin\ReminderSettings;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Institution;
use App\Models\Opportunity;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\BroadcastMessage;
use App\Notifications\EventReminder;
use App\Notifications\OpportunityDeadlineReminder;
use App\Support\Broadcasts;
use App\Support\NotificationPreferences;
use App\Support\OpportunityBoard;
use App\Support\Reminders;
use App\Support\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class BroadcastsAndRemindersTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_targeted_broadcast_needs_approval_and_reaches_only_the_audience(): void
    {
        Notification::fake();
        $mwanza = Institution::factory()->create(['region' => 'Mwanza']);
        $inMwanza = User::factory()->count(2)->create(['institution_id' => $mwanza->institution_id]);
        $elsewhere = User::factory()->create(['institution_id' => Institution::factory()->create(['region' => 'Arusha'])->institution_id]);
        $author = $this->officerWith(['create_broadcast'], ['user_type' => 'staff']);
        $approver = $this->officerWith(['approve_broadcast'], ['user_type' => 'staff']);

        Livewire::actingAs($author)->test(Form::class)
            ->set('title', 'Mwanza Tax Club week')
            ->set('message_sw', 'Wiki ya Tax Club inaanza Jumatatu. Mwone mlezi wa klabu yako.')
            ->set('audienceRegions', ['Mwanza'])
            ->assertSee('members match')
            ->call('save', true)
            ->assertHasNoErrors();

        $broadcast = Broadcast::firstOrFail();
        $this->assertSame(Review::IN_REVIEW, $broadcast->status);
        Notification::assertNothingSent();

        $this->expectsAuthorCannotApprove($broadcast, $author);

        Livewire::actingAs($approver)->test(Show::class, ['broadcast' => $broadcast])
            ->call('approve')
            ->call('send')
            ->assertSee('Sent.');

        $broadcast->refresh();
        $this->assertSame(Broadcast::SENT, $broadcast->status);
        $this->assertSame(2, $broadcast->recipients_count);
        Notification::assertSentTo($inMwanza, BroadcastMessage::class);
        Notification::assertNotSentTo($elsewhere, BroadcastMessage::class);
    }

    private function expectsAuthorCannotApprove(Broadcast $broadcast, User $author): void
    {
        try {
            Broadcasts::approve($broadcast, $author);
            $this->fail('Authors must not approve their own broadcasts.');
        } catch (InvalidArgumentException) {
            $this->assertSame(Review::IN_REVIEW, $broadcast->fresh()->status);
        }
    }

    public function test_scheduled_broadcast_goes_out_on_time_in_each_members_language(): void
    {
        $swahili = User::factory()->create(['preferred_locale' => 'sw']);
        $english = User::factory()->create(['preferred_locale' => 'en']);
        $broadcast = Broadcasts::create(['title' => 'Tax Day', 'message_sw' => 'Siku ya Mlipakodi ni kesho.', 'message_en' => 'Taxpayers Day is tomorrow.', 'send_sms' => true, 'send_inbox' => true], User::factory()->create());
        $broadcast->forceFill(['status' => Review::APPROVED])->save();

        Broadcasts::send($broadcast, User::factory()->create(), now()->addHour());
        $this->assertSame(0, SmsMessage::count());

        $this->travel(2)->hours();
        Broadcasts::applySchedule();

        $this->assertStringContainsString('Siku ya Mlipakodi', SmsMessage::where('user_id', $swahili->user_id)->value('message'));
        $this->assertStringContainsString('Taxpayers Day', SmsMessage::where('user_id', $english->user_id)->value('message'));
        $this->assertSame('broadcast', SmsMessage::where('user_id', $english->user_id)->value('purpose'));
    }

    public function test_members_who_switched_announcements_off_get_no_sms(): void
    {
        $member = User::factory()->create();
        NotificationPreferences::set($member, 'announcements', sms: false, inbox: true);
        $broadcast = Broadcasts::create(['title' => 'News', 'message_sw' => 'Habari njema kwa wanachama wote.', 'send_sms' => true, 'send_inbox' => true], User::factory()->create());
        $broadcast->forceFill(['status' => Review::APPROVED])->save();

        Broadcasts::deliver($broadcast);

        $this->assertSame(0, SmsMessage::where('user_id', $member->user_id)->count());
        $this->assertSame(1, $member->notifications()->count());
    }

    public function test_broadcast_cannot_be_sent_twice(): void
    {
        User::factory()->create();
        $broadcast = Broadcasts::create(['title' => 'Once', 'message_sw' => 'Ujumbe mmoja tu.', 'send_sms' => false, 'send_inbox' => true], User::factory()->create());
        $broadcast->forceFill(['status' => Review::APPROVED])->save();

        $this->assertSame(User::where('user_type', '!=', 'staff')->count(), Broadcasts::deliver($broadcast));
        $this->assertSame(0, Broadcasts::deliver($broadcast->fresh()));
    }

    public function test_event_and_opportunity_reminders_go_out_once(): void
    {
        Notification::fake();
        $event = Event::factory()->create(['starts_at' => now()->addHours(5)]);
        $registered = User::factory()->create();
        EventRegistration::create(['event_id' => $event->event_id, 'user_id' => $registered->user_id, 'status' => 'registered']);

        $opportunity = Opportunity::factory()->published()->create(['deadline' => now()->addDays(2)]);
        $saver = User::factory()->create();
        OpportunityBoard::save($opportunity, $saver);

        $this->assertSame(['event' => 1, 'opportunity' => 1, 'quiz' => 0], Reminders::run());
        $this->assertSame(['event' => 0, 'opportunity' => 0, 'quiz' => 0], Reminders::run());

        Notification::assertSentToTimes($registered, EventReminder::class, 1);
        Notification::assertSentTo($saver, OpportunityDeadlineReminder::class);
    }

    public function test_administrators_switch_reminders_off_and_change_timing(): void
    {
        $admin = $this->officerWith(['manage_reminders']);
        Livewire::actingAs($admin)->test(ReminderSettings::class)
            ->set('settings.event.enabled', false)
            ->set('settings.opportunity.lead', 7)
            ->call('save')
            ->assertSee('Saved.');

        $this->assertFalse(Reminders::settings()['event']['enabled']);
        $this->assertSame(7, Reminders::settings()['opportunity']['lead']);

        $event = Event::factory()->create(['starts_at' => now()->addHours(2)]);
        EventRegistration::create(['event_id' => $event->event_id, 'user_id' => User::factory()->create()->user_id, 'status' => 'registered']);
        $this->assertSame(0, Reminders::run()['event']);
    }

    public function test_admin_screens_render(): void
    {
        $officer = $this->officerWith(['create_broadcast', 'approve_broadcast', 'manage_reminders']);
        $broadcast = Broadcasts::create(['title' => 'Hello members', 'message_sw' => 'Karibuni.', 'send_sms' => false, 'send_inbox' => true], $officer);

        $this->actingAs($officer)->get('/admin/broadcasts?lang=en')->assertOk()->assertSee('Hello members');
        $this->actingAs($officer)->get("/admin/broadcasts/{$broadcast->broadcast_id}?lang=en")->assertOk()->assertSee('Karibuni.');
        $this->actingAs($officer)->get('/admin/broadcasts/new?lang=en')->assertOk();
        $this->actingAs($officer)->get('/admin/reminders?lang=en')->assertOk()->assertSee('Before an event');
    }
}
