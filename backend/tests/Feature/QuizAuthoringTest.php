<?php

namespace Tests\Feature;

use App\Livewire\Admin\Quizzes\Builder;
use App\Livewire\Admin\Quizzes\Show;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\QuizEngine;
use App\Support\QuizWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\BuildsQuizzes;
use Tests\Concerns\CreatesOfficers;
use Tests\TestCase;

class QuizAuthoringTest extends TestCase
{
    use BuildsQuizzes, CreatesOfficers, RefreshDatabase;

    private function author(): User
    {
        return $this->officerWith(['create_quiz', 'edit_quiz'], ['user_type' => 'staff']);
    }

    private function reviewer(): User
    {
        return $this->officerWith(['review_quiz', 'publish_quiz', 'view_results'], ['user_type' => 'staff']);
    }

    public function test_author_builds_a_quiz_with_all_question_types(): void
    {
        $author = $this->author();

        $page = Livewire::actingAs($author)->test(Builder::class)
            ->set('title', 'VAT check-up')
            ->set('pass_mark', 60)
            ->set('time_limit_minutes', 10)
            ->set('max_attempts', 3)
            ->call('saveSettings');

        $quiz = Quiz::where('title', 'VAT check-up')->sole();
        $page->assertRedirect(route('admin.quizzes.edit', $quiz));
        $this->assertSame(Quiz::DRAFT, $quiz->editorial_status);
        $this->assertMatchesRegularExpression('/^QZ-\d{4}$/', $quiz->code);

        $builder = Livewire::actingAs($author)->test(Builder::class, ['quiz' => $quiz]);

        // Single choice: exactly one correct answer.
        $builder->call('newQuestion')
            ->set('question.question_text', 'What is the standard VAT rate?')
            ->set('question.answers.0.text', '18%')
            ->set('question.answers.1.text', '10%')
            ->set('question.answers.2.text', '30%')
            ->set('question.explanation', 'Mainland Tanzania applies 18%.')
            ->call('saveQuestion')
            ->assertHasNoErrors();

        // True/false gets its two options automatically.
        $builder->call('newQuestion')
            ->set('question.question_text', 'Everyone must ask for an EFD receipt.')
            ->set('question.question_type', QuizQuestion::TRUE_FALSE)
            ->call('saveQuestion')
            ->assertHasNoErrors();

        // Multiple select needs at least one correct answer.
        $builder->call('newQuestion')
            ->set('question.question_text', 'Which of these are taxes?')
            ->set('question.question_type', QuizQuestion::MULTIPLE_SELECT)
            ->set('question.answers.0.text', 'VAT')
            ->set('question.answers.1.text', 'PAYE')
            ->set('question.answers.2.text', 'Bus fare')
            ->call('markCorrect', 1)
            ->call('saveQuestion')
            ->assertHasNoErrors();

        $types = $quiz->questions()->pluck('question_type')->all();
        $this->assertSame([QuizQuestion::SINGLE_CHOICE, QuizQuestion::TRUE_FALSE, QuizQuestion::MULTIPLE_SELECT], $types);
        $this->assertSame(2, $quiz->questions()->where('question_type', QuizQuestion::MULTIPLE_SELECT)->first()->answers()->where('is_correct', true)->count());
        $this->assertSame([], QuizWorkflow::readinessErrors($quiz->fresh()));
    }

    public function test_a_question_without_a_correct_answer_is_refused(): void
    {
        $quiz = $this->quizWithQuestions(0, state: 'draft');

        Livewire::actingAs($this->author())->test(Builder::class, ['quiz' => $quiz])
            ->call('newQuestion')
            ->set('question.question_text', 'Which are taxes?')
            ->set('question.question_type', QuizQuestion::MULTIPLE_SELECT)
            ->set('question.answers.0.text', 'A')
            ->set('question.answers.1.text', 'B')
            ->set('question.answers.2.text', 'C')
            ->call('markCorrect', 0)
            ->call('saveQuestion')
            ->assertHasErrors('question.answers');
    }

    public function test_review_and_publication_with_an_opening_window(): void
    {
        $author = $this->author();
        $reviewer = $this->reviewer();
        $quiz = $this->quizWithQuestions(3, ['submitted_by' => null], 'draft');

        $this->assertThrows(fn () => QuizWorkflow::submit($this->quizWithQuestions(0, state: 'draft'), $author), InvalidArgumentException::class);

        QuizWorkflow::submit($quiz, $author);
        $this->assertThrows(fn () => QuizWorkflow::approve($quiz->fresh(), $author), InvalidArgumentException::class);

        Livewire::actingAs($reviewer)->test(Show::class, ['quiz' => $quiz->fresh()])
            ->call('approve')
            ->assertSee('Approved')
            ->set('opensAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('closesAt', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->call('publish')
            ->assertSee('Scheduled');

        $quiz->refresh();
        $member = User::factory()->create();
        $this->assertFalse($quiz->isAvailableTo($member));

        $this->travel(2)->days();
        $this->assertTrue($quiz->fresh()->isAvailableTo($member));

        $this->travel(7)->days();
        $this->assertFalse($quiz->fresh()->isAvailableTo($member));
        $this->assertTrue($quiz->fresh()->hasEnded());
    }

    public function test_quizzes_already_taken_are_locked_and_can_be_duplicated(): void
    {
        $author = $this->author();
        $quiz = $this->quizWithQuestions(2);
        QuizEngine::start(User::factory()->create(), $quiz);

        $this->assertFalse(QuizWorkflow::canEdit($quiz));
        $this->assertThrows(fn () => QuizWorkflow::reopen($quiz, $author), InvalidArgumentException::class);

        $copy = QuizWorkflow::duplicate($quiz, $author);
        $this->assertSame(Quiz::DRAFT, $copy->editorial_status);
        $this->assertSame(2, $copy->questions()->count());
        $this->assertSame(6, $copy->questions()->withCount('answers')->get()->sum('answers_count'));
        $this->assertTrue(QuizWorkflow::canEdit($copy));
    }

    public function test_results_summary_for_officers(): void
    {
        $quiz = $this->quizWithQuestions(2);
        $member = User::factory()->create();
        $attempt = QuizEngine::start($member, $quiz);
        foreach ($quiz->questions as $question) {
            QuizEngine::saveAnswer($attempt, $question->question_id, [$this->correctAnswerId($question)]);
        }
        QuizEngine::submit($attempt);

        Livewire::actingAs($this->reviewer())->test(Show::class, ['quiz' => $quiz])
            ->assertViewHas('results', fn (array $results) => $results['attempts'] === 1 && $results['average'] === 100 && $results['passed'] === 1);

        $this->assertSame(1, QuizAttempt::where('quiz_id', $quiz->quiz_id)->where('passed', true)->count());
    }
}
