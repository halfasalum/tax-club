<?php

namespace App\Notifications;

/**
 * Sent to a patron when assigned to a Tax Club (INS-REQ05). A new account's
 * one-time password goes by SMS only, never into the inbox.
 */
class PatronAssigned extends TaxClubNotification
{
    public function __construct(public string $clubName, public ?string $temporaryPassword = null)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'patron_assigned';
    }

    public static function label(): string
    {
        return __('Patron assigned');
    }

    public static function placeholders(): array
    {
        return ['club', 'url'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: You are now the patron of :club. Sign in at :url to confirm student registrations.');
    }

    public function replacements(object $notifiable): array
    {
        return ['club' => $this->clubName, 'url' => route('login')];
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
        return route('admin.registrations');
    }
}
