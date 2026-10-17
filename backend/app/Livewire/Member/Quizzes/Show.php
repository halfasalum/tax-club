<?php

namespace App\Livewire\Member\Quizzes;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Support\QuizEngine;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Quiz introduction: instructions, rules and past attempts (GAM-REQ05).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Quiz $quiz;

    public function mount(Quiz $quiz): void
    {
        $member = auth()->user();
        $taken = $quiz->attempts()->where('user_id', $member->user_id)->exists();
        abort_unless($quiz->isAvailableTo($member) || $taken, 404);

        $this->quiz = $quiz;
    }

    public function start(): mixed
    {
        try {
            $attempt = QuizEngine::start(auth()->user(), $this->quiz);
        } catch (InvalidArgumentException $exception) {
            $this->addError('start', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('member.quizzes.take', $attempt, navigate: false);
    }

    public function render(): mixed
    {
        $member = auth()->user();

        return view('livewire.member.quizzes.show', [
            'status' => QuizEngine::availability($member, $this->quiz),
            'questionCount' => $this->quiz->questions()->where('is_active', true)->count(),
            'attempts' => $this->quiz->attempts()->where('user_id', $member->user_id)->where('status', QuizAttempt::SUBMITTED)->orderByDesc('attempt_number')->get(),
        ])->title($this->quiz->title);
    }
}
