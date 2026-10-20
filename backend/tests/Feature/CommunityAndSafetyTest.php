<?php

namespace Tests\Feature;

use App\Livewire\Admin\Moderation\Show as CaseShow;
use App\Livewire\Member\Community\Post as PostPage;
use App\Livewire\Member\Community\Space as SpacePage;
use App\Livewire\Safety\ReportButton;
use App\Models\CommunityPost;
use App\Models\CommunitySpace;
use App\Models\Institution;
use App\Models\ModerationCase;
use App\Models\User;
use App\Notifications\CommunityReply;
use App\Notifications\ModerationAlert;
use App\Notifications\ModerationWarning;
use App\Notifications\ReportReviewed;
use App\Support\Community;
use App\Support\SafetyDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class CommunityAndSafetyTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function national(): CommunitySpace
    {
        return CommunitySpace::create(['name' => 'Tax Club Tanzania', 'type' => 'national', 'status' => 'active']);
    }

    public function test_members_post_and_comment_and_authors_hear_about_replies(): void
    {
        Notification::fake();
        $space = $this->national();
        $author = User::factory()->create();
        $reader = User::factory()->create();

        Livewire::actingAs($author)->test(SpacePage::class, ['space' => $space])
            ->set('title', 'Why do we pay VAT?')
            ->set('body', 'Our club is preparing a talk on VAT. Ideas welcome!')
            ->call('publish')
            ->assertSee('Posted.')
            ->assertSee('Why do we pay VAT?');

        $post = CommunityPost::firstOrFail();
        Livewire::actingAs($reader)->test(PostPage::class, ['post' => $post])
            ->set('comment', 'Start with how VAT pays for schools.')
            ->call('addComment')
            ->assertSee('Start with how VAT pays for schools.');

        $this->assertSame(1, $post->fresh()->comments_count);
        Notification::assertSentTo($author, CommunityReply::class);
    }

    public function test_contact_details_and_blocked_words_are_held_for_a_moderator(): void
    {
        $space = $this->national();
        $minor = User::factory()->minor()->create();

        $post = Community::post($space, $minor, null, 'Nitumie ujumbe WhatsApp 0712 345 678');
        $this->assertSame(CommunityPost::HELD, $post->status);
        $case = ModerationCase::where('subject_id', $post->post_id)->firstOrFail();
        $this->assertSame('filter', $case->source);
        $this->assertTrue($case->safeguarding);
        $this->assertTrue($case->involves_minor);

        $other = User::factory()->create();
        $this->assertSame(CommunityPost::HELD, Community::post($space, $other, null, 'You are so stupid')->status);
        $this->assertSame(CommunityPost::PUBLISHED, Community::post($space, $other, null, 'Great session today on EFD receipts')->status);

        // Held posts are not shown to others.
        Livewire::actingAs($other)->test(SpacePage::class, ['space' => $space])->assertDontSee('WhatsApp');
    }

    public function test_reports_group_into_one_case_and_hide_content_after_three_reporters(): void
    {
        Notification::fake();
        $moderator = $this->officerWith(['moderate_community']);
        $space = $this->national();
        $post = Community::post($space, User::factory()->create(), null, 'Buy cheap phones here, best prices');

        foreach (User::factory()->count(3)->create() as $reporter) {
            SafetyDesk::report($reporter, 'post', $post->post_id, 'spam', 'Advertising');
        }

        $this->assertSame(1, ModerationCase::count());
        $case = ModerationCase::firstOrFail();
        $this->assertSame(3, $case->reports_count);
        $this->assertSame(CommunityPost::HIDDEN, $post->fresh()->status);
        Notification::assertSentTo($moderator, ModerationAlert::class);

        $this->expectException(InvalidArgumentException::class);
        SafetyDesk::report($post->author, 'post', $post->post_id, 'spam');
    }

    public function test_grooming_reports_go_to_safeguarding_officers_only(): void
    {
        Notification::fake();
        $safeguarding = $this->officerWith(['handle_safeguarding_concerns']);
        $moderator = $this->officerWith(['moderate_community']);
        $space = $this->national();
        $post = Community::post($space, User::factory()->create(), null, 'Let us meet after school, I can help you');

        Livewire::actingAs(User::factory()->minor()->create())->test(ReportButton::class, ['type' => 'post', 'subjectId' => $post->post_id])
            ->set('category', 'grooming')
            ->call('send')
            ->assertSee('Reported. Thank you.');

        $case = ModerationCase::firstOrFail();
        $this->assertSame('urgent', $case->priority);
        $this->assertTrue($case->safeguarding);
        Notification::assertSentTo($safeguarding, ModerationAlert::class);
        $this->assertFalse(ModerationCase::visibleTo($moderator)->exists());
        $this->actingAs($moderator)->get(route('admin.moderation.show', $case))->assertNotFound();
    }

    public function test_moderator_hides_warns_restricts_and_closes_with_a_full_record(): void
    {
        Notification::fake();
        $moderator = $this->officerWith(['moderate_community', 'restrict_users']);
        $space = $this->national();
        $offender = User::factory()->create();
        $reporter = User::factory()->create();
        $post = Community::post($space, $offender, null, 'Mean words about another club');
        $case = SafetyDesk::report($reporter, 'post', $post->post_id, 'bullying');

        Livewire::actingAs($moderator)->test(CaseShow::class, ['case' => $case])
            ->call('hide')
            ->assertHasErrors('action')
            ->set('reason', 'Bullying another club')
            ->call('hide')
            ->set('reason', 'Bullying another club')
            ->call('warn')
            ->set('reason', 'Repeated bullying')
            ->set('restrictionType', 'community')
            ->set('restrictionDays', 14)
            ->call('restrict')
            ->set('outcome', 'actioned')
            ->call('close')
            ->assertSee('Case closed.');

        $this->assertSame(CommunityPost::HIDDEN, $post->fresh()->status);
        $this->assertTrue(SafetyDesk::isRestricted($offender, 'community'));
        Notification::assertSentTo($offender, ModerationWarning::class);
        Notification::assertSentTo($reporter, ReportReviewed::class);
        $this->assertSame(['hidden', 'warned', 'restricted', 'closed'], $case->actions()->reorder('id')->pluck('action')->all());

        $this->expectException(InvalidArgumentException::class);
        Community::post($space, $offender, null, 'Trying again');
    }

    public function test_no_violation_restores_automatically_hidden_content(): void
    {
        $moderator = $this->officerWith(['moderate_community']);
        $space = $this->national();
        $post = Community::post($space, User::factory()->create(), null, 'A fair opinion about tax rates');

        foreach (User::factory()->count(3)->create() as $reporter) {
            $case = SafetyDesk::report($reporter, 'post', $post->post_id, 'misinformation');
        }
        $this->assertSame(CommunityPost::HIDDEN, $post->fresh()->status);

        SafetyDesk::close($case, 'no_violation', $moderator, 'Opinion, not misinformation');
        $this->assertSame(CommunityPost::PUBLISHED, $post->fresh()->status);
    }

    public function test_spaces_respect_institution_and_group_membership(): void
    {
        $school = Institution::factory()->create();
        $clubSpace = CommunitySpace::create(['name' => 'Azania Tax Club', 'type' => 'institution', 'institution_id' => $school->institution_id, 'status' => 'active']);
        $group = CommunitySpace::create(['name' => 'Mwanza VAT project', 'type' => 'group', 'status' => 'active']);
        $insider = User::factory()->create(['institution_id' => $school->institution_id]);
        $outsider = User::factory()->create();
        $manager = $this->officerWith(['manage_community_spaces']);

        $this->assertTrue($clubSpace->isVisibleTo($insider));
        $this->assertFalse($clubSpace->isVisibleTo($outsider));
        $this->assertTrue($group->members_only);
        $this->assertFalse($group->isVisibleTo($outsider));

        $result = Community::addMembers($group, $outsider->membership_id.', TC-99-ZZZ-000001-0', 'member', $manager);
        $this->assertSame(1, $result['added']);
        $this->assertTrue($group->fresh()->isVisibleTo($outsider));
        $this->actingAs($insider)->get(route('member.community.space', $group))->assertNotFound();
    }

    public function test_admin_and_member_screens_render(): void
    {
        $space = $this->national();
        $officer = $this->officerWith(['moderate_community', 'manage_community_spaces', 'restrict_users', 'view_safety_reports', 'manage_safety_settings']);
        $post = Community::post($space, User::factory()->create(), null, 'Call me on 0712345678');

        $this->actingAs($officer)->get('/admin/moderation?lang=en')->assertOk()->assertSee('Held by the filter');
        $this->actingAs($officer)->get('/admin/moderation/'.ModerationCase::firstOrFail()->case_id.'?lang=en')->assertOk()->assertSee('Take action');
        $this->actingAs($officer)->get('/admin/moderation/restrictions?lang=en')->assertOk();
        $this->actingAs($officer)->get('/admin/moderation/report?lang=en')->assertOk()->assertSee('Content filter');
        $this->actingAs($officer)->get('/admin/community?lang=en')->assertOk()->assertSee('Tax Club Tanzania');
        $this->actingAs(User::factory()->create())->get('/app/community?lang=en')->assertOk()->assertSee('Community rules');
        $this->assertNotNull($post);
    }
}
