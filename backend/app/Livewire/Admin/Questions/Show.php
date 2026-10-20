<?php

namespace App\Livewire\Admin\Questions;

use App\Models\ContentCategory;
use App\Models\Question;
use App\Models\User;
use App\Support\AskTra;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Triage, answer, review and publish one question (COM-REQ02-04).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Question $question;

    public ?string $category_id = null;

    public string $expert = '';

    public string $answer = '';

    public string $note = '';

    public bool $publish = true;

    public string $publicQuestion = '';

    public string $publicAnswer = '';

    public ?string $statusMessage = null;

    public function mount(Question $question): void
    {
        $this->question = $question;
        $this->fillForm();
    }

    public function categorise(): void
    {
        $this->authorizeAny(['triage_questions']);
        $this->attempt(fn () => AskTra::categorise($this->question, $this->category_id ? (int) $this->category_id : null, auth()->user()), __('Saved.'));
    }

    public function assign(): void
    {
        $this->authorizeAny(['triage_questions']);
        $this->attempt(fn () => AskTra::assign($this->question, User::findOrFail($this->expert), auth()->user()), __('Assigned.'));
    }

    public function takeIt(): void
    {
        $this->authorizeAny(['answer_questions']);
        $this->attempt(fn () => AskTra::assign($this->question, auth()->user(), auth()->user()), __('Assigned to you.'));
    }

    public function decline(): void
    {
        $this->authorizeAny(['triage_questions', 'review_answers']);
        $this->attempt(fn () => AskTra::decline($this->question, $this->note, auth()->user()), __('Closed. The member was told why.'));
    }

    public function saveAnswer(bool $submit = false): void
    {
        $this->authorizeAny(['answer_questions']);
        $this->validate(['answer' => 'required|string|max:10000']);
        $this->attempt(fn () => AskTra::saveAnswer($this->question, $this->answer, auth()->user(), $submit), $submit ? __('Sent for review.') : __('Draft saved.'));
    }

    public function approve(): void
    {
        $this->authorizeAny(['review_answers']);
        $publish = $this->publish && $this->question->allow_publish;
        $this->attempt(fn () => AskTra::approve($this->question, auth()->user(), $publish, $this->publicQuestion, $this->publicAnswer), $publish ? __('Approved, sent to the member and published.') : __('Approved and sent to the member.'));
    }

    public function returnAnswer(): void
    {
        $this->authorizeAny(['review_answers']);
        $this->attempt(fn () => AskTra::returnAnswer($this->question, auth()->user(), $this->note), __('Returned to the expert.'));
    }

    public function publishNow(): void
    {
        $this->authorizeAny(['review_answers']);
        $this->attempt(fn () => AskTra::publish($this->question, auth()->user(), $this->publicQuestion, $this->publicAnswer), __('Published to the knowledge base.'));
    }

    public function unpublish(): void
    {
        $this->authorizeAny(['review_answers']);
        $this->attempt(fn () => AskTra::unpublish($this->question, auth()->user(), $this->note), __('Removed from the knowledge base.'));
    }

    private function fillForm(): void
    {
        $this->category_id = $this->question->category_id ? (string) $this->question->category_id : null;
        $this->expert = (string) $this->question->assigned_to;
        $this->answer = (string) $this->question->answer;
        $this->publicQuestion = (string) ($this->question->public_question ?? $this->question->subject);
        $this->publicAnswer = (string) ($this->question->public_answer ?? $this->question->answer);
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

        $this->note = '';
        $this->question->refresh();
        $this->fillForm();
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
        $user = auth()->user();

        return view('livewire.admin.questions.show', [
            'categories' => ContentCategory::active()->get(),
            'experts' => User::where('account_status', User::STATUS_ACTIVE)->whereHas('roles.controls', fn ($query) => $query->where('slug', 'answer_questions'))->orderBy('full_name')->get(['user_id', 'full_name']),
            'asker' => $this->question->asker()->with('institution:institution_id,name')->first(),
            'can' => [
                'triage' => $user->hasControl('triage_questions'),
                'answer' => $user->hasControl('answer_questions') && $this->question->assigned_to === $user->user_id,
                'takeIt' => $user->hasControl('answer_questions') && ! $this->question->assigned_to && in_array($this->question->status, [Question::SUBMITTED], true),
                'review' => $user->hasControl('review_answers'),
            ],
        ])->title($this->question->reference);
    }
}
