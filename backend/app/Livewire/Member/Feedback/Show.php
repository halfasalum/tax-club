<?php

namespace App\Livewire\Member\Feedback;

use App\Models\Feedback;
use App\Support\FeedbackDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Status, response and history of one feedback case (FBK-REQ04).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Feedback $feedback;

    public string $reply = '';

    public ?string $statusMessage = null;

    public function mount(string $reference): void
    {
        $this->feedback = Feedback::where('reference', $reference)->where('user_id', auth()->id())->firstOrFail();
        $this->statusMessage = session('status');
    }

    public function sendReply(): void
    {
        $this->validate(['reply' => 'required|string|max:5000']);

        try {
            FeedbackDesk::addMemberMessage($this->feedback, auth()->user(), $this->reply);
        } catch (InvalidArgumentException $exception) {
            $this->addError('reply', $exception->getMessage());

            return;
        }

        $this->reply = '';
        $this->statusMessage = __('Added. The officer will see your message.');
    }

    public function render(): mixed
    {
        $this->feedback->refresh();

        return view('livewire.member.feedback.show', [
            'history' => FeedbackDesk::publicHistory($this->feedback)->load('user:user_id,full_name,user_type'),
            'relatedLabel' => FeedbackDesk::relatedLabel($this->feedback),
        ])->title($this->feedback->reference);
    }
}
