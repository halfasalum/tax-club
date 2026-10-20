<?php

namespace Tests\Feature;

use App\Livewire\Admin\Events\CheckIn;
use App\Livewire\Admin\Events\Form;
use App\Livewire\Admin\Events\Show as AdminShow;
use App\Livewire\Member\Events\Index as MemberIndex;
use App\Livewire\Member\Events\Show as MemberShow;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Institution;
use App\Models\User;
use App\Notifications\EventChanged;
use App\Notifications\EventPlaceOffered;
use App\Notifications\EventRegistrationConfirmed;
use App\Support\EventManager;
use App\Support\MembershipCard;
use App\Support\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_event_goes_through_approval_before_members_see_it(): void
    {
        $organizer = $this->officerWith(['add_event', 'edit_event']);
        $approver = $this->officerWith(['review_event', 'publish_event']);
        $member = User::factory()->create();

        Livewire::actingAs($organizer)->test(Form::class)
            ->set('title', 'Tax Club Week workshop')
            ->set('description', 'A morning on why taxes matter.')
            ->set('location', 'Azania Secondary School hall')
            ->set('starts_at', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('capacity', 50)
            ->call('save', true)
            ->assertHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertSame(Review::IN_REVIEW, $event->editorial_status);
        $this->assertFalse($event->isVisibleTo($member));

        $this->expectsSelfApprovalToFail($event, $organizer);

        Livewire::actingAs($approver)->test(AdminShow::class, ['event' => $event])
            ->call('approve')
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertSame(Event::PUBLISHED, $event->fresh()->publication_status);
        Livewire::actingAs($member)->test(MemberIndex::class)->assertSee('Tax Club Week workshop');
        $this->get('/en/events')->assertSee('Tax Club Week workshop');
    }

    private function expectsSelfApprovalToFail(Event $event, User $organizer): void
    {
        $organizer->roles()->first()->controls()->attach(DB::table('module_controls')->where('slug', 'review_event')->value('control_id'));
        $organizer->refreshAccess();

        try {
            EventManager::approve($event, $organizer);
            $this->fail('Organizers must not approve their own events.');
        } catch (InvalidArgumentException) {
            $this->assertSame(Review::IN_REVIEW, $event->fresh()->editorial_status);
        }
    }

    public function test_scheduled_events_go_live_on_time(): void
    {
        $event = Event::factory()->draft()->create(['editorial_status' => Review::APPROVED]);
        EventManager::publish($event, User::factory()->create(), now()->addHour());
        $this->assertSame(Event::SCHEDULED, $event->fresh()->publication_status);

        $this->travel(2)->hours();
        EventManager::applySchedule();

        $this->assertSame(Event::PUBLISHED, $event->fresh()->publication_status);
    }

    public function test_audience_limits_who_sees_and_registers(): void
    {
        $mwanza = Institution::factory()->create(['region' => 'Mwanza']);
        $event = Event::factory()->create(['audience' => ['categories' => ['university'], 'regions' => ['Mwanza']]]);
        $fits = User::factory()->create(['user_type' => 'university', 'institution_id' => $mwanza->institution_id]);
        $otherRegion = User::factory()->create(['user_type' => 'university', 'institution_id' => Institution::factory()->create(['region' => 'Arusha'])->institution_id]);
        $secondary = User::factory()->create(['user_type' => 'secondary', 'institution_id' => $mwanza->institution_id]);

        $this->assertTrue($event->isVisibleTo($fits));
        $this->assertFalse($event->isVisibleTo($otherRegion));
        $this->assertFalse($event->isVisibleTo($secondary));

        $this->expectException(InvalidArgumentException::class);
        EventManager::register($event, $secondary);
    }

    public function test_full_events_use_the_waitlist_and_promote_when_a_place_frees_up(): void
    {
        Notification::fake();
        $event = Event::factory()->create(['capacity' => 1, 'waitlist_enabled' => true]);
        [$first, $second, $third] = User::factory()->count(3)->create();

        $this->assertSame(EventRegistration::REGISTERED, EventManager::register($event, $first)->status);
        $this->assertSame(EventRegistration::WAITLISTED, EventManager::register($event, $second)->status);
        $this->assertSame(2, EventManager::register($event, $third)->waitlistPosition());
        Notification::assertSentTo($second, EventRegistrationConfirmed::class, fn ($notification) => $notification->waitlisted);

        EventManager::cancelRegistration($event, $first);

        $this->assertSame(EventRegistration::REGISTERED, EventManager::registrationFor($event, $second)->status);
        $this->assertSame(1, EventManager::registrationFor($event, $third)->waitlistPosition());
        Notification::assertSentTo($second, EventPlaceOffered::class);
    }

    public function test_full_event_without_waitlist_refuses_registrations(): void
    {
        $event = Event::factory()->create(['capacity' => 1, 'waitlist_enabled' => false]);
        EventManager::register($event, User::factory()->create());

        $member = User::factory()->create();
        Livewire::actingAs($member)->test(MemberShow::class, ['slug' => $event->slug])
            ->assertSee('This event is full.');
    }

    public function test_registration_needs_the_answer_and_closes_at_the_cut_off(): void
    {
        $event = Event::factory()->create(['registration_question' => 'Which school?', 'registration_closes_at' => now()->addDay()]);
        $member = User::factory()->create();

        Livewire::actingAs($member)->test(MemberShow::class, ['slug' => $event->slug])
            ->call('register')
            ->assertHasErrors('register')
            ->set('answer', 'Azania')
            ->call('register')
            ->assertSee('You are registered');

        $this->assertSame('Azania', EventManager::registrationFor($event, $member)->answer);

        $this->travel(2)->days();
        $this->assertSame('Registration for this event has closed.', EventManager::registrationProblem($event->fresh(), User::factory()->create()));
    }

    public function test_officer_checks_members_in_with_the_card_qr_or_number(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $officer = $this->officerWith(['check_in_attendees']);
        $byQr = User::factory()->create();
        $byNumber = User::factory()->create();
        $this->registered($event, $byQr);
        $this->registered($event, $byNumber);

        Livewire::actingAs($officer)->test(CheckIn::class, ['event' => $event])
            ->set('input', MembershipCard::verifyUrl($byQr))
            ->call('check')
            ->assertSee('Checked in')
            ->assertSee($byQr->full_name)
            ->set('input', strtolower($byNumber->membership_id))
            ->call('check')
            ->assertSee('Checked in')
            ->set('input', $byNumber->membership_id)
            ->call('check')
            ->assertSee('Already checked in');

        $registration = EventManager::registrationFor($event, $byQr);
        $this->assertSame(EventRegistration::ATTENDED, $registration->status);
        $this->assertSame(EventRegistration::CHECK_IN_QR, $registration->check_in_method);
        $this->assertSame($officer->user_id, $registration->checked_in_by);
        $this->assertSame(15, (int) $byQr->fresh()->total_points);
    }

    public function test_walk_ins_need_confirmation_and_invalid_cards_are_refused(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $officer = $this->officerWith(['check_in_attendees']);
        $walkIn = User::factory()->create();
        $suspended = User::factory()->create(['account_status' => User::STATUS_SUSPENDED]);

        $component = Livewire::actingAs($officer)->test(CheckIn::class, ['event' => $event])
            ->set('input', $walkIn->membership_id)
            ->call('check')
            ->assertSee('Not registered for this event');
        $this->assertNull(EventManager::registrationFor($event, $walkIn));

        $component->call('admitWalkIn')->assertSee('Checked in');
        $this->assertTrue(EventManager::registrationFor($event, $walkIn)->walk_in);

        $component->set('input', $suspended->membership_id)->call('check')->assertSee('Membership not valid');
    }

    public function test_check_in_is_closed_before_the_event(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addDays(3)]);
        $this->expectException(InvalidArgumentException::class);
        EventManager::checkIn($event, User::factory()->create()->membership_id, $this->officerWith(['check_in_attendees']));
    }

    public function test_attendance_corrections_need_a_reason_and_take_back_points(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $officer = $this->officerWith(['check_in_attendees', 'view_event_attendees', 'add_event']);
        $member = User::factory()->create();
        EventManager::checkIn($event, $member->membership_id, $officer, allowWalkIn: true);
        $registration = EventManager::registrationFor($event, $member);

        Livewire::actingAs($officer)->test(AdminShow::class, ['event' => $event])
            ->set('listView', 'attended')
            ->call('startCorrection', $registration->id)
            ->call('saveCorrection')
            ->assertHasErrors('action')
            ->set('correctionReason', 'Scanned the wrong card')
            ->call('saveCorrection')
            ->assertSee('Attendance removed.');

        $this->assertSame(EventRegistration::REGISTERED, $registration->fresh()->status);
        $this->assertSame(0, (int) $member->fresh()->total_points);
        $this->assertDatabaseHas('audit_logs', ['action' => 'event.attendance_corrected', 'reason' => 'Scanned the wrong card']);
    }

    public function test_postponing_and_cancelling_tell_registered_members(): void
    {
        Notification::fake();
        $event = Event::factory()->create();
        $member = User::factory()->create();
        EventManager::register($event, $member);
        $officer = $this->officerWith(['publish_event']);

        EventManager::postpone($event, $officer, 'Venue under repair', now()->addMonth(), now()->addMonth()->addHours(3));
        Notification::assertSentTo($member, EventChanged::class, fn ($notification) => str_contains($notification->change, 'moved'));
        $this->assertSame(Event::PUBLISHED, $event->fresh()->publication_status);

        EventManager::cancel($event->fresh(), $officer, 'Exams week');
        $this->assertSame(Event::CANCELLED, $event->fresh()->publication_status);
        Notification::assertSentTo($member, EventChanged::class, fn ($notification) => $notification->reason === 'Exams week');
    }

    public function test_closing_produces_the_participation_summary_and_export(): void
    {
        $event = Event::factory()->ended()->create(['capacity' => 10]);
        $officer = $this->officerWith(['publish_event', 'view_event_attendees']);
        $came = User::factory()->create(['user_type' => 'university']);
        $absent = User::factory()->create(['user_type' => 'secondary']);
        foreach ([$came, $absent] as $member) {
            EventRegistration::create(['event_id' => $event->event_id, 'user_id' => $member->user_id, 'status' => EventRegistration::REGISTERED]);
        }
        EventManager::registrationFor($event, $came)->forceFill(['status' => EventRegistration::ATTENDED, 'check_in_method' => 'qr', 'checked_in_at' => now()])->save();

        Livewire::actingAs($officer)->test(AdminShow::class, ['event' => $event])
            ->set('closingNotes', 'Good turnout from universities.')
            ->call('close')
            ->assertSee('Closed. The participation summary is final.');

        $summary = EventManager::summary($event->fresh());
        $this->assertSame(2, $summary['registered']);
        $this->assertSame(1, $summary['attended']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(50, $summary['rate']);
        $this->assertSame(['registered' => 1, 'attended' => 1], $summary['by_category']['university']);

        $csv = $this->actingAs($officer)->get(route('admin.events.export', $event))->assertOk()->streamedContent();
        $this->assertStringContainsString($came->membership_id, $csv);
    }

    public function test_updates_reach_registered_members_and_private_materials_stay_private(): void
    {
        Storage::fake('local');
        $event = Event::factory()->create();
        $organizer = $this->officerWith(['add_event']);
        $registered = User::factory()->create();
        $other = User::factory()->create();
        EventManager::register($event, $registered);

        $update = EventManager::postUpdate($event, ['kind' => 'material', 'title' => 'Programme', 'registered_only' => true], $organizer, UploadedFile::fake()->create('programme.pdf', 20, 'application/pdf'));

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $registered->user_id]);
        $this->actingAs($registered)->get(route('events.material', [$event, $update]))->assertOk();
        $this->actingAs($other)->get(route('events.material', [$event, $update]))->assertNotFound();
        Livewire::actingAs($other)->test(MemberShow::class, ['slug' => $event->slug])->assertDontSee('Programme');
    }

    public function test_api_registration_and_check_in(): void
    {
        $event = Event::factory()->create(['capacity' => 5, 'starts_at' => now()->addHours(2), 'registration_closes_at' => now()->addHour()]);
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.0.places_left', 5);
        $this->actingAs($member, 'sanctum')->postJson("/api/v1/events/{$event->event_id}/register")->assertCreated()->assertJsonPath('data.status', 'registered');
        $this->actingAs($member, 'sanctum')->postJson("/api/v1/events/{$event->event_id}/check-in", ['card' => $member->membership_id])->assertForbidden();

        $officer = $this->officerWith(['check_in_attendees']);
        $this->actingAs($officer, 'sanctum')->postJson("/api/v1/events/{$event->event_id}/check-in", ['card' => MembershipCard::verifyUrl($member)])
            ->assertOk()
            ->assertJsonPath('data.result', 'checked_in')
            ->assertJsonPath('data.member.membership_id', $member->membership_id);
    }

    private function registered(Event $event, User $member): EventRegistration
    {
        return EventRegistration::create(['event_id' => $event->event_id, 'user_id' => $member->user_id, 'status' => EventRegistration::REGISTERED]);
    }

    public function test_admin_screens_render(): void
    {
        $officer = $this->officerWith(['add_event', 'review_event', 'publish_event', 'view_event_attendees', 'check_in_attendees']);
        $event = Event::factory()->happeningNow()->create();

        $this->actingAs($officer)->get('/admin/events?lang=en')->assertOk()->assertSee($event->title);
        $this->actingAs($officer)->get("/admin/events/{$event->event_id}?lang=en")->assertOk()->assertSee('Participation');
        $this->actingAs($officer)->get("/admin/events/{$event->event_id}/check-in?lang=en")->assertOk()->assertSee('Scan the card QR code');
        $this->actingAs($officer)->get('/admin/events/new?lang=en')->assertOk();
        $this->actingAs(User::factory()->create())->get('/app/events?lang=en')->assertOk()->assertSee($event->title);
    }
}
