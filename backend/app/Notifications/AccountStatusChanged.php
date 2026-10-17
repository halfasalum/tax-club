<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Sent when an officer suspends, deactivates or reactivates an account
 * (REG-REQ06). One notification per new status so each wording can be
 * edited separately.
 */
abstract class AccountStatusChanged extends TaxClubNotification
{
    public function __construct(public ?string $reason = null)
    {
        parent::__construct();
    }

    public static function for(string $status, ?string $reason): self
    {
        return match ($status) {
            User::STATUS_ACTIVE => new AccountReactivated,
            User::STATUS_SUSPENDED => new AccountSuspended($reason),
            default => new AccountDeactivated($reason),
        };
    }

    public static function placeholders(): array
    {
        return ['name', 'reason', 'url'];
    }

    public function replacements(object $notifiable): array
    {
        return ['name' => $notifiable->publicName(), 'reason' => $this->reason, 'url' => route('login')];
    }
}
