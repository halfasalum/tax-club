<?php

namespace App\Livewire\Admin\Quizzes;

use App\Models\EditorialEvent;
use App\Models\Quiz;
use App\Support\QuizWorkflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Preview, review, publish and follow results of a quiz (GAM-REQ02-04, 06).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Quiz $quiz;

    public string $reviewNote = '';

    public string $opensAt = '';

    public string $closesAt = '';

    public string $reason = '';

    public ?string $statusMessage = null;

    public function mount(Quiz $quiz): void
    {
        $this->quiz = $quiz;
        $this->statusMessage = session('status');
    }

    public function submit(): void
    {
        $this->authorizeAny(['create_quiz', 'edit_quiz']);
        $this->attempt(fn () => QuizWorkflow::submit($this->quiz, auth()->user()), __('Sent for review.'));
    }

    public function approve(): void
    {
        $this->authorizeAny(['review_quiz']);
        $this->attempt(fn () => QuizWorkflow::approve($this->quiz, auth()->user(), trim($this->reviewNote) ?: null), __('Approved. It can now be published.'));
    }

    public function returnForChanges(): void
    {
        $this->authorizeAny(['review_quiz']);
        $this->attempt(fn () => QuizWorkflow::returnForChanges($this->quiz, auth()->user(), trim($this->reviewNote)), __('Returned to the author with your notes.'));
    }

    public function reject(): void
    {
        $this->authorizeAny(['review_quiz']);
        $this->attempt(fn () => QuizWorkflow::reject($this->quiz, auth()->user(), trim($this->reviewNote)), __('Rejected.'));
    }

    public function publish(): void
    {
        $this->authorizeAny(['publish_quiz']);
        $this->validate(['opensAt' => 'nullable|date', 'closesAt' => 'nullable|date']);
        $opens = $this->opensAt ? Carbon::parse($this->opensAt) : null;

        $this->attempt(fn () => QuizWorkflow::publish($this->quiz, auth()->user(), $opens, $this->closesAt ? Carbon::parse($this->closesAt) : null),
            $opens?->isFuture() ? __('Scheduled.') : __('Published. Members can take it now.'));
    }

    public function close(): void
    {
        $this->authorizeAny(['publish_quiz']);
        $this->attempt(fn () => QuizWorkflow::close($this->quiz, auth()->user(), trim($this->reason) ?: null), __('Closed. Members can no longer start it.'));
    }

    public function archive(): void
    {
        $this->authorizeAny(['publish_quiz']);
        $this->attempt(fn () => QuizWorkflow::archive($this->quiz, auth()->user(), $this->reason), __('Archived.'));
    }

    public function reopen(): void
    {
        $this->authorizeAny(['create_quiz', 'edit_quiz']);
        $this->attempt(fn () => QuizWorkflow::reopen($this->quiz, auth()->user()), __('Back to draft. You can change the questions now.'));
    }

    public function duplicate(): mixed
    {
        $this->authorizeAny(['create_quiz']);
        $copy = QuizWorkflow::duplicate($this->quiz, auth()->user());
        session()->flash('status', __('A new draft was created from :code.', ['code' => $this->quiz->code]));

        return $this->redirectRoute('admin.quizzes.edit', $copy, navigate: false);
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('action', $exception->getMessage());

            return;
        }

        $this->reset('reviewNote', 'reason', 'opensAt', 'closesAt');
        $this->statusMessage = $success;
    }

    /**
     * @param  list<string>  $controls
     */
    private function authorizeAny(array $controls): void
    {
        abort_unless(collect($controls)->contains(fn ($control) => auth()->user()->hasControl($control)), 403);
    }

    public function render(): mixed
    {
        $this->quiz->refresh()->load(['questions.answers', 'category', 'content']);
        $user = auth()->user();
        $submitted = $this->quiz->attempts()->whereNotNull('submitted_at');

        return view('livewire.admin.quizzes.show', [
            'problems' => QuizWorkflow::readinessErrors($this->quiz),
            'editable' => QuizWorkflow::canEdit($this->quiz),
            'hasAttempts' => QuizWorkflow::hasAttempts($this->quiz),
            'results' => $user->hasControl('view_results') ? [
                'attempts' => (clone $submitted)->count(),
                'members' => (clone $submitted)->distinct('user_id')->count('user_id'),
                'average' => (int) round((float) (clone $submitted)->avg('percent')),
                'passed' => (clone $submitted)->where('passed', true)->distinct('user_id')->count('user_id'),
                'hardest' => $this->hardestQuestions(),
            ] : null,
            'events' => EditorialEvent::where('subject_type', 'quiz')->where('subject_id', $this->quiz->quiz_id)->with('user:user_id,full_name')->latest('id')->limit(20)->get(),
            'can' => [
                'edit' => $user->hasControl('create_quiz') || $user->hasControl('edit_quiz'),
                'create' => $user->hasControl('create_quiz'),
                'review' => $user->hasControl('review_quiz'),
                'publish' => $user->hasControl('publish_quiz'),
            ],
            'ownSubmission' => $this->quiz->editorial_status === Quiz::IN_REVIEW && $this->quiz->submitted_by === $user->user_id,
        ])->title($this->quiz->title);
    }

    /**
     * Questions most often answered wrongly, for content officers.
     *
     * @return Collection<int, object>
     */
    private function hardestQuestions(): Collection
    {
        return DB::table('quiz_user_responses')
            ->join('quiz_attempts', 'quiz_attempts.attempt_id', '=', 'quiz_user_responses.attempt_id')
            ->join('quiz_questions', 'quiz_questions.question_id', '=', 'quiz_user_responses.question_id')
            ->where('quiz_attempts.quiz_id', $this->quiz->quiz_id)
            ->whereNotNull('quiz_attempts.submitted_at')
            ->groupBy('quiz_questions.question_id', 'quiz_questions.question_text')
            ->selectRaw('quiz_questions.question_text, count(*) as answered, sum(case when quiz_user_responses.is_correct then 1 else 0 end) as correct')
            ->havingRaw('count(*) > 0')
            ->orderByRaw('sum(case when quiz_user_responses.is_correct then 1 else 0 end) * 1.0 / count(*)')
            ->limit(3)
            ->get();
    }
}
