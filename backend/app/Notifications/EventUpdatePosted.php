<?php

namespace App\Notifications;

/**
 * New update, instructions or material for an event (EVT-REQ07). Inbox only by default.
 */
class EventUpdatePosted extends TaxClubNotification
{
    public function __construct(public string $eventTitle, public string $updateTitle, public string $slug)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'event_update_posted';
    }

    public static function label(): string
    {
        return __('New event update or material');
    }

    public static function placeholders(): array
    {
        return ['title', 'update'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public static function preferenceCategory(): ?string
    {
        return 'events';
    }

    public function message(): string
    {
        return __('TRA Tax Club: New for ":title": :update');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->eventTitle, 'update' => $this->updateTitle];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.events.show', $this->slug);
    }
}
