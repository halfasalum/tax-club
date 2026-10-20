<?php

namespace App\Livewire\Member\Feedback;

use App\Models\Feedback;
use App\Support\FeedbackDesk;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Send feedback and follow what happened to it (FBK-REQ01, FBK-REQ04).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    /** What the feedback is about, e.g. "event:{id}" from an event page. */
    #[Url]
    public string $about = '';

    public string $category = 'suggestion';

    public string $subject = '';

    public string $message = '';

    public function mount(): void
    {
        [$type] = explode(':', $this->about.':');
        $this->category = match ($type) {
            'event' => 'event',
            'lesson', 'quiz' => 'content',
            default => $this->category,
        };
    }

    public function send(): mixed
    {
        $this->validate([
            'category' => 'required|in:'.implode(',', Feedback::CATEGORIES),
            'subject' => 'required|string|min:4|max:200',
            'message' => 'required|string|min:10|max:5000',
        ]);

        [$type, $id] = array_pad(explode(':', $this->about, 2), 2, null);

        try {
            $feedback = FeedbackDesk::submit(auth()->user(), [
                'category' => $this->category,
                'subject' => $this->subject,
                'message' => $this->message,
                'related_type' => in_array($type, ['event', 'lesson', 'quiz', 'opportunity'], true) ? $type : null,
                'related_id' => in_array($type, ['event', 'lesson', 'quiz', 'opportunity'], true) ? $id : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('send', $exception->getMessage());

            return null;
        }

        session()->flash('status', __('Thank you. Your reference is :reference.', ['reference' => $feedback->reference]));

        return $this->redirectRoute('member.feedback.show', $feedback->reference, navigate: true);
    }

    public function render(): mixed
    {
        $member = auth()->user();
        $feedback = Feedback::where('user_id', $member->user_id)->latest()->limit(50)->get();
        $relatedLabel = null;

        if ($this->about !== '') {
            [$type, $id] = array_pad(explode(':', $this->about, 2), 2, null);
            $relatedLabel = FeedbackDesk::relatedLabel(new Feedback(['related_type' => $type, 'related_id' => $id]));
        }

        return view('livewire.member.feedback.index', [
            'feedback' => $feedback,
            'relatedLabel' => $relatedLabel,
        ])->title(__('Feedback'));
    }
}
