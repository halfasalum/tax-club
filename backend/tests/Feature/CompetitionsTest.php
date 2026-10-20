<?php

namespace Tests\Feature;

use App\Livewire\Admin\Competitions\Form;
use App\Livewire\Admin\Competitions\Show as AdminShow;
use App\Livewire\Member\Competitions\Show as MemberShow;
use App\Models\Certificate;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\RewardAward;
use App\Models\User;
use App\Support\Certificates;
use App\Support\CompetitionDesk;
use App\Support\Review;
use App\Support\Rewards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\BuildsQuizzes;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class CompetitionsTest extends TestCase
{
    use BuildsQuizzes, CreatesOfficers, RefreshDatabase;

    private function submissionCompetition(array $attributes = []): Competition
    {
        $competition = CompetitionDesk::create([
            'title' => 'Essay: Why taxes matter',
            'description' => 'Write 500 words on how taxes build Tanzania.',
            'type' => 'submission',
            'submission_kind' => 'text',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'winners_count' => 2,
            ...$attributes,
        ], User::factory()->create(['user_type' => 'staff']));
        $competition->forceFill(['editorial_status' => Review::APPROVED, 'publication_status' => Competition::PUBLISHED])->save();

        return $competition;
    }

    public function test_competition_is_reviewed_before_publication(): void
    {
        $author = $this->officerWith(['create_competition']);
        $reviewer = $this->officerWith(['review_competition', 'publish_competition']);

        Livewire::actingAs($author)->test(Form::class)
            ->set('title', 'National Tax Quiz 2027')
            ->set('description', 'Answer 20 questions about Tanzanian taxes.')
            ->set('type', 'submission')
            ->set('submission_kind', 'link')
            ->set('starts_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(10)->format('Y-m-d\TH:i'))
            ->call('save', true)
            ->assertHasNoErrors();

        $competition = Competition::firstOrFail();
        $this->assertSame(Review::IN_REVIEW, $competition->editorial_status);

        Livewire::actingAs($reviewer)->test(AdminShow::class, ['competition' => $competition])
            ->call('approve')
            ->call('publish')
            ->assertSee('Published.');

        $this->assertSame(Competition::PUBLISHED, $competition->fresh()->publication_status);
    }

    public function test_members_register_submit_and_judges_score_and_results_are_approved(): void
    {
        $competition = $this->submissionCompetition(['ends_at' => now()->addHour()]);
        [$asha, $baraka, $chausiku] = User::factory()->count(3)->create();
        $trophy = Reward::create(['name_sw' => 'Kombe', 'name_en' => 'Trophy', 'type' => 'trophy', 'is_active' => true]);
        Rewards::setPrizes($competition, [['rank_from' => 1, 'rank_to' => 1, 'reward_id' => $trophy->id]], User::factory()->create());

        foreach ([$asha, $baraka, $chausiku] as $member) {
            Livewire::actingAs($member)->test(MemberShow::class, ['slug' => $competition->slug])
                ->call('register')
                ->assertSee('You are registered')
                ->set('text', 'Taxes pay for schools, roads and hospitals. '.$member->full_name)
                ->call('submitEntry')
                ->assertSee('Your entry was received.');
        }

        $judgeA = $this->officerWith(['judge_competition']);
        $judgeB = $this->officerWith(['judge_competition']);
        foreach ([[$asha, 90, 80], [$baraka, 70, 60], [$chausiku, 50, 40]] as [$member, $a, $b]) {
            $entry = CompetitionDesk::entryFor($competition, $member);
            CompetitionDesk::score($entry, $judgeA, $a);
            CompetitionDesk::score($entry, $judgeB, $b);
        }
        $this->assertSame(85.0, CompetitionDesk::entryFor($competition, $asha)->score);

        $this->travel(2)->hours();
        $organizer = $this->officerWith(['create_competition']);
        CompetitionDesk::submitResults($competition->fresh(), $organizer);

        try {
            CompetitionDesk::approveResults($competition->fresh(), $organizer);
            $this->fail('Organizers must not approve their own results.');
        } catch (InvalidArgumentException) {
        }

        $approver = $this->officerWith(['approve_results']);
        Livewire::actingAs($approver)->test(AdminShow::class, ['competition' => $competition->fresh()])
            ->call('approveResults')
            ->assertSee('Results published.');

        $this->assertSame(1, CompetitionDesk::entryFor($competition, $asha)->rank);
        $this->assertSame(3, CompetitionDesk::entryFor($competition, $chausiku)->rank);
        $this->assertSame('competition_winner', Certificate::where('user_id', $asha->user_id)->value('kind'));
        $this->assertSame('competition', Certificate::where('user_id', $chausiku->user_id)->value('kind'));
        $this->assertSame(RewardAward::APPROVED, RewardAward::where('user_id', $asha->user_id)->value('status'));
        $this->assertSame(0, RewardAward::where('user_id', $baraka->user_id)->count());

        Livewire::actingAs($baraka)->test(MemberShow::class, ['slug' => $competition->slug])->assertSee('Results')->assertSee($asha->publicName());
    }

    public function test_quiz_competition_ranks_by_best_attempt_during_the_competition(): void
    {
        $quiz = $this->quizWithQuestions(2);
        $competition = CompetitionDesk::create(['title' => 'Quiz challenge week', 'description' => 'Fast tax quiz', 'type' => 'quiz', 'quiz_id' => $quiz->quiz_id, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subHour()], User::factory()->create());
        $competition->forceFill(['editorial_status' => Review::APPROVED, 'publication_status' => Competition::PUBLISHED])->save();
        $fast = User::factory()->create();
        $slow = User::factory()->create();

        foreach ([[$fast, 100, now()->subDay()], [$slow, 100, now()->subHours(3)], [$slow, 50, now()->subHours(2)]] as [$member, $percent, $at]) {
            CompetitionEntry::firstOrCreate(['competition_id' => $competition->competition_id, 'user_id' => $member->user_id], ['status' => 'registered', 'registered_at' => now()->subDays(2)]);
            QuizAttempt::forceCreate(['quiz_id' => $quiz->quiz_id, 'user_id' => $member->user_id, 'status' => QuizAttempt::SUBMITTED, 'score' => $percent / 50, 'max_score' => 2, 'percent' => $percent, 'submitted_at' => $at, 'started_at' => $at, 'attempt_number' => 1]);
        }

        CompetitionDesk::rank($competition);

        $this->assertSame(1, CompetitionDesk::entryFor($competition, $fast)->rank);
        $this->assertSame(2, CompetitionDesk::entryFor($competition, $slow)->rank);
    }

    public function test_registration_rules(): void
    {
        $competition = $this->submissionCompetition(['max_participants' => 1, 'audience' => ['categories' => ['university']]]);
        $student = User::factory()->create(['user_type' => 'university']);
        $pupil = User::factory()->create(['user_type' => 'secondary']);

        $this->assertNotNull(CompetitionDesk::registrationProblem($competition, $pupil));
        CompetitionDesk::register($competition, $student);
        $this->assertSame('This competition is full.', CompetitionDesk::registrationProblem($competition, User::factory()->create(['user_type' => 'university'])));
    }

    public function test_rewards_that_cost_money_need_another_officer_and_delivery_evidence(): void
    {
        $member = User::factory()->create();
        $airtime = Reward::create(['name_sw' => 'Muda wa maongezi', 'name_en' => 'Airtime', 'type' => 'airtime', 'value_tzs' => 5000, 'stock' => 1, 'needs_approval' => true, 'is_active' => true]);
        $officer = $this->officerWith(['award_rewards']);
        $award = Rewards::award($member, $airtime, 'Best question of the month', $officer);
        $this->assertSame(RewardAward::PENDING_APPROVAL, $award->status);

        try {
            Rewards::approve($award, $officer);
            $this->fail('The same officer must not approve.');
        } catch (InvalidArgumentException) {
        }

        Rewards::approve($award, $this->officerWith(['approve_rewards']));
        Rewards::issue($award->fresh(), $officer);
        $this->assertSame(0, $airtime->fresh()->stock);

        $this->expectException(InvalidArgumentException::class);
        Rewards::deliver($award->fresh(), $officer, '');
    }

    public function test_certificates_are_verified_publicly_and_can_be_withdrawn(): void
    {
        $member = User::factory()->create(['full_name' => 'Neema Kileo']);
        $certificate = Certificates::issue($member, 'other', 'Cheti cha Pongezi', 'Certificate of Recognition', 'Tax Club Week volunteer');

        $this->get('/en/certificates/verify/'.$certificate->token)->assertOk()->assertSee('Genuine certificate')->assertSee('Neema K.')->assertDontSee('Kileo');
        $this->get('/en/certificates/verify?number='.$certificate->number)->assertSee('Genuine certificate');

        Certificates::revoke($certificate, 'Issued by mistake', $this->officerWith(['issue_certificates']));
        $this->get('/en/certificates/verify/'.$certificate->token)->assertSee('This certificate was withdrawn');
        $this->get('/en/certificates/verify?number=TCC-00-XXXX-XXXX')->assertSee('Certificate not found');
    }

    public function test_screens_render(): void
    {
        $competition = $this->submissionCompetition();
        $officer = $this->officerWith(['create_competition', 'review_competition', 'publish_competition', 'judge_competition', 'approve_results', 'manage_rewards', 'award_rewards', 'issue_certificates']);
        $member = User::factory()->create();
        $certificate = Certificates::issue($member, 'other', 'Cheti', 'Certificate', 'Volunteer');

        $this->actingAs($officer)->get('/admin/competitions?lang=en')->assertOk()->assertSee($competition->title);
        $this->actingAs($officer)->get('/admin/competitions/'.$competition->competition_id.'?lang=en')->assertOk()->assertSee('Prizes');
        $this->actingAs($officer)->get('/admin/rewards?lang=en')->assertOk()->assertSee('Give a reward');
        $this->actingAs($officer)->get('/admin/certificates?lang=en')->assertOk();
        $this->actingAs($member)->get('/app/competitions?lang=en')->assertOk()->assertSee($competition->title);
        $this->actingAs($member)->get('/app/achievements?lang=en')->assertOk()->assertSee('Volunteer');
        $this->actingAs($member)->get('/app/certificates/'.$certificate->certificate_id.'?lang=en')->assertOk()->assertSee('<svg', false);
    }
}
