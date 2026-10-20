<?php

namespace Tests\Feature;

use App\Livewire\Admin\Learning\Form;
use App\Livewire\Admin\Learning\Show;
use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\ContentVersion;
use App\Models\User;
use App\Support\ContentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class LearningContentWorkflowTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private ContentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = ContentCategory::create(['slug' => 'vat', 'name_sw' => 'VAT sw', 'name_en' => 'VAT']);
    }

    private function author(): User
    {
        return $this->officerWith(['upload_content', 'edit_content'], ['user_type' => 'staff']);
    }

    private function reviewer(): User
    {
        return $this->officerWith(['review_content', 'publish_content'], ['user_type' => 'staff']);
    }

    private function draft(User $author, array $overrides = []): Content
    {
        return ContentWorkflow::create([
            'title' => 'What is VAT?',
            'content_type' => 'article',
            'category_id' => $this->category->id,
            'body_text' => '<p>VAT is a tax on consumption.</p>',
            'level' => 'beginner',
            'language' => 'en',
            ...$overrides,
        ], $author);
    }

    public function test_author_writes_a_lesson_in_markdown_and_sends_it_for_review(): void
    {
        $author = $this->author();

        Livewire::actingAs($author)->test(Form::class)
            ->set('title', 'Why receipts matter')
            ->set('category_id', (string) $this->category->id)
            ->set('body_markdown', "## Receipts\n\nAlways ask for an **EFD receipt**.<script>alert(1)</script>")
            ->set('audience', ['secondary'])
            ->set('keywords', 'EFD, receipts , EFD')
            ->call('save', true)
            ->assertHasNoErrors();

        $content = Content::where('title', 'Why receipts matter')->sole();
        $revision = $content->versions()->sole();
        $this->assertSame(ContentVersion::IN_REVIEW, $revision->status);
        $this->assertStringContainsString('<strong>EFD receipt</strong>', $revision->field('body_text'));
        $this->assertStringNotContainsString('<script>', $revision->field('body_text'));
        $this->assertSame('EFD, receipts', $revision->field('keywords'));
        $this->assertSame(['secondary'], $revision->field('audience'));
        $this->assertFalse($content->isLive());
        $this->assertMatchesRegularExpression('/^LC-\d{4}$/', $content->code);
    }

    public function test_incomplete_lessons_cannot_go_to_review(): void
    {
        $content = $this->draft($this->author(), ['category_id' => null, 'body_text' => null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Choose a category.');
        ContentWorkflow::submit($content, $this->author());
    }

    public function test_reviewer_cannot_approve_their_own_submission(): void
    {
        $person = $this->officerWith(['upload_content', 'review_content'], ['user_type' => 'staff']);
        $content = $this->draft($person);
        ContentWorkflow::submit($content, $person);

        $this->expectException(InvalidArgumentException::class);
        ContentWorkflow::approve($content, $person);
    }

    public function test_return_reject_and_resubmit(): void
    {
        $author = $this->author();
        $reviewer = $this->reviewer();
        $content = $this->draft($author);
        ContentWorkflow::submit($content, $author);

        Livewire::actingAs($reviewer)->test(Show::class, ['content' => $content])
            ->call('returnForChanges')
            ->assertHasErrors('action')
            ->set('reviewNote', 'Add an example with prices.')
            ->call('returnForChanges')
            ->assertSee('Returned to the author');

        $this->assertSame(ContentVersion::RETURNED, $content->versions()->first()->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $author->user_id]);

        ContentWorkflow::saveDraft($content, ['title' => 'What is VAT? With examples', 'content_type' => 'article', 'category_id' => $this->category->id, 'body_text' => '<p>Example</p>'], $author);
        ContentWorkflow::submit($content, $author);
        ContentWorkflow::approve($content, $reviewer);

        $this->assertSame(ContentVersion::APPROVED, $content->versions()->first()->status);
    }

    public function test_publishing_a_new_version_keeps_members_on_the_live_one_until_approved(): void
    {
        $author = $this->author();
        $reviewer = $this->reviewer();
        $content = $this->draft($author);
        ContentWorkflow::submit($content, $author);
        ContentWorkflow::approve($content, $reviewer);

        Livewire::actingAs($reviewer)->test(Show::class, ['content' => $content])->call('publish')->assertSee('Published');

        $content->refresh();
        $this->assertTrue($content->isLive());
        $this->assertSame(1, $content->current_version);
        $this->assertTrue($content->review_due_at->between(now()->addMonths(11), now()->addMonths(13)));

        // A correction after the Finance Act: version 2.
        ContentWorkflow::saveDraft($content, ['title' => 'What is VAT? (2026 rates)', 'content_type' => 'article', 'category_id' => $this->category->id, 'body_text' => '<p>VAT is 18%.</p>'], $author);
        $this->assertSame('What is VAT?', $content->fresh()->title, 'Members still see version 1.');

        $this->assertThrows(fn () => ContentWorkflow::submit($content, $author), InvalidArgumentException::class);
        ContentWorkflow::submit($content, $author, 'Updated after the Finance Act 2026');
        ContentWorkflow::approve($content, $reviewer);
        ContentWorkflow::publish($content, $reviewer);

        $content->refresh();
        $this->assertSame('What is VAT? (2026 rates)', $content->title);
        $this->assertSame(2, $content->current_version);
        $this->assertSame([ContentVersion::PUBLISHED, ContentVersion::SUPERSEDED], $content->versions()->pluck('status')->all());
    }

    public function test_scheduled_and_expiring_publication(): void
    {
        $author = $this->author();
        $reviewer = $this->reviewer();
        $content = $this->draft($author);
        ContentWorkflow::submit($content, $author);
        ContentWorkflow::approve($content, $reviewer);
        ContentWorkflow::publish($content, $reviewer, now()->addDays(2), now()->addDays(9));

        $this->assertFalse($content->fresh()->isLive());
        $this->assertSame(0, Content::live()->count());

        $this->travel(3)->days();
        $this->assertTrue($content->fresh()->isLive());
        ContentWorkflow::applySchedule();
        $this->assertSame(Content::PUBLISHED, $content->fresh()->publication_status);

        $this->travel(7)->days();
        ContentWorkflow::applySchedule();
        $this->assertSame(Content::UNPUBLISHED, $content->fresh()->publication_status);
    }

    public function test_unpublish_and_archive_need_a_reason(): void
    {
        $reviewer = $this->reviewer();
        $content = Content::factory()->create();

        $this->assertThrows(fn () => ContentWorkflow::unpublish($content, $reviewer, ''), InvalidArgumentException::class);
        ContentWorkflow::archive($content, $reviewer, 'Replaced by a newer lesson');

        $this->assertSame(Content::ARCHIVED, $content->fresh()->publication_status);
        $this->assertFalse($content->fresh()->isLive());
        $this->assertDatabaseHas('editorial_events', ['subject_id' => $content->content_id, 'action' => 'archived', 'note' => 'Replaced by a newer lesson']);
    }

    public function test_document_upload(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->author())->test(Form::class)
            ->set('title', 'Income tax guide')
            ->set('content_type', 'document')
            ->set('category_id', (string) $this->category->id)
            ->set('media', UploadedFile::fake()->create('guide.pdf', 200, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();

        $path = Content::where('title', 'Income tax guide')->sole()->versions()->sole()->field('media_path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_members_only_get_live_content_for_their_category(): void
    {
        $member = User::factory()->create(['user_type' => 'secondary']);
        $forAll = Content::factory()->create(['title' => 'For everyone', 'keywords' => 'VAT, receipts']);
        Content::factory()->create(['title' => 'University only', 'audience' => ['university']]);
        Content::factory()->draft()->create(['title' => 'Draft lesson']);

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/content')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'For everyone');

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/content?search=receipts')->assertJsonCount(1, 'data');
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/content/'.$forAll->content_id)->assertOk()->assertJsonPath('data.content.version', 1);
    }

    public function test_content_list_shows_working_titles_by_status(): void
    {
        $author = $this->author();
        $this->draft($author, ['title' => 'Draft about customs']);
        Content::factory()->create(['title' => 'Live lesson on PAYE']);

        $this->actingAs($author)->get(route('admin.learning.index', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Draft about customs')
            ->assertSee('Live lesson on PAYE');
    }
}
