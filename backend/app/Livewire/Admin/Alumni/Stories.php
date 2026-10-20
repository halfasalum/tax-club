<?php

namespace App\Livewire\Admin\Alumni;

use App\Models\AlumniStory;
use App\Support\AlumniNetwork;
use App\Support\Review;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Review alumni stories before they are published (ALM-REQ03).
 */
#[Layout('components.layouts.app')]
class Stories extends Component
{
    /** @var array<string, string> */
    public array $notes = [];

    public ?string $statusMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasControl('review_alumni_stories'), 403);
    }

    public function approve(string $storyId): void
    {
        $this->attempt(fn () => AlumniNetwork::approveStory(AlumniStory::findOrFail($storyId), auth()->user()), __('Published.'));
    }

    public function returnStory(string $storyId): void
    {
        $this->attempt(fn () => AlumniNetwork::returnStory(AlumniStory::findOrFail($storyId), auth()->user(), (string) ($this->notes[$storyId] ?? '')), __('Returned to the author.'));
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

        $this->statusMessage = $success;
    }

    public function render(): mixed
    {
        return view('livewire.admin.alumni.stories', [
            'waiting' => AlumniStory::where('editorial_status', Review::IN_REVIEW)->with('author:user_id,full_name,membership_id')->oldest('submitted_at')->get(),
            'published' => AlumniStory::published()->with('author:user_id,full_name')->latest('published_at')->limit(20)->get(),
        ])->title(__('Alumni stories'));
    }
}
