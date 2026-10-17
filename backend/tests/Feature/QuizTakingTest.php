<?php

namespace Tests\Feature;

use App\Livewire\Member\Quizzes\Result;
use App\Livewire\Member\Quizzes\Show;
use App\Livewire\Member\Quizzes\Take;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Support\QuizEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\BuildsQuizzes;
use Tests\TestCase;

class QuizTakingTest extends TestCase
{
    use BuildsQuizzes, RefreshDatabase;

    private function answerAll(QuizAttempt $attempt, Quiz $quiz, int $correct): void
    {
        foreach ($quiz->questions as $index => $question) {
            QuizEngine::saveAnswer($attempt, $question->question_id, [$index < $correct ? $this->correctAnswerId($question) : $this->wrongAnswerId($question)]);
        }
    }

    public function test_member_takes_a_quiz_with_autosave_and_sees_the_result(): void
    {
        $member = User::factory()->create();
        $quiz = $this->quizWithQuestions(4, ['pass_mark' => 75]);

        $intro = Livewire::actingAs($member)->test(Show::class, ['quiz' => $quiz])->assertSee('Pass mark: 75%');
        $intro->call('start');
        $attempt = QuizAttempt::sole();
        $intro->assertRedirect(route('member.quizzes.take', $attempt));

        $take = Livewire::actingAs($member)->test(Take::class, ['attempt' => $attempt]);
        foreach ($quiz->questions as $index => $question) {
            $take->set("answers.{$question->question_id}", $index < 3 ? $this->correctAnswerId($question) : $this->wrongAnswerId($question));
        }
        $this->assertSame(4, $attempt->responses()->count(), 'Answers are saved as the member goes.');

        // The browser may send all answers at once while the array is empty.
        Livewire::actingAs($member)->test(Take::class, ['attempt' => $attempt])
            ->set('answers', [$quiz->questions[3]->question_id => $this->correctAnswerId($quiz->questions[3])]);
        $this->assertTrue($attempt->responses()->where('question_id', $quiz->questions[3]->question_id)->value('selected_answer_ids') === [$this->correctAnswerId($quiz->questions[3])]);
        QuizEngine::saveAnswer($attempt, $quiz->questions[3]->question_id, [$this->wrongAnswerId($quiz->questions[3])]);

        // Leaving and coming back resumes the same attempt with the saved answers.
        Livewire::actingAs($member)->test(Show::class, ['quiz' => $quiz])->assertSee('Continue your attempt');
        Livewire::actingAs($member)->test(Take::class, ['attempt' => $attempt])->assertSet('answers.'.$quiz->questions[0]->question_id, $this->correctAnswerId($quiz->questions[0]));

        $take->call('submit')->assertRedirect(route('member.quizzes.result', $attempt));

        $attempt->refresh();
        $this->assertSame([3, 4, 75.0, true], [$attempt->score, $attempt->max_score, $attempt->percent, $attempt->passed]);

        Livewire::actingAs($member)->test(Result::class, ['attempt' => $attempt])
            ->assertSee('75%')
            ->assertSee('You passed!')
            ->assertSee('your answer');
    }

    public function test_multiple_select_needs_every_correct_option(): void
    {
        $member = User::factory()->create();
        $quiz = $this->quizWithQuestions(1);
        $question = $quiz->questions->first();
        $question->update(['question_type' => QuizQuestion::MULTIPLE_SELECT]);
        $second = $question->answers()->where('is_correct', false)->first();
        $second->update(['is_correct' => true]);

        $attempt = QuizEngine::start($member, $quiz);
        QuizEngine::saveAnswer($attempt, $question->question_id, [$this->correctAnswerId($question)]);
        $this->assertSame(0, QuizEngine::submit($attempt)->score, 'Half right is wrong.');

        $attempt = QuizEngine::start($member, $quiz);
        QuizEngine::saveAnswer($attempt, $question->question_id, $question->answers()->where('is_correct', true)->pluck('answer_id')->all());
        $this->assertSame(1, QuizEngine::submit($attempt)->score);
    }

    public function test_time_limit_is_enforced_by_the_server(): void
    {
        $member = User::factory()->create();
        $quiz = $this->quizWithQuestions(2, ['time_limit_minutes' => 5]);
        $attempt = QuizEngine::start($member, $quiz);
        $this->assertTrue($attempt->expires_at->between(now()->addMinutes(4), now()->addMinutes(6)));

        QuizEngine::saveAnswer($attempt, $quiz->questions[0]->question_id, [$this->correctAnswerId($quiz->questions[0])]);

        $this->travel(6)->minutes();
        $this->assertThrows(fn () => QuizEngine::saveAnswer($attempt, $quiz->questions[1]->question_id, [$this->correctAnswerId($quiz->questions[1])]), InvalidArgumentException::class);

        $attempt->refresh();
        $this->assertSame(QuizAttempt::SUBMITTED, $attempt->status);
        $this->assertTrue($attempt->auto_submitted);
        $this->assertSame(1, $attempt->score, 'Only answers saved before the deadline count.');
        $this->assertSame(300, $attempt->time_spent_seconds);
    }

    public function test_overdue_attempts_are_closed_by_the_scheduler(): void
    {
        $quiz = $this->quizWithQuestions(1, ['time_limit_minutes' => 1]);
        QuizEngine::start(User::factory()->create(), $quiz);

        $this->travel(3)->minutes();
        $this->assertSame(1, QuizEngine::closeOverdue());
        $this->assertSame(QuizAttempt::SUBMITTED, QuizAttempt::sole()->status);
    }

    public function test_attempt_limit_and_result_that_counts(): void
    {
        $member = User::factory()->create();
        $quiz = $this->quizWithQuestions(4, ['max_attempts' => 2, 'retained_result' => Quiz::RETAIN_LATEST]);

        $first = QuizEngine::start($member, $quiz);
        $this->answerAll($first, $quiz, 4);
        QuizEngine::submit($first);

        $second = QuizEngine::start($member, $quiz);
        $this->answerAll($second, $quiz, 1);
        QuizEngine::submit($second);

        $this->assertSame($second->attempt_id, QuizEngine::resultOfRecord($member, $quiz)->attempt_id, 'Latest attempt counts.');
        $this->assertFalse(QuizEngine::availability($member, $quiz)['can_start']);
        $this->assertThrows(fn () => QuizEngine::start($member, $quiz), InvalidArgumentException::class);

        $quiz->update(['retained_result' => Quiz::RETAIN_BEST, 'max_attempts' => null]);
        $third = QuizEngine::start($member, $quiz);
        $this->answerAll($third, $quiz, 2);
        QuizEngine::submit($third);
        $this->assertSame($first->attempt_id, QuizEngine::resultOfRecord($member, $quiz)->attempt_id, 'Best attempt counts.');
    }

    public function test_answers_can_be_hidden_until_the_quiz_closes(): void
    {
        $member = User::factory()->create();
        $quiz = $this->quizWithQuestions(1, ['show_answers' => Quiz::ANSWERS_AFTER_CLOSE, 'closes_at' => now()->addDay()]);
        $attempt = QuizEngine::submit(QuizEngine::start($member, $quiz));

        $this->assertFalse(QuizEngine::canSeeAnswers($attempt));
        Livewire::actingAs($member)->test(Result::class, ['attempt' => $attempt])->assertSee('Correct answers will be shown after the quiz closes');

        $this->travel(2)->days();
        $this->assertTrue(QuizEngine::canSeeAnswers($attempt->fresh()));
    }

    public function test_members_outside_the_audience_cannot_take_it(): void
    {
        $quiz = $this->quizWithQuestions(1, ['audience' => ['university']]);

        $this->assertThrows(fn () => QuizEngine::start(User::factory()->create(['user_type' => 'secondary']), $quiz), InvalidArgumentException::class);
        $this->actingAs(User::factory()->create(['user_type' => 'secondary']))->get(route('member.quizzes.show', $quiz))->assertNotFound();
    }

    public function test_api_quiz_flow_never_reveals_answers_before_submitting(): void
    {
        $member = User::factory()->create();
        $quiz = $this->quizWithQuestions(2, ['time_limit_minutes' => 10]);

        $started = $this->actingAs($member, 'sanctum')->postJson("/api/v1/quizzes/{$quiz->quiz_id}/start")->assertOk()->json('data');
        $this->assertArrayNotHasKey('is_correct', $started['questions'][0]['answers'][0]);
        $this->assertGreaterThan(590, $started['seconds_left']);

        foreach ($quiz->questions as $question) {
            $this->putJson("/api/v1/quizzes/attempts/{$started['attempt_id']}/answers", ['question_id' => $question->question_id, 'answer_ids' => [$this->correctAnswerId($question)]])->assertOk();
        }

        $this->postJson("/api/v1/quizzes/attempts/{$started['attempt_id']}/submit")
            ->assertOk()
            ->assertJsonPath('data.percent', 100)
            ->assertJsonPath('data.answers_visible', true);

        $this->postJson("/api/v1/quizzes/{$quiz->quiz_id}/submit", ['answers' => []])->assertStatus(409);
    }
}
