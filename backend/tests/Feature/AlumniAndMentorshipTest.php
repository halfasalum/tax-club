<?php

namespace Tests\Feature;

use App\Livewire\Member\Alumni\Directory;
use App\Livewire\Member\Alumni\ProfileEdit;
use App\Livewire\Member\Mentorship\Index as MentorshipPage;
use App\Livewire\Member\Mentorship\Show as MentorshipShow;
use App\Models\AlumniStory;
use App\Models\ClubPatron;
use App\Models\Institution;
use App\Models\MentorProfile;
use App\Models\Mentorship;
use App\Models\MentorshipRequest;
use App\Models\ModerationCase;
use App\Models\TaxClub;
use App\Models\User;
use App\Notifications\MentorshipMatched;
use App\Notifications\MentorshipNeedsAttention;
use App\Support\AlumniNetwork;
use App\Support\MentorshipDesk;
use App\Support\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class AlumniAndMentorshipTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function alumnus(array $attributes = []): User
    {
        return User::factory()->create(['user_type' => 'alumni', 'alumni_since' => now()->subYear(), ...$attributes]);
    }

    public function test_alumni_list_themselves_and_adults_connect_to_see_contacts(): void
    {
        $alumnus = $this->alumnus(['full_name' => 'Juma Mussa', 'phone' => '+255713000111']);
        $student = User::factory()->create(['user_type' => 'university']);

        Livewire::actingAs($alumnus)->test(ProfileEdit::class)
            ->set('occupation', 'Tax consultant')
            ->set('field', 'Accounting')
            ->set('bio', 'I joined Tax Club in Form 3 and now help small businesses file returns.')
            ->set('show_in_directory', true)
            ->call('save')
            ->assertSee('Saved.');

        Livewire::actingAs($student)->test(Directory::class)
            ->assertSee('Juma Mussa')
            ->assertDontSee('+255713000111')
            ->set('connecting', $alumnus->user_id)
            ->set('message', 'I study accounting and would love advice.')
            ->call('connect')
            ->assertSee('Request sent.');

        $connection = AlumniNetwork::pendingRequestsFor($alumnus)->first();
        AlumniNetwork::respond($alumnus, $connection->id, true);

        Livewire::actingAs($student)->test(Directory::class)->assertSee('+255713000111');
    }

    public function test_minors_cannot_use_the_directory_and_profiles_reject_contact_details(): void
    {
        $this->alumnus();
        $minor = User::factory()->minor()->create();

        $this->actingAs($minor)->get(route('member.alumni.directory'))->assertForbidden();

        $this->expectException(InvalidArgumentException::class);
        AlumniNetwork::saveProfile($this->alumnus(), ['bio' => 'Call me on 0712345678', 'show_in_directory' => true]);
    }

    public function test_alumni_stories_are_published_after_review(): void
    {
        $alumnus = $this->alumnus();
        $story = AlumniNetwork::submitStory($alumnus, 'From Tax Club to TRA', str_repeat('Tax Club showed me how taxes build our country. ', 4));
        $this->assertSame(Review::IN_REVIEW, $story->editorial_status);
        $this->assertSame(0, AlumniStory::published()->count());

        AlumniNetwork::approveStory($story, $this->officerWith(['review_alumni_stories']));
        $this->actingAs(User::factory()->create())->get(route('member.alumni.story', $story->slug).'?lang=en')->assertOk()->assertSee('From Tax Club to TRA');
    }

    public function test_mentor_is_approved_matched_and_sessions_are_logged(): void
    {
        Notification::fake();
        $mentor = $this->alumnus();
        $mentee = User::factory()->create(['user_type' => 'university']);
        $coordinator = $this->officerWith(['manage_mentorships', 'review_mentors']);

        Livewire::actingAs($mentor)->test(MentorshipPage::class)
            ->set('expertise', ['accounting', 'careers_in_tax'])
            ->set('bio', 'Accountant with eight years of experience in tax compliance for small businesses.')
            ->call('apply')
            ->assertHasErrors('form')
            ->set('declaration', true)
            ->call('apply')
            ->assertSee('TRA will review your application.');

        $profile = MentorProfile::firstOrFail();
        MentorshipDesk::decideApplication($profile, $coordinator, true);

        Livewire::actingAs($mentee)->test(MentorshipPage::class)
            ->set('interests', ['accounting'])
            ->set('goals', 'I want to understand how accountants work with TRA.')
            ->call('requestMentor')
            ->assertSee('Request sent.');

        $request = MentorshipRequest::firstOrFail();
        $suggestion = MentorshipDesk::suggestions($request)->first();
        $this->assertSame(1, $suggestion['overlap']);

        $mentorship = MentorshipDesk::match($request, $profile, $coordinator);
        Notification::assertSentTo($mentee, MentorshipMatched::class);
        Notification::assertSentTo($mentor, MentorshipMatched::class);

        Livewire::actingAs($mentee)->test(MentorshipShow::class, ['mentorship' => $mentorship])
            ->set('topic', 'Choosing a degree')
            ->set('summary', 'Compared accounting and finance courses.')
            ->call('logSession')
            ->assertSee('Session recorded.')
            ->assertSee('Choosing a degree')
            ->set('outcome', 'goals_met')
            ->set('rating', 5)
            ->call('close')
            ->assertSee('Mentorship closed.');

        $this->assertSame(Mentorship::COMPLETED, $mentorship->fresh()->status);
        $this->assertSame(5, $mentorship->fresh()->mentee_rating);
        $this->actingAs(User::factory()->create())->get(route('member.mentorship.show', $mentorship))->assertNotFound();
    }

    public function test_minors_need_confirmed_consent_and_a_cleared_mentor_and_their_patron_supervises(): void
    {
        Notification::fake();
        $school = Institution::factory()->create();
        $club = TaxClub::factory()->create(['institution_id' => $school->institution_id]);
        $patron = User::factory()->create(['user_type' => 'staff']);
        ClubPatron::create(['tax_club_id' => $club->tax_club_id, 'user_id' => $patron->user_id, 'starts_on' => now()->subYear()]);
        $minor = User::factory()->minor()->create(['institution_id' => $school->institution_id]);
        $coordinator = $this->officerWith(['manage_mentorships', 'review_mentors']);

        $mentor = MentorshipDesk::apply($this->alumnus(), ['expertise' => ['study_skills'], 'bio' => 'Teacher and Tax Club alumna who loves helping students prepare for exams.', 'mentors_minors' => true, 'declaration' => true]);
        MentorshipDesk::decideApplication($mentor, $coordinator, true);
        $this->assertSame('pending', $mentor->fresh()->background_check);

        try {
            MentorshipDesk::request($minor, ['interests' => ['study_skills'], 'goals' => 'Help with exam preparation please.']);
            $this->fail('Minors need guardian consent.');
        } catch (InvalidArgumentException) {
        }

        $request = MentorshipDesk::request($minor, ['interests' => ['study_skills'], 'goals' => 'Help with exam preparation please.', 'guardian_consent' => true]);

        foreach (['consent' => fn () => MentorshipDesk::match($request, $mentor->fresh(), $coordinator)] as $attempt) {
            try {
                $attempt();
                $this->fail('A mentor without a cleared check must not be matched with a minor.');
            } catch (InvalidArgumentException) {
            }
        }

        MentorshipDesk::recordBackgroundCheck($mentor, true, $coordinator, 'Police clearance ref 2026/123');
        MentorshipDesk::confirmConsent($request, $patron);
        $mentorship = MentorshipDesk::match($request->fresh(), $mentor->fresh(), $coordinator);

        $this->assertSame($patron->user_id, $mentorship->supervisor_id);
        Notification::assertSentTo($patron, MentorshipNeedsAttention::class);
        $this->assertTrue(MentorshipDesk::canView($patron, $mentorship));

        MentorshipDesk::raiseConcern($mentorship, $minor, 'The mentor asked to meet outside school.');
        $this->assertTrue(ModerationCase::where('subject_type', 'mentorship')->firstOrFail()->safeguarding);
    }

    public function test_inactive_mentorships_are_flagged_once(): void
    {
        Notification::fake();
        $coordinator = $this->officerWith(['manage_mentorships']);
        $mentorship = Mentorship::create(['mentor_id' => $this->alumnus()->user_id, 'mentee_id' => User::factory()->create()->user_id, 'status' => Mentorship::ACTIVE, 'started_at' => now()->subDays(40), 'last_activity_at' => now()->subDays(35)]);

        $this->assertSame(1, MentorshipDesk::flagInactive());
        $this->assertSame(0, MentorshipDesk::flagInactive());
        Notification::assertSentTo($coordinator, MentorshipNeedsAttention::class);
        $this->assertNotNull($mentorship);
    }

    public function test_screens_render(): void
    {
        $officer = $this->officerWith(['manage_mentorships', 'review_mentors', 'review_alumni_stories']);
        $alumnus = $this->alumnus();

        $this->actingAs($officer)->get('/admin/mentorship?lang=en')->assertOk()->assertSee('Requests');
        $this->actingAs($officer)->get('/admin/alumni/stories?lang=en')->assertOk();
        $this->actingAs($alumnus)->get('/app/alumni?lang=en')->assertOk()->assertSee('Share your story');
        $this->actingAs($alumnus)->get('/app/mentorship?lang=en')->assertOk()->assertSee('Become a mentor');
        $this->actingAs(User::factory()->create())->get('/app/mentorship?lang=en')->assertOk()->assertSee('Ask for a mentor');
    }
}
