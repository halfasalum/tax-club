<?php

namespace App\Livewire\Admin\Feedback;

use App\Models\Feedback;
use App\Models\User;
use App\Support\FeedbackDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Handle one feedback case: classify, assign, note, respond, close
 * (FBK-REQ02, FBK-REQ03).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Feedback $feedback;

    public string $category = '';

    public string $priority = '';

    public string $area = '';

    public string $assignee = '';

    public string $note = '';

    public string $response = '';

    public bool $closeAfter = true;

    public string $reason = '';

    public ?string $statusMessage = null;

    public function mount(Feedback $feedback): void
    {
        abort_unless(Feedback::visibleTo(auth()->user())->whereKey($feedback->feedback_id)->exists(), 404);
        $this->feedback = $feedback;
        $this->fillFromCase();
    }

    public function classify(): void
    {
        $this->authorizeAny(['manage_feedback']);
        $this->attempt(fn () => FeedbackDesk::classify($this->feedback, ['category' => $this->category, 'priority' => $this->priority, 'area' => $this->area], auth()->user()), __('Saved.'));
    }

    public function assign(): void
    {
        $this->authorizeAny(['manage_feedback']);
        $assignee = $this->assignee !== '' ? User::findOrFail($this->assignee) : null;
        $this->attempt(fn () => FeedbackDesk::assign($this->feedback, $assignee, auth()->user()), $assignee ? __('Assigned to :name.', ['name' => $assignee->full_name]) : __('Unassigned.'));
    }

    public function takeIt(): void
    {
        $this->authorizeAny(['respond_to_feedback', 'manage_feedback']);
        $this->attempt(fn () => FeedbackDesk::assign($this->feedback, auth()->user(), auth()->user()), __('Assigned to you.'));
    }

    public function addNote(): void
    {
        $this->authorizeAny(['respond_to_feedback', 'manage_feedback']);
        $this->attempt(fn () => FeedbackDesk::note($this->feedback, $this->note, auth()->user()), __('Note added.'));
    }

    public function respond(): void
    {
        $this->authorizeAny(['respond_to_feedback']);
        $this->attempt(fn () => FeedbackDesk::respond($this->feedback, $this->response, auth()->user(), $this->closeAfter), __('Response sent to the member.'));
    }

    public function close(): void
    {
        $this->authorizeAny(['respond_to_feedback']);
        $this->attempt(fn () => FeedbackDesk::close($this->feedback, auth()->user(), $this->reason), __('Closed.'));
    }

    public function reopen(): void
    {
        $this->authorizeAny(['respond_to_feedback', 'manage_feedback']);
        $this->attempt(fn () => FeedbackDesk::reopen($this->feedback, auth()->user(), $this->reason), __('Reopened.'));
    }

    private function fillFromCase(): void
    {
        $this->category = $this->feedback->category;
        $this->priority = $this->feedback->priority;
        $this->area = (string) $this->feedback->area;
        $this->assignee = (string) $this->feedback->assigned_to;
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        if ($this->feedback->isSafeguarding() && ! auth()->user()->hasControl('handle_safeguarding_concerns') && $this->feedback->assigned_to !== auth()->id()) {
            abort(403);
        }

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('action', $exception->getMessage());

            return;
        }

        $this->reset('note', 'response', 'reason');
        $this->feedback->refresh();
        $this->fillFromCase();
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
        $officers = User::where('account_status', User::STATUS_ACTIVE)
            ->whereHas('roles.controls', fn ($query) => $query->whereIn('slug', $this->feedback->isSafeguarding() ? ['handle_safeguarding_concerns'] : ['respond_to_feedback', 'handle_safeguarding_concerns']))
            ->orderBy('full_name')
            ->limit(200)
            ->get(['user_id', 'full_name']);

        return view('livewire.admin.feedback.show', [
            'member' => $this->feedback->user()->with('institution:institution_id,name,region')->first(),
            'history' => $this->feedback->events()->with('user:user_id,full_name,user_type')->get(),
            'officers' => $officers,
            'relatedLabel' => FeedbackDesk::relatedLabel($this->feedback),
            'can' => [
                'manage' => $user->hasControl('manage_feedback'),
                'respond' => $user->hasControl('respond_to_feedback'),
            ],
        ])->title($this->feedback->reference);
    }
}
