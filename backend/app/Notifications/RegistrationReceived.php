<?php

namespace App\Notifications;

/**
 * Sent when a registration is submitted (REG-REQ01).
 */
class RegistrationReceived extends TaxClubNotification
{
    public static function key(): string
    {
        return 'registration_received';
    }

    public static function label(): string
    {
        return __('Registration received');
    }

    public static function placeholders(): array
    {
        return ['name', 'reference'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: Hi :name, we received your registration :reference. Your patron will confirm your membership and we will let you know by SMS.');
    }

    public function replacements(object $notifiable): array
    {
        return ['name' => $notifiable->publicName(), 'reference' => $notifiable->registration_reference];
    }
}
