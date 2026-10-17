<?php

namespace App\Notifications;

/**
 * Sent to a coordinator when a region is assigned (INS-REQ06). A new
 * account's one-time password goes by SMS only, never into the inbox.
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

    public static function label(): string
    {
        return __('Coordinator assigned');
    }

    public static function placeholders(): array
    {
        return ['region', 'url'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: You are now Tax Club coordinator for :region. Sign in at :url to follow institutions and members.');
    }

    public function replacements(object $notifiable): array
    {
        return ['region' => $this->region, 'url' => route('login')];
    }

    public function toSms(object $notifiable): string
    {
        $message = parent::toSms($notifiable);

        return $this->temporaryPassword
            ? $message.' '.__('Temporary password: :password. Change it after signing in.', ['password' => $this->temporaryPassword])
            : $message;
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.members.index');
    }
}
