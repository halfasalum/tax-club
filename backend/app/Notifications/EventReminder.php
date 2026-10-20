<?php

namespace App\Notifications;

/**
 * Automatic reminder before an event starts (NOT-REQ04).
 */
class EventReminder extends TaxClubNotification
{
    public function __construct(public string $eventTitle, public string $when, public string $place, public string $slug)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'event_reminder';
    }

    public static function label(): string
    {
        return __('Event reminder');
    }

    public static function placeholders(): array
    {
        return ['title', 'when', 'place'];
    }

    public static function preferenceCategory(): ?string
    {
        return 'events';
    }

    public function message(): string
    {
        return __('TRA Tax Club reminder: ":title" starts :when at :place. Bring your membership card for check-in.');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->eventTitle, 'when' => $this->when, 'place' => $this->place];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.events.show', $this->slug);
    }
}
