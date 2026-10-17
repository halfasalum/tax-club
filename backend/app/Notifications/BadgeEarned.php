<?php

namespace App\Notifications;

/**
 * A member earned a badge (GAM-REQ09). Inbox only by default.
 */
class BadgeEarned extends TaxClubNotification
{
    public function __construct(public string $nameSw, public string $nameEn, public string $icon)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'badge_earned';
    }

    public static function label(): string
    {
        return __('Badge earned');
    }

    public static function placeholders(): array
    {
        return ['badge'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public static function preferenceCategory(): ?string
    {
        return 'achievements';
    }

    public function message(): string
    {
        return __('TRA Tax Club: Well done! You earned the ":badge" badge.');
    }

    public function replacements(object $notifiable): array
    {
        return ['badge' => $this->icon.' '.(app()->getLocale() === 'en' ? $this->nameEn : $this->nameSw)];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.leaderboard');
    }
}
