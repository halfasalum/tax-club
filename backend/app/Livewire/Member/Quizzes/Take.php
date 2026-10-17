<?php

namespace App\Livewire\Member\Quizzes;

use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Support\QuizEngine;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Answering a quiz. Every answer is saved straight away; the timer comes
 * from the server and the attempt is submitted when time runs out
 * (GAM-REQ05, GAM-REQ07).
 */
#[Layout('components.layouts.app')]
class Take extends Component
{
    public QuizAttempt $attempt;

    /**
     * Chosen answers by question id: a single id, or a list for multi-select.
     *
     * @var array<string, string|list<string>>
     */
    public array $answers = [];

    public ?string $saveError = null;

    public function mount(QuizAttempt $attempt): mixed
    {
        abort_unless($attempt->user_id === auth()->id(), 404);
        $this->attempt = $attempt;

        if ($attempt->status === QuizAttempt::SUBMITTED || QuizEngine::isOverdue($attempt)) {
            QuizEngine::submit($attempt, automatic: true);

            return $this->redirectRoute('member.quizzes.result', $attempt, navigate: true);
        }

        // Every question starts with an empty answer: a list for multi-select.
        foreach (QuizQuestion::whereIn('question_id', $attempt->question_order ?? [])->get(['question_id', 'question_type']) as $question) {
            $this->answers[$question->question_id] = $question->question_type === QuizQuestion::MULTIPLE_SELECT ? [] : '';
        }

        foreach ($attempt->responses()->get() as $response) {
            $this->answers[$response->question_id] = $this->isMulti($response->question_id)
                ? ($response->selected_answer_ids ?? [])
                : ($response->selected_answer_ids[0] ?? '');
        }

        return null;
    }

    /**
     * Livewire sends either one question ("answers.{id}") or, while the
     * array is still empty in the browser, the whole set of answers.
     */
    public function updatedAnswers(mixed $value, ?string $questionId = null): void
    {
        $changes = $questionId === null ? (array) $value : [$questionId => $value];

        try {
            foreach ($changes as $id => $answer) {
                QuizEngine::saveAnswer($this->attempt, (string) $id, array_values(array_filter((array) $answer)));
            }
            $this->saveError = null;
        } catch (InvalidArgumentException $exception) {
            $this->saveError = $exception->getMessage();
        }
    }

    public function submit(bool $timeUp = false): mixed
    {
        QuizEngine::submit($this->attempt, automatic: $timeUp);

        return $this->redirectRoute('member.quizzes.result', $this->attempt, navigate: true);
    }

    private function isMulti(string $questionId): bool
    {
        return QuizQuestion::whereKey($questionId)->value('question_type') === QuizQuestion::MULTIPLE_SELECT;
    }

    public function render(): mixed
    {
        $questions = QuizQuestion::with('answers')->whereIn('question_id', $this->attempt->question_order ?? [])->get()->keyBy('question_id');

        return view('livewire.member.quizzes.take', [
            'quiz' => $this->attempt->quiz,
            'questions' => collect($this->attempt->question_order ?? [])->map(fn ($id) => $questions->get($id))->filter()->values(),
            'secondsLeft' => $this->attempt->expires_at ? max(0, now()->diffInSeconds($this->attempt->expires_at, false)) : null,
            'answered' => collect($this->answers)->filter(fn ($value) => filled($value))->count(),
        ])->title($this->attempt->quiz->title);
    }
}
