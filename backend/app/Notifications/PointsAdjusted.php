<?php

namespace App\Notifications;

/**
 * An officer added or removed points (GAM-REQ08).
 */
class PointsAdjusted extends TaxClubNotification
{
    public function __construct(public int $points, public string $reason)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'points_adjusted';
    }

    public static function label(): string
    {
        return __('Points adjusted');
    }

    public static function placeholders(): array
    {
        return ['points', 'reason'];
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
        return ! isset($this->points) || $this->points > 0
            ? __('TRA Tax Club: :points points were added to your account. Reason: :reason')
            : __('TRA Tax Club: :points points were removed from your account. Reason: :reason');
    }

    public function replacements(object $notifiable): array
    {
        return ['points' => abs($this->points), 'reason' => $this->reason];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.leaderboard');
    }
}
