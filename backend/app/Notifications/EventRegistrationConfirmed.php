<?php

namespace App\Notifications;

/**
 * Confirms a member's registration or waitlist place (EVT-REQ04/05).
 */
class EventRegistrationConfirmed extends TaxClubNotification
{
    public function __construct(public string $eventTitle, public string $when, public bool $waitlisted, public string $slug)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'event_registration';
    }

    public static function label(): string
    {
        return __('Event registration confirmed');
    }

    public static function placeholders(): array
    {
        return ['title', 'when', 'status'];
    }

    public static function preferenceCategory(): ?string
    {
        return 'events';
    }

    public function message(): string
    {
        return __('TRA Tax Club: :status for ":title" on :when.');
    }

    public function replacements(object $notifiable): array
    {
        return [
            'title' => $this->eventTitle,
            'when' => $this->when,
            'status' => $this->waitlisted ? __('You are on the waitlist') : __('Your place is confirmed'),
        ];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.events.show', $this->slug);
    }
}
