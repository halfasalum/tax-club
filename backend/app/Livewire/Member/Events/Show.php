<?php

namespace App\Livewire\Member\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\EventManager;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * An event for members: details, registration or waitlist, updates and
 * materials (EVT-REQ04, EVT-REQ05, EVT-REQ07).
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Event $event;

    public string $answer = '';

    public ?string $statusMessage = null;

    public function mount(string $slug): void
    {
        $this->event = Event::live()->where('slug', $slug)->firstOrFail();
        abort_unless($this->event->isVisibleTo(auth()->user()), 404);
    }

    public function register(): void
    {
        $this->validate(['answer' => 'nullable|string|max:1000']);
        $this->resetErrorBag();

        try {
            $registration = EventManager::register($this->event, auth()->user(), $this->answer);
        } catch (InvalidArgumentException $exception) {
            $this->addError('register', $exception->getMessage());

            return;
        }

        $this->answer = '';
        $this->statusMessage = $registration->status === EventRegistration::WAITLISTED
            ? __('The event is full, so you are on the waitlist. We will tell you if a place opens up.')
            : __('You are registered. Bring your membership card for check-in.');
    }

    public function cancelRegistration(): void
    {
        try {
            EventManager::cancelRegistration($this->event, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('register', $exception->getMessage());

            return;
        }

        $this->statusMessage = __('Your registration was cancelled.');
    }

    public function render(): mixed
    {
        $this->event->refresh();
        $user = auth()->user();
        $registration = EventManager::registrationFor($this->event, $user);

        return view('livewire.member.events.show', [
            'registration' => $registration?->status === EventRegistration::CANCELLED ? null : $registration,
            'problem' => EventManager::registrationProblem($this->event, $user),
            'updates' => EventManager::updatesFor($this->event, $user),
        ])->title($this->event->title);
    }
}
