<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Sent when an officer suspends, deactivates or reactivates an account
 * (REG-REQ06).
 */
class AccountStatusChanged extends TaxClubNotification
{
    public function __construct(public string $status, public ?string $reason = null)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'account_status_changed';
    }

    public function message(): string
    {
        return match ($this->status) {
            User::STATUS_ACTIVE => __('TRA Tax Club: Hi :name, your account is active again. Sign in at :url'),
            User::STATUS_SUSPENDED => __('TRA Tax Club: Hi :name, your account has been suspended. Reason: :reason. Contact your Tax Club patron for help.'),
            default => __('TRA Tax Club: Hi :name, your account has been deactivated. Reason: :reason. Contact your Tax Club patron for help.'),
        };
    }

    public function replacements(object $notifiable): array
    {
        return ['name' => $notifiable->publicName(), 'reason' => $this->reason, 'url' => route('login')];
    }
}
