<?php

namespace App\Notifications;

/**
 * Tells a member that part of their account was limited (SAF-REQ04).
 */
class RestrictionApplied extends TaxClubNotification
{
    public function __construct(public string $feature, public ?string $until, public string $reason)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'restriction_applied';
    }

    public static function label(): string
    {
        return __('Account limited');
    }

    public static function placeholders(): array
    {
        return ['feature', 'date', 'reason'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: You cannot use :feature until :date. Reason: :reason');
    }

    public function replacements(object $notifiable): array
    {
        return ['feature' => $this->feature, 'date' => $this->until ?? __('further notice'), 'reason' => $this->reason];
    }

    public function url(object $notifiable): ?string
    {
        return null;
    }
}
