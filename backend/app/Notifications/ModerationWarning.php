<?php

namespace App\Notifications;

/**
 * A formal warning to a member about community rules (SAF-REQ04).
 */
class ModerationWarning extends TaxClubNotification
{
    public function __construct(public string $reason)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'moderation_warning';
    }

    public static function label(): string
    {
        return __('Warning about community rules');
    }

    public static function placeholders(): array
    {
        return ['reason'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: You received a warning for breaking the community rules: :reason. Further breaches may limit your account.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reason' => $this->reason];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.community');
    }
}
