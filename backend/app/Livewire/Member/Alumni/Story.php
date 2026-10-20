<?php

namespace App\Livewire\Member\Alumni;

use App\Models\AlumniStory;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A published alumni story (ALM-REQ03).
 */
#[Layout('components.layouts.app')]
class Story extends Component
{
    public AlumniStory $story;

    public function mount(string $slug): void
    {
        $this->story = AlumniStory::published()->where('slug', $slug)->with('author.institution')->firstOrFail();
    }

    public function render(): mixed
    {
        return view('livewire.member.alumni.story')->title($this->story->title);
    }
}
