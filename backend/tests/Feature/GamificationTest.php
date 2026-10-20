<?php

namespace Tests\Feature;

use App\Livewire\Admin\GamificationSettings;
use App\Livewire\Admin\Members\Show as MemberPage;
use App\Livewire\Member\Leaderboard;
use App\Livewire\Member\Profile;
use App\Models\Content;
use App\Models\LearningModule;
use App\Models\PointsEntry;
use App\Models\Quiz;
use App\Models\User;
use App\Support\Gamification;
use App\Support\LearningProgress;
use App\Support\QuizEngine;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\BuildsQuizzes;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use BuildsQuizzes, CreatesOfficers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BadgeSeeder::class);
    }

    private function takeQuiz(User $member, Quiz $quiz, int $correct): void
    {
        $attempt = QuizEngine::start($member, $quiz);
        foreach ($quiz->questions as $index => $question) {
            QuizEngine::saveAnswer($attempt, $question->question_id, [$index < $correct ? $this->correctAnswerId($question) : $this->wrongAnswerId($question)]);
        }
        QuizEngine::submit($attempt);
    }

    public function test_quiz_points_count_the_best_attempt_only(): void
    {
        $member = User::factory()->create(['total_points' => 0]);
        $quiz = $this->quizWithQuestions(4);

        $this->takeQuiz($member, $quiz, 1);   // fail: 1 point
        $this->assertSame(1, $member->fresh()->total_points);

        $this->takeQuiz($member, $quiz, 4);   // pass: 10 + 4 = 14, so +13
        $this->assertSame(14, $member->fresh()->total_points);

        $this->takeQuiz($member, $quiz, 3);   // worse: nothing
        $this->assertSame(14, $member->fresh()->total_points);
        $this->assertSame(14, (int) PointsEntry::where('user_id', $member->user_id)->sum('points'));
    }

    public function test_practice_quizzes_earn_no_points(): void
    {
        $member = User::factory()->create(['total_points' => 0]);
        $this->takeQuiz($member, $this->quizWithQuestions(2, ['mode' => Quiz::PRACTICE]), 2);

        $this->assertSame(0, $member->fresh()->total_points);
    }

    public function test_lessons_modules_badges_and_the_daily_cap(): void
    {
        $member = User::factory()->create(['total_points' => 0]);
        [$one, $two] = Content::factory()->count(2)->create();
        $module = LearningModule::create(['title' => 'Basics', 'status' => LearningModule::PUBLISHED]);
        $module->lessons()->attach([$one->content_id => ['position' => 0, 'is_required' => true], $two->content_id => ['position' => 1, 'is_required' => true]]);

        LearningProgress::complete($member, $one);
        LearningProgress::complete($member, $one);
        $this->assertSame(2, $member->fresh()->total_points, 'A lesson earns points once.');
        $this->assertDatabaseHas('user_badges', ['user_id' => $member->user_id]);
        $this->assertSame(1, $member->notifications()->where('data', 'like', '%badge%')->count());

        LearningProgress::complete($member, $two);
        $this->assertSame(14, $member->fresh()->total_points, '2 + 2 + module 10');

        // Daily cap of 50 automatic points.
        foreach (range(1, 3) as $i) {
            $this->takeQuiz($member, $this->quizWithQuestions(4), 4); // 14 each
        }
        $this->assertSame(50, $member->fresh()->total_points);
        $this->assertStringContainsString('daily limit', PointsEntry::where('user_id', $member->user_id)->latest('id')->value('description'));
    }

    public function test_officer_adjusts_points_with_a_reason_and_entries_are_permanent(): void
    {
        $officer = $this->officerWith(['view_members', 'adjust_points', 'view_all_registrations'], ['user_type' => 'staff']);
        $member = User::factory()->create(['total_points' => 0]);

        Livewire::actingAs($officer)->test(MemberPage::class, ['member' => $member])
            ->set('pointsChange', 25)
            ->set('pointsReason', 'Won the regional tax debate')
            ->call('adjustPoints')
            ->assertHasNoErrors();

        $this->assertSame(25, $member->fresh()->total_points);
        $entry = PointsEntry::sole();
        $this->assertSame($officer->user_id, $entry->awarded_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'points.adjusted', 'reason' => 'Won the regional tax debate']);

        $this->expectException(LogicException::class);
        $entry->update(['points' => 1000]);
    }

    public function test_leaderboard_by_period_and_area_hides_minor_schools(): void
    {
        $viewer = User::factory()->create(['total_points' => 0]);
        $minor = User::factory()->minor()->create(['full_name' => 'Neema Ally Juma', 'institution_id' => $viewer->institution_id]);
        $other = User::factory()->create(['full_name' => 'Juma Hassan']);
        Gamification::adjust($minor, 40, 'Debate', $this->officerWith([], ['user_type' => 'staff']));
        Gamification::adjust($other, 30, 'Quiz night', $this->officerWith([], ['user_type' => 'staff']));

        $page = Livewire::actingAs($viewer)->test(Leaderboard::class)
            ->assertSee('Neema J.')
            ->assertDontSee('Neema Ally Juma')
            ->assertSee($minor->institution->region)
            ->set('scope', 'institution')
            ->assertSee('Neema J.')
            ->assertDontSee('Juma H.');

        $this->travel(40)->days();
        $page->set('scope', 'national')->set('period', 'month')->assertDontSee('Neema J.');
        $page->set('period', 'all')->assertSee('Neema J.');
    }

    public function test_admin_changes_rules_and_switches_leaderboards_off(): void
    {
        $admin = $this->officerWith(['manage_gamification'], ['user_type' => 'staff']);

        Livewire::actingAs($admin)->test(GamificationSettings::class)
            ->set('rules.lesson_completed', 5)
            ->set('leaderboardsEnabled', false)
            ->call('saveRules');

        $member = User::factory()->create(['total_points' => 0]);
        LearningProgress::complete($member, Content::factory()->create());
        $this->assertSame(5, $member->fresh()->total_points);

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/leaderboard')->assertJsonPath('data.enabled', false);
    }

    public function test_members_can_switch_off_badge_messages(): void
    {
        $member = User::factory()->create();

        Livewire::actingAs($member)->test(Profile::class)
            ->set('preferences.achievements.inbox', false)
            ->call('savePreferences');

        LearningProgress::complete($member, Content::factory()->create());

        $this->assertDatabaseHas('user_badges', ['user_id' => $member->user_id]);
        $this->assertSame(0, $member->notifications()->count());
    }
}
