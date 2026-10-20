<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\User;
use App\Support\QuizEngine;
use Database\Seeders\SuperUserSeeder;
use Database\Seeders\TraLearningSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraLearningSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_content_is_published_in_both_languages_with_sources_and_working_quizzes(): void
    {
        $this->seed(SuperUserSeeder::class);
        $this->seed(TraLearningSeeder::class);
        $this->seed(TraLearningSeeder::class); // re-running adds nothing

        $this->assertSame(11, Content::live()->where('language', 'sw')->count());
        $this->assertSame(11, Content::live()->where('language', 'en')->count());
        $this->assertSame(16, Quiz::open()->count());
        $this->assertSame(4, LearningModule::published()->count());

        Content::all()->each(fn (Content $lesson) => $this->assertMatchesRegularExpression('/(Source|Chanzo):/', $lesson->body_markdown, $lesson->title.' has no source line'));
        $this->assertSame(0, Content::where('body_markdown', 'like', '%](http%')->where('body_markdown', 'not like', '%tra.go.tz%')->count());

        Quiz::with('questions.answers')->get()->each(function (Quiz $quiz) {
            $this->assertCount(5, $quiz->questions);
            $quiz->questions->each(fn ($question) => $this->assertNull($question->problem(), $quiz->title.': '.$question->question_text));
        });

        // A member can start a seeded quiz.
        $quiz = Quiz::where('language', 'sw')->firstOrFail();
        $attempt = QuizEngine::start(User::factory()->create(), $quiz);
        $this->assertNotNull($attempt->attempt_id);

        $this->get('/learn')->assertOk()->assertSee('TRA ni Nini na Kwa Nini Tunalipa Kodi');
        $this->get('/en/learn')->assertOk()->assertSee('What TRA Is and Why We Pay Taxes')->assertDontSee('TRA ni Nini na Kwa Nini Tunalipa Kodi');
    }
}
