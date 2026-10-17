<?php

namespace Tests\Feature;

use App\Events\LessonCompleted;
use App\Events\ModuleCompleted;
use App\Livewire\Admin\Learning\Modules;
use App\Livewire\Member\Learn\Index;
use App\Livewire\Member\Learn\Lesson;
use App\Models\Content;
use App\Models\ContentCategory;
use App\Models\LearningModule;
use App\Models\User;
use App\Support\ContentWorkflow;
use App\Support\LearningProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class MemberLearningTest extends TestCase
{
    use CreatesOfficers, RefreshDatabase;

    private function module(array $lessons, array $optional = []): LearningModule
    {
        $module = LearningModule::create(['title' => 'VAT basics', 'status' => LearningModule::PUBLISHED]);
        foreach ($lessons as $position => $lesson) {
            $module->lessons()->attach($lesson->content_id, ['position' => $position, 'is_required' => ! in_array($lesson, $optional, true)]);
        }

        return $module;
    }

    public function test_member_completes_lessons_and_the_module(): void
    {
        Event::fake([LessonCompleted::class, ModuleCompleted::class]);
        $member = User::factory()->create();
        [$one, $two, $extra] = Content::factory()->count(3)->create();
        $module = $this->module([$one, $two, $extra], optional: [$extra]);

        Livewire::actingAs($member)->test(Lesson::class, ['lesson' => $one])
            ->assertSee($one->title)
            ->assertSee('Next lesson')
            ->call('complete')
            ->assertSee('You completed this lesson');

        $this->assertSame(1, $member->progress()->where('content_id', $one->content_id)->value('content_version'));
        Event::assertDispatchedTimes(LessonCompleted::class, 1);
        Event::assertNotDispatched(ModuleCompleted::class);

        LearningProgress::complete($member, $two);
        LearningProgress::complete($member, $two);

        Event::assertDispatchedTimes(LessonCompleted::class, 2);
        Event::assertDispatchedTimes(ModuleCompleted::class, 1);
        $this->assertDatabaseHas('module_completions', ['user_id' => $member->user_id, 'module_id' => $module->id]);
    }

    public function test_hub_shows_resume_modules_and_searches(): void
    {
        $member = User::factory()->create();
        $category = ContentCategory::create(['slug' => 'vat', 'name_sw' => 'VAT', 'name_en' => 'VAT']);
        $vat = Content::factory()->create(['title' => 'VAT made simple', 'category_id' => $category->id]);
        Content::factory()->create(['title' => 'Customs at the border']);
        $this->module([$vat]);
        LearningProgress::open($member, $vat);

        Livewire::actingAs($member)->test(Index::class)
            ->assertSee('Continue where you left off')
            ->assertSee('VAT basics')
            ->set('search', 'customs')
            ->assertSee('Customs at the border')
            ->assertDontSee('VAT made simple')
            ->set('search', '')
            ->set('category', (string) $category->id)
            ->assertSee('VAT made simple')
            ->assertDontSee('Customs at the border');
    }

    public function test_targeted_and_unpublished_lessons_are_hidden(): void
    {
        $member = User::factory()->create(['user_type' => 'secondary']);
        $universityOnly = Content::factory()->create(['audience' => ['university']]);
        $draft = Content::factory()->draft()->create();

        $this->actingAs($member)->get(route('member.learn.lesson', $universityOnly))->assertNotFound();
        $this->actingAs($member)->get(route('member.learn.lesson', $draft))->assertNotFound();
    }

    public function test_members_are_told_when_a_completed_lesson_changes(): void
    {
        $member = User::factory()->create();
        $author = $this->officerWith(['upload_content'], ['user_type' => 'staff']);
        $reviewer = $this->officerWith(['review_content', 'publish_content'], ['user_type' => 'staff']);
        $lesson = Content::factory()->create(['category_id' => ContentCategory::create(['slug' => 'x', 'name_sw' => 'X', 'name_en' => 'X'])->id]);
        LearningProgress::complete($member, $lesson);

        ContentWorkflow::saveDraft($lesson, [...ContentWorkflow::liveFields($lesson), 'body_text' => '<p>New rate</p>'], $author, 'Rate change');
        ContentWorkflow::submit($lesson, $author);
        ContentWorkflow::approve($lesson, $reviewer);
        ContentWorkflow::publish($lesson, $reviewer);

        Livewire::actingAs($member)->test(Lesson::class, ['lesson' => $lesson->fresh()])->assertSee('This lesson was updated after you completed it');
    }

    public function test_publisher_builds_a_module(): void
    {
        $publisher = $this->officerWith(['publish_content'], ['user_type' => 'staff']);
        [$one, $two] = Content::factory()->count(2)->create();

        Livewire::actingAs($publisher)->test(Modules::class)
            ->call('create')
            ->set('title', 'Receipts and you')
            ->set('addLesson', $one->content_id)
            ->set('addLesson', $two->content_id)
            ->call('move', 1, -1)
            ->set('items.1.is_required', false)
            ->call('save', true)
            ->assertHasNoErrors()
            ->assertSee('Module published.');

        $module = LearningModule::sole();
        $this->assertSame(LearningModule::PUBLISHED, $module->status);
        $this->assertSame([$two->content_id, $one->content_id], $module->lessons->pluck('content_id')->all());
        $this->assertFalse((bool) $module->lessons->last()->pivot->is_required);
    }

    public function test_api_learning_endpoints(): void
    {
        $member = User::factory()->create();
        $lesson = Content::factory()->create();
        $module = $this->module([$lesson]);

        $this->actingAs($member, 'sanctum')->postJson("/api/v1/content/{$lesson->content_id}/complete")->assertOk()->assertJsonPath('data.first_time', true);
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/learning')->assertOk()->assertJsonPath('data.modules.0.is_complete', true);
        $this->actingAs($member, 'sanctum')->getJson("/api/v1/learning/modules/{$module->id}")->assertOk()->assertJsonPath('data.lessons.0.status', 'completed');
    }
}
