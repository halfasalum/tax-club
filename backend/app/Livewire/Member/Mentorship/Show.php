<?php

namespace App\Livewire\Member\Mentorship;

use App\Models\Mentorship;
use App\Models\MentorshipSession;
use App\Support\MentorshipDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A mentorship for its participants: session log, closing and safety
 * concerns (MNT-REQ04-06).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Mentorship $mentorship;

    public string $held_on = '';

    public string $mode = 'video';

    public ?int $duration_minutes = 45;

    public string $topic = '';

    public string $summary = '';

    public bool $milestone = false;

    public string $outcome = 'goals_met';

    public string $closingNote = '';

    public ?int $rating = null;

    public string $concern = '';

    public ?string $statusMessage = null;

    public function mount(Mentorship $mentorship): void
    {
        abort_unless(MentorshipDesk::canView(auth()->user(), $mentorship), 404);
        $this->mentorship = $mentorship;
        $this->held_on = now()->toDateString();
    }

    public function logSession(): void
    {
        $this->validate(['held_on' => 'required|date', 'mode' => 'required|in:'.implode(',', MentorshipSession::MODES), 'duration_minutes' => 'nullable|integer|min:5|max:480', 'topic' => 'required|string|max:200', 'summary' => 'nullable|string|max:2000']);
        $this->attempt(fn () => MentorshipDesk::logSession($this->mentorship, auth()->user(), $this->only(['held_on', 'mode', 'duration_minutes', 'topic', 'summary', 'milestone'])), __('Session recorded.'));
        $this->reset('topic', 'summary', 'milestone');
    }

    public function close(): void
    {
        $this->attempt(fn () => MentorshipDesk::close($this->mentorship, auth()->user(), $this->outcome, $this->closingNote, $this->rating), __('Mentorship closed. Thank you!'));
    }

    public function raiseConcern(): void
    {
        $this->attempt(fn () => MentorshipDesk::raiseConcern($this->mentorship, auth()->user(), $this->concern), __('Your concern went to a safeguarding officer. Thank you for speaking up.'));
        $this->concern = '';
    }

    private function attempt(callable $action, string $success): void
    {
        $this->resetErrorBag();

        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->statusMessage = $success;
    }

    public function render(): mixed
    {
        $this->mentorship->refresh();
        $user = auth()->user();

        return view('livewire.member.mentorship.show', [
            'sessions' => $this->mentorship->sessions()->with('logger:user_id,full_name')->get(),
            'isParticipant' => $this->mentorship->involves($user),
            'isMentee' => $this->mentorship->mentee_id === $user->user_id,
        ])->title(__('Mentorship'));
    }
}
