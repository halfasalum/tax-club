<?php

namespace App\Livewire\Member;

use App\Models\Content;
use App\Models\Event;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public function render(): mixed
    {
        $member = auth()->user()->load('institution:institution_id,name,region');

        return view('livewire.member.dashboard', [
            'member' => $member,
            'lessons' => Content::live()->availableTo($member)->latest('published_at')->limit(4)->get(),
            'events' => Event::upcoming()->orderBy('starts_at')->limit(20)->get()->filter(fn (Event $event) => $event->isVisibleTo($member))->take(3),
            'attempts' => $member->quizAttempts()->count(),
        ])->title(__('My Tax Club'));
    }
}
