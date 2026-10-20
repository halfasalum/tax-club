<?php

namespace App\Notifications;

/**
 * An event was postponed, moved or cancelled (EVT-REQ03).
 */
class EventChanged extends TaxClubNotification
{
    public function __construct(public string $eventTitle, public string $change, public ?string $reason, public string $slug)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'event_changed';
    }

    public static function label(): string
    {
        return __('Event postponed, moved or cancelled');
    }

    public static function placeholders(): array
    {
        return ['title', 'change', 'reason'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: ":title" :change. :reason');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->eventTitle, 'change' => $this->change, 'reason' => $this->reason];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.events.show', $this->slug);
    }
}
