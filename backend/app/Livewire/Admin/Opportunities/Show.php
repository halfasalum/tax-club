<?php

namespace App\Livewire\Admin\Opportunities;

use App\Models\Opportunity;
use App\Support\OpportunityBoard;
use App\Support\Review;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Review, publish, close, withdraw or archive an opportunity and see how
 * members engage with it (OPP-REQ02, OPP-REQ03, OPP-REQ08).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Opportunity $opportunity;

    public string $reviewNote = '';

    public string $publishAt = '';

    public string $reason = '';

    public ?string $statusMessage = null;

    public function mount(Opportunity $opportunity): void
    {
        $this->opportunity = $opportunity;
        $this->statusMessage = session('status');
    }

    public function submit(): void
    {
        $this->authorizeAny(['add_opportunity', 'edit_opportunity']);
        $this->attempt(fn () => OpportunityBoard::submit($this->opportunity, auth()->user()), __('Sent for review.'));
    }

    public function approve(): void
    {
        $this->authorizeAny(['review_opportunity']);
        $this->attempt(fn () => OpportunityBoard::approve($this->opportunity, auth()->user(), trim($this->reviewNote) ?: null), __('Approved. It can now be published.'));
    }

    public function returnForChanges(): void
    {
        $this->authorizeAny(['review_opportunity']);
        $this->attempt(fn () => OpportunityBoard::returnForChanges($this->opportunity, auth()->user(), trim($this->reviewNote)), __('Returned to the author with your notes.'));
    }

    public function reject(): void
    {
        $this->authorizeAny(['review_opportunity']);
        $this->attempt(fn () => OpportunityBoard::reject($this->opportunity, auth()->user(), trim($this->reviewNote)), __('Rejected.'));
    }

    public function publish(): void
    {
        $this->authorizeAny(['publish_opportunity']);
        $this->validate(['publishAt' => 'nullable|date']);
        $at = $this->publishAt ? Carbon::parse($this->publishAt) : null;
        $this->attempt(fn () => OpportunityBoard::publish($this->opportunity, auth()->user(), $at), $at?->isFuture() ? __('Scheduled.') : __('Published. Eligible members can see it now.'));
    }

    public function close(): void
    {
        $this->authorizeAny(['publish_opportunity']);
        $this->attempt(fn () => OpportunityBoard::close($this->opportunity, auth()->user(), $this->reason), __('Closed to new applications.'));
    }

    public function withdraw(): void
    {
        $this->authorizeAny(['publish_opportunity']);
        $this->attempt(fn () => OpportunityBoard::withdraw($this->opportunity, auth()->user(), $this->reason), __('Withdrawn.'));
    }

    public function archive(): void
    {
        $this->authorizeAny(['publish_opportunity']);
        $this->attempt(fn () => OpportunityBoard::archive($this->opportunity, auth()->user(), $this->reason), __('Archived.'));
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

        $this->reset('reviewNote', 'reason', 'publishAt');
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
        $this->opportunity->refresh();
        $user = auth()->user();

        return view('livewire.admin.opportunities.show', [
            'problems' => OpportunityBoard::readinessErrors($this->opportunity),
            'engagement' => OpportunityBoard::engagement($this->opportunity),
            'history' => $this->opportunity->history(),
            'can' => [
                'edit' => $user->hasControl('add_opportunity') || $user->hasControl('edit_opportunity'),
                'review' => $user->hasControl('review_opportunity'),
                'publish' => $user->hasControl('publish_opportunity'),
            ],
            'ownSubmission' => $this->opportunity->editorial_status === Review::IN_REVIEW && $this->opportunity->submitted_by === $user->user_id,
        ])->title($this->opportunity->title);
    }
}
