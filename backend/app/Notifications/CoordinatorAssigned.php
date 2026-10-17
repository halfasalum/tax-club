<?php

namespace App\Notifications;

/**
 * Sent to a coordinator when a region is assigned (INS-REQ06).
 */
class CoordinatorAssigned extends TaxClubNotification
{
    public function __construct(public string $region, public ?string $temporaryPassword = null)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'coordinator_assigned';
    }

    public function message(): string
    {
        $text = __('TRA Tax Club: You are now Tax Club coordinator for :region. Sign in at :url to follow institutions and members.');

        return $this->temporaryPassword ? $text.' '.__('Temporary password: :password. Change it after signing in.') : $text;
    }

    public function replacements(object $notifiable): array
    {
        return ['region' => $this->region, 'url' => route('login'), 'password' => $this->temporaryPassword];
    }
}
