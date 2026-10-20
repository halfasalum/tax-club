<?php

namespace Tests\Feature;

use App\Livewire\Admin\Opportunities\Form;
use App\Livewire\Admin\Opportunities\Report;
use App\Livewire\Admin\Opportunities\Show as AdminShow;
use App\Livewire\Member\Opportunities\Index as MemberIndex;
use App\Livewire\Member\Opportunities\Show as MemberShow;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\OpportunityBoard;
use App\Support\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class OpportunitiesTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    public function test_opportunity_is_reviewed_before_members_see_it(): void
    {
        $author = $this->officerWith(['add_opportunity', 'edit_opportunity']);
        $reviewer = $this->officerWith(['review_opportunity', 'publish_opportunity']);
        $member = User::factory()->create(['user_type' => 'university']);

        Livewire::actingAs($author)->test(Form::class)
            ->set('title', 'TRA graduate internship 2027')
            ->set('description', 'Twelve months in domestic revenue.')
            ->set('eligibility', 'Final-year university students.')
            ->set('deadline', now()->addMonth()->toDateString())
            ->set('apply_url', 'https://www.tra.go.tz/vacancies')
            ->set('audienceCategories', ['university'])
            ->call('save', true)
            ->assertHasNoErrors();

        $opportunity = Opportunity::firstOrFail();
        $this->assertSame(Review::IN_REVIEW, $opportunity->editorial_status);
        $this->assertTrue(OpportunityBoard::browse($member)->isEmpty());

        Livewire::actingAs($reviewer)->test(AdminShow::class, ['opportunity' => $opportunity])
            ->call('approve')
            ->call('publish')
            ->assertSee('Published.');

        Livewire::actingAs($member)->test(MemberIndex::class)->assertSee('TRA graduate internship 2027');
        Livewire::actingAs(User::factory()->create(['user_type' => 'secondary']))->test(MemberIndex::class)->assertDontSee('TRA graduate internship 2027');
    }

    public function test_publishing_needs_an_application_channel(): void
    {
        $opportunity = Opportunity::factory()->create(['apply_url' => null]);
        $opportunity->forceFill(['editorial_status' => Review::APPROVED])->save();

        $this->expectException(InvalidArgumentException::class);
        OpportunityBoard::publish($opportunity, User::factory()->create());
    }

    public function test_it_closes_at_the_deadline_and_is_archived_later(): void
    {
        $opportunity = Opportunity::factory()->published()->create(['deadline' => now()->addDays(2)]);
        $this->assertTrue($opportunity->fresh()->isClosingSoon());

        $this->travel(3)->days();
        OpportunityBoard::applySchedule();
        $this->assertSame(Opportunity::CLOSED, $opportunity->fresh()->publication_status);

        $this->travel(OpportunityBoard::ARCHIVE_AFTER_DAYS + 1)->days();
        OpportunityBoard::applySchedule();
        $this->assertSame(Opportunity::ARCHIVED, $opportunity->fresh()->publication_status);
    }

    public function test_withdrawing_needs_a_reason(): void
    {
        $opportunity = Opportunity::factory()->published()->create();
        $officer = $this->officerWith(['publish_opportunity']);

        Livewire::actingAs($officer)->test(AdminShow::class, ['opportunity' => $opportunity])
            ->call('withdraw')
            ->assertHasErrors('action')
            ->set('reason', 'The organization cancelled the programme')
            ->call('withdraw')
            ->assertSee('Withdrawn.');

        $this->assertSame(Opportunity::WITHDRAWN, $opportunity->fresh()->publication_status);
    }

    public function test_member_searches_saves_and_applies(): void
    {
        $member = User::factory()->create();
        $scholarship = Opportunity::factory()->published()->create(['title' => 'Customs scholarship', 'type' => 'scholarship']);
        Opportunity::factory()->published()->create(['title' => 'Volunteer at Tax Day', 'type' => 'volunteer']);

        Livewire::actingAs($member)->test(MemberIndex::class)
            ->set('type', 'scholarship')
            ->assertSee('Customs scholarship')
            ->assertDontSee('Volunteer at Tax Day')
            ->set('type', '')
            ->set('search', 'Tax Day')
            ->assertSee('Volunteer at Tax Day')
            ->assertDontSee('Customs scholarship');

        Livewire::actingAs($member)->test(MemberShow::class, ['slug' => $scholarship->slug])
            ->call('toggleSave')
            ->assertSee('Saved');
        $this->assertTrue(OpportunityBoard::isSaved($scholarship, $member));

        $this->actingAs($member)->get(route('member.opportunities.apply', $scholarship->slug))->assertRedirect('https://www.tra.go.tz/vacancies');

        $engagement = OpportunityBoard::engagement($scholarship);
        $this->assertSame(['viewers' => 1, 'saves' => 1, 'applies' => 1, 'saved_now' => 1], $engagement);
    }

    public function test_engagement_report_aggregates_without_names(): void
    {
        $officer = $this->officerWith(['view_opportunity_reports']);
        $opportunity = Opportunity::factory()->published()->create(['title' => 'Data analyst internship']);
        $member = User::factory()->create(['full_name' => 'Rehema Juma']);
        OpportunityBoard::record($opportunity, $member, 'view');
        OpportunityBoard::record($opportunity, $member, 'view');
        OpportunityBoard::record($opportunity, $member, 'apply');

        $report = OpportunityBoard::report(now()->subDay(), now()->addDay());
        $this->assertSame(1, $report['totals']['views']);
        $this->assertSame(1, $report['totals']['applies']);

        Livewire::actingAs($officer)->test(Report::class)
            ->assertSee('Data analyst internship')
            ->assertDontSee('Rehema Juma');
    }

    public function test_api_browse_save_and_apply(): void
    {
        $member = User::factory()->create();
        $opportunity = Opportunity::factory()->published()->create();

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/opportunities')->assertOk()->assertJsonPath('data.0.opportunity_id', $opportunity->opportunity_id);
        $this->actingAs($member, 'sanctum')->postJson("/api/v1/opportunities/{$opportunity->opportunity_id}/save")->assertJsonPath('data.saved', true);
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/opportunities?saved=1')->assertJsonCount(1, 'data');
        $this->actingAs($member, 'sanctum')->postJson("/api/v1/opportunities/{$opportunity->opportunity_id}/apply")->assertJsonPath('data.apply_to', 'https://www.tra.go.tz/vacancies');
    }

    public function test_admin_screens_render(): void
    {
        $officer = $this->officerWith(['add_opportunity', 'review_opportunity', 'publish_opportunity', 'view_opportunity_reports']);
        $opportunity = Opportunity::factory()->published()->create();

        $this->actingAs($officer)->get('/admin/opportunities?lang=en')->assertOk()->assertSee($opportunity->title);
        $this->actingAs($officer)->get("/admin/opportunities/{$opportunity->opportunity_id}?lang=en")->assertOk()->assertSee('Engagement');
        $this->actingAs($officer)->get('/admin/opportunities/report?lang=en')->assertOk();
        $this->actingAs($officer)->get('/admin/opportunities/new?lang=en')->assertOk();
        $this->actingAs(User::factory()->create())->get('/app/opportunities?lang=en')->assertOk()->assertSee($opportunity->title);
    }
}
