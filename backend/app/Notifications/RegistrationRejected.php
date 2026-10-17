<?php

namespace App\Notifications;

/**
 * Sent when a registration is rejected, with the reason (REG-REQ02).
 */
class RegistrationRejected extends TaxClubNotification
{
    public static function key(): string
    {
        return 'registration_rejected';
    }

    public static function label(): string
    {
        return __('Registration not approved');
    }

    public static function placeholders(): array
    {
        return ['reference', 'reason'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: Your registration :reference was not approved. Reason: :reason. Contact your Tax Club patron for help.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reference' => $notifiable->registration_reference, 'reason' => $notifiable->status_reason];
    }
}
