<?php

namespace App\Notifications;

/**
 * A saved opportunity closes soon (NOT-REQ04, OPP-REQ06).
 */
class OpportunityDeadlineReminder extends TaxClubNotification
{
    public function __construct(public string $opportunityTitle, public string $deadline, public string $slug)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'opportunity_deadline';
    }

    public static function label(): string
    {
        return __('Saved opportunity closing soon');
    }

    public static function placeholders(): array
    {
        return ['title', 'date'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public static function preferenceCategory(): ?string
    {
        return 'opportunities';
    }

    public function message(): string
    {
        return __('TRA Tax Club: ":title", which you saved, closes on :date. Apply before the deadline.');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->opportunityTitle, 'date' => $this->deadline];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.opportunities.show', $this->slug);
    }
}
