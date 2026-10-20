<?php

namespace App\Livewire\Member\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Upcoming events for the member and the ones they registered for
 * (EVT-REQ03, EVT-REQ04).
 */
#[Layout('components.layouts.app')]
class Index extends Component
{
    #[Url]
    public string $type = '';

    #[Url]
    public string $tab = 'upcoming';

    public function render(): mixed
    {
        $user = auth()->user();
        $registrations = EventRegistration::where('user_id', $user->user_id)->where('status', '!=', EventRegistration::CANCELLED)->get()->keyBy('event_id');

        $upcoming = Event::upcoming()
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->orderBy('starts_at')
            ->limit(100)
            ->get()
            ->filter(fn (Event $event) => $event->isVisibleTo($user))
            ->values();

        $mine = $this->tab === 'mine'
            ? Event::live()->whereIn('event_id', $registrations->keys())->orderByDesc('starts_at')->get()
            : collect();

        return view('livewire.member.events.index', [
            'upcoming' => $upcoming,
            'mine' => $mine,
            'registrations' => $registrations,
        ])->title(__('Events'));
    }
}
