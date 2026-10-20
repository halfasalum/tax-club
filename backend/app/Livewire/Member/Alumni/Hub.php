<?php

namespace App\Livewire\Member\Alumni;

use App\Models\AlumniStory;
use App\Support\AlumniNetwork;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The alumni hub: stories, career talks, the directory and, for alumni,
 * their profile and story submissions (ALM-REQ01-03).
 */
#[Layout('components.layouts.app')]
class Hub extends Component
{
    public string $storyTitle = '';

    public string $storyBody = '';

    public string $storyKind = 'story';

    public ?string $statusMessage = null;

    public function submitStory(): void
    {
        $this->validate(['storyTitle' => 'required|string|min:5|max:200', 'storyBody' => 'required|string|min:80|max:10000', 'storyKind' => 'required|in:'.implode(',', AlumniStory::KINDS)]);

        try {
            AlumniNetwork::submitStory(auth()->user(), $this->storyTitle, $this->storyBody, $this->storyKind);
        } catch (InvalidArgumentException $exception) {
            $this->addError('storyBody', $exception->getMessage());

            return;
        }

        $this->reset('storyTitle', 'storyBody', 'storyKind');
        $this->statusMessage = __('Thank you! Your story will appear after a quick review.');
    }

    public function render(): mixed
    {
        $user = auth()->user();

        return view('livewire.member.alumni.hub', [
            'isAlumnus' => AlumniNetwork::isAlumnus($user),
            'profile' => AlumniNetwork::isAlumnus($user) ? AlumniNetwork::profileFor($user) : null,
            'stories' => AlumniStory::published()->with('author:user_id,full_name')->latest('published_at')->limit(9)->get(),
            'myStories' => AlumniStory::where('user_id', $user->user_id)->latest()->get(),
            'sessions' => AlumniNetwork::upcomingSessions($user),
            'canUseDirectory' => AlumniNetwork::canUseDirectory($user),
            'requests' => AlumniNetwork::pendingRequestsFor($user)->count(),
        ])->title(__('Alumni'));
    }
}
