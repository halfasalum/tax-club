<?php

namespace App\Livewire\Admin\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\EventManager;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Check members in at the door by scanning the QR code on their
 * membership card or typing the membership number (EVT-REQ06).
 */
#[Layout('components.layouts.app')]
class CheckIn extends Component
{
    public Event $event;

    public string $input = '';

    /** @var array{result: string, name: ?string, number: ?string, institution: ?string, photo: ?string, minor: bool}|null */
    public ?array $outcome = null;

    /** Card waiting for a walk-in decision. */
    public ?string $pending = null;

    public function mount(Event $event): void
    {
        abort_unless(auth()->user()->hasControl('check_in_attendees'), 403);
        $this->event = $event;
    }

    public function check(bool $allowWalkIn = false): void
    {
        $input = $allowWalkIn ? (string) $this->pending : trim($this->input);
        $this->resetErrorBag();

        if ($input === '') {
            return;
        }

        $key = 'check-in:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 120)) {
            $this->addError('input', __('Too many checks. Wait a minute and try again.'));

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            $result = EventManager::checkIn($this->event, $input, auth()->user(), $allowWalkIn);
        } catch (InvalidArgumentException $exception) {
            $this->addError('input', $exception->getMessage());

            return;
        }

        $member = $result['member'];
        $this->outcome = [
            'result' => $result['result'],
            'name' => $member?->full_name,
            'number' => $member?->membership_id,
            'institution' => $member?->institution?->name,
            'photo' => $member?->photo_path ? route('members.photo', ['member' => $member, 'v' => md5($member->photo_path)]) : null,
            'minor' => (bool) $member?->isMinor(),
        ];
        $this->pending = $result['result'] === 'not_registered' ? $input : null;
        $this->input = '';
        $this->dispatch('checked', result: $result['result']);
    }

    public function admitWalkIn(): void
    {
        $this->check(true);
    }

    public function render(): mixed
    {
        $this->event->refresh();

        return view('livewire.admin.events.check-in', [
            'open' => EventManager::checkInIsOpen($this->event),
            'attended' => $this->event->registrations()->where('status', EventRegistration::ATTENDED)->count(),
            'expected' => $this->event->registrations()->holdingPlace()->count(),
            'recent' => $this->event->registrations()->where('status', EventRegistration::ATTENDED)->with('user:user_id,full_name,membership_id')->latest('checked_in_at')->limit(8)->get(),
        ])->title(__('Check-in: :title', ['title' => $this->event->title]));
    }
}
