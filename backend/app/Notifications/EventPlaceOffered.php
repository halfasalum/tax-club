<?php

namespace App\Notifications;

/**
 * A waitlisted member got a place (EVT-REQ05).
 */
class EventPlaceOffered extends TaxClubNotification
{
    public function __construct(public string $eventTitle, public string $when, public string $slug)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'event_place_offered';
    }

    public static function label(): string
    {
        return __('Waitlist place offered');
    }

    public static function placeholders(): array
    {
        return ['title', 'when'];
    }

    public static function preferenceCategory(): ?string
    {
        return 'events';
    }

    public function message(): string
    {
        return __('TRA Tax Club: Good news! A place opened up and you are now registered for ":title" on :when.');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->eventTitle, 'when' => $this->when];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.events.show', $this->slug);
    }
}
