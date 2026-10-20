<?php

namespace Tests\Feature;

use App\Livewire\Admin\Feedback\Show as AdminShow;
use App\Livewire\Member\Feedback\Index as MemberIndex;
use App\Livewire\Member\Feedback\Show as MemberShow;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackAssigned;
use App\Notifications\FeedbackUpdated;
use App\Support\FeedbackDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_member_sends_feedback_and_gets_a_reference(): void
    {
        $member = User::factory()->create();
        $event = Event::factory()->create(['title' => 'Mwanza workshop']);

        Livewire::actingAs($member)->test(MemberIndex::class, ['about' => 'event:'.$event->event_id])
            ->assertSet('category', 'event')
            ->assertSee('Mwanza workshop')
            ->set('subject', 'Great workshop')
            ->set('message', 'The VAT session was very clear, thank you.')
            ->call('send')
            ->assertRedirect(route('member.feedback.show', 'FB-'.now()->format('Y').'-00001'));

        $feedback = Feedback::firstOrFail();
        $this->assertSame('events', $feedback->area);
        $this->assertSame('event', $feedback->related_type);
        $this->assertSame(Feedback::SUBMITTED, $feedback->status);
    }

    public function test_officer_classifies_assigns_responds_and_closes(): void
    {
        Notification::fake();
        $member = User::factory()->create();
        $feedback = FeedbackDesk::submit($member, ['category' => 'problem', 'subject' => 'Quiz timer', 'message' => 'The timer stopped on my phone.']);
        $lead = $this->officerWith(['view_feedback', 'manage_feedback']);
        $support = $this->officerWith(['view_feedback', 'respond_to_feedback']);

        Livewire::actingAs($lead)->test(AdminShow::class, ['feedback' => $feedback])
            ->set('priority', 'high')
            ->call('classify')
            ->set('assignee', $support->user_id)
            ->call('assign')
            ->assertSee('Assigned to '.$support->full_name);
        Notification::assertSentTo($support, FeedbackAssigned::class);

        Livewire::actingAs($support)->test(AdminShow::class, ['feedback' => $feedback])
            ->set('note', 'Reproduced on Android 9')
            ->call('addNote')
            ->set('response', 'Thank you. We fixed the timer; please update the app.')
            ->call('respond')
            ->assertSee('Response sent to the member.');

        $feedback->refresh();
        $this->assertSame(Feedback::CLOSED, $feedback->status);
        $this->assertSame('high', $feedback->priority);
        Notification::assertSentTo($member, FeedbackUpdated::class);

        // The member sees the response but not the internal note.
        Livewire::actingAs($member)->test(MemberShow::class, ['reference' => $feedback->reference])
            ->assertSee('We fixed the timer')
            ->assertDontSee('Android 9');
    }

    public function test_safety_concerns_go_to_safeguarding_officers_only(): void
    {
        Notification::fake();
        $safeguarding = $this->officerWith(['view_feedback', 'handle_safeguarding_concerns', 'respond_to_feedback']);
        $general = $this->officerWith(['view_feedback', 'manage_feedback']);
        $feedback = FeedbackDesk::submit(User::factory()->minor()->create(), ['category' => 'concern', 'subject' => 'Message from a stranger', 'message' => 'Someone asked me for my phone number.']);

        $this->assertSame('high', $feedback->priority);
        $this->assertSame(Feedback::SAFEGUARDING, $feedback->area);
        Notification::assertSentTo($safeguarding, FeedbackAssigned::class);
        $this->assertFalse(Feedback::visibleTo($general)->whereKey($feedback->feedback_id)->exists());
        $this->actingAs($general)->get(route('admin.feedback.show', $feedback))->assertNotFound();

        $this->expectException(InvalidArgumentException::class);
        FeedbackDesk::assign($feedback, $general, $safeguarding);
    }

    public function test_members_see_only_their_own_feedback_and_can_add_information(): void
    {
        $member = User::factory()->create();
        $feedback = FeedbackDesk::submit($member, ['category' => 'suggestion', 'subject' => 'More videos', 'message' => 'Please add more short videos.']);

        $this->actingAs(User::factory()->create())->get(route('member.feedback.show', $feedback->reference))->assertNotFound();

        Livewire::actingAs($member)->test(MemberShow::class, ['reference' => $feedback->reference])
            ->set('reply', 'Especially about EFD receipts.')
            ->call('sendReply')
            ->assertSee('Especially about EFD receipts.');
    }

    public function test_submissions_are_rate_limited(): void
    {
        $member = User::factory()->create();

        for ($i = 0; $i < FeedbackDesk::PER_HOUR; $i++) {
            FeedbackDesk::submit($member, ['category' => 'other', 'subject' => 'Test '.$i, 'message' => 'Message number '.$i]);
        }

        $this->expectException(InvalidArgumentException::class);
        FeedbackDesk::submit($member, ['category' => 'other', 'subject' => 'One more', 'message' => 'Too many messages']);
    }

    public function test_api_feedback_flow(): void
    {
        $member = User::factory()->create();

        $reference = $this->actingAs($member, 'sanctum')->postJson('/api/v1/feedback', ['category' => 'content', 'subject' => 'Typo in VAT lesson', 'message' => 'The second paragraph has a typo.'])
            ->assertCreated()
            ->json('data.reference');

        $this->actingAs($member, 'sanctum')->getJson("/api/v1/feedback/{$reference}")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.response', null);
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/feedback')->assertJsonCount(1, 'data');
    }

    public function test_admin_queue_renders(): void
    {
        FeedbackDesk::submit(User::factory()->create(), ['category' => 'problem', 'subject' => 'Login slow', 'message' => 'The login page takes long.']);

        $this->actingAs($this->officerWith(['view_feedback']))->get('/admin/feedback?lang=en')->assertOk()->assertSee('Login slow');
        $this->actingAs(User::factory()->create())->get('/app/feedback?lang=en')->assertOk()->assertSee('Send feedback');
    }
}
