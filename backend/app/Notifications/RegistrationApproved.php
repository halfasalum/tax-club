<?php

namespace App\Notifications;

/**
 * Sent when a registration is approved, with the membership number (REG-REQ02/03).
 */
class RegistrationApproved extends TaxClubNotification
{
    public static function key(): string
    {
        return 'registration_approved';
    }

    public static function label(): string
    {
        return __('Registration approved');
    }

    public static function placeholders(): array
    {
        return ['name', 'number', 'url'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: Congratulations :name! Your membership is approved. Membership no. :number. Sign in at :url');
    }

    public function replacements(object $notifiable): array
    {
        return ['name' => $notifiable->publicName(), 'number' => $notifiable->membership_id, 'url' => route('login')];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.card');
    }
}
