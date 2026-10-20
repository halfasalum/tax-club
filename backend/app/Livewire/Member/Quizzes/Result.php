<?php

namespace App\Livewire\Member\Quizzes;

use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Support\QuizEngine;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Result of an attempt, with answers when the quiz allows it (GAM-REQ06).
 */
#[Layout('components.layouts.app')]
class Result extends Component
{
    public QuizAttempt $attempt;

    public function mount(QuizAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id(), 404);

        if ($attempt->status !== QuizAttempt::SUBMITTED) {
            $attempt = QuizEngine::isOverdue($attempt) ? QuizEngine::submit($attempt, automatic: true) : abort(404);
        }

        $this->attempt = $attempt;
    }

    public function render(): mixed
    {
        $quiz = $this->attempt->quiz;
        $showAnswers = QuizEngine::canSeeAnswers($this->attempt);
        $questions = QuizQuestion::with('answers')->whereIn('question_id', $this->attempt->question_order ?? [])->get()->keyBy('question_id');

        return view('livewire.member.quizzes.result', [
            'quiz' => $quiz,
            'showAnswers' => $showAnswers,
            'questions' => collect($this->attempt->question_order ?? [])->map(fn ($id) => $questions->get($id))->filter()->values(),
            'responses' => $this->attempt->responses()->get()->keyBy('question_id'),
            'pointsAwarded' => (int) DB::table('points_ledger')->where('source_type', 'quiz_attempt')->where('source_id', $this->attempt->attempt_id)->sum('points'),
            'status' => QuizEngine::availability(auth()->user(), $quiz),
        ])->title(__('Result: :title', ['title' => $quiz->title]));
    }
}
