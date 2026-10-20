<?php

namespace App\Notifications;

/**
 * A member was given a reward (RWD-REQ02).
 */
class RewardAwarded extends TaxClubNotification
{
    public function __construct(public string $rewardSw, public string $rewardEn, public string $reason)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'reward_awarded';
    }

    public static function label(): string
    {
        return __('Reward awarded');
    }

    public static function placeholders(): array
    {
        return ['reward', 'reason'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: Congratulations! You have been awarded :reward for :reason. We will tell you how to collect it.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reward' => app()->getLocale() === 'en' ? $this->rewardEn : $this->rewardSw, 'reason' => $this->reason];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.achievements');
    }
}
