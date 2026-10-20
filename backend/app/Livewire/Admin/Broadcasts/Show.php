<?php

namespace App\Livewire\Admin\Broadcasts;

use App\Models\Broadcast;
use App\Support\Broadcasts;
use App\Support\Review;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Approve, send, schedule or cancel a broadcast (NOT-REQ03, NOT-REQ04).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Broadcast $broadcast;

    public string $reviewNote = '';

    public string $sendAt = '';

    public string $reason = '';

    public ?string $statusMessage = null;

    public function mount(Broadcast $broadcast): void
    {
        abort_unless(auth()->user()->hasControl('create_broadcast') || auth()->user()->hasControl('approve_broadcast'), 403);
        $this->broadcast = $broadcast;
        $this->statusMessage = session('status');
    }

    public function submit(): void
    {
        $this->authorizeAny(['create_broadcast']);
        $this->attempt(fn () => Broadcasts::submit($this->broadcast, auth()->user()), __('Sent for approval.'));
    }

    public function approve(): void
    {
        $this->authorizeAny(['approve_broadcast']);
        $this->attempt(fn () => Broadcasts::approve($this->broadcast, auth()->user(), trim($this->reviewNote) ?: null), __('Approved. Send it now or schedule it.'));
    }

    public function returnForChanges(): void
    {
        $this->authorizeAny(['approve_broadcast']);
        $this->attempt(fn () => Broadcasts::returnForChanges($this->broadcast, auth()->user(), trim($this->reviewNote)), __('Returned to the author with your notes.'));
    }

    public function reject(): void
    {
        $this->authorizeAny(['approve_broadcast']);
        $this->attempt(fn () => Broadcasts::reject($this->broadcast, auth()->user(), trim($this->reviewNote)), __('Rejected.'));
    }

    public function send(): void
    {
        $this->authorizeAny(['approve_broadcast']);
        $this->validate(['sendAt' => 'nullable|date']);
        $at = $this->sendAt ? Carbon::parse($this->sendAt) : null;
        $this->attempt(fn () => Broadcasts::send($this->broadcast, auth()->user(), $at), $at?->isFuture() ? __('Scheduled.') : __('Sent. Messages are on their way.'));
    }

    public function cancel(): void
    {
        $this->authorizeAny(['approve_broadcast', 'create_broadcast']);
        $this->attempt(fn () => Broadcasts::cancel($this->broadcast, auth()->user(), $this->reason), __('Cancelled.'));
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

        $this->reset('reviewNote', 'reason', 'sendAt');
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
        $this->broadcast->refresh();
        $user = auth()->user();
        $recipients = in_array($this->broadcast->status, [Broadcast::SENT, Broadcast::SENDING], true) ? $this->broadcast->recipients_count : Broadcasts::recipientCount($this->broadcast);

        return view('livewire.admin.broadcasts.show', [
            'problems' => in_array($this->broadcast->status, Review::EDITABLE, true) ? Broadcasts::readinessErrors($this->broadcast) : [],
            'recipients' => (int) $recipients,
            'history' => $this->broadcast->history(),
            'can' => ['create' => $user->hasControl('create_broadcast'), 'approve' => $user->hasControl('approve_broadcast')],
            'ownSubmission' => $this->broadcast->status === Review::IN_REVIEW && $this->broadcast->submitted_by === $user->user_id,
        ])->title($this->broadcast->title);
    }
}
