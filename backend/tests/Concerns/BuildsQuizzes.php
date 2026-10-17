<?php

namespace Tests\Concerns;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;

trait BuildsQuizzes
{
    /**
     * A quiz with $count single-choice questions worth 1 mark each. The
     * first option of every question is correct.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function quizWithQuestions(int $count = 4, array $attributes = [], string $state = 'published'): Quiz
    {
        $factory = Quiz::factory();
        $quiz = ($state === 'draft' ? $factory->draft() : $factory)->create($attributes);

        for ($i = 0; $i < $count; $i++) {
            $question = QuizQuestion::create(['quiz_id' => $quiz->quiz_id, 'question_text' => "Question number {$i}", 'question_type' => QuizQuestion::SINGLE_CHOICE, 'points' => 1, 'order_index' => $i, 'is_active' => true]);
            foreach (['Right', 'Wrong', 'Also wrong'] as $position => $text) {
                QuizAnswer::create(['question_id' => $question->question_id, 'answer_text' => "{$text} {$i}", 'is_correct' => $position === 0, 'order_index' => $position]);
            }
        }

        return $quiz->fresh();
    }

    protected function correctAnswerId(QuizQuestion $question): string
    {
        return $question->answers()->where('is_correct', true)->value('answer_id');
    }

    protected function wrongAnswerId(QuizQuestion $question): string
    {
        return $question->answers()->where('is_correct', false)->value('answer_id');
    }
}
