<?php

namespace App\Notifications;

/**
 * Sent when a member becomes Tax Club alumni (REG-REQ07).
 */
class AlumniStatusGranted extends TaxClubNotification
{
    public static function key(): string
    {
        return 'alumni_status_granted';
    }

    public static function label(): string
    {
        return __('Became alumni');
    }

    public static function placeholders(): array
    {
        return ['name', 'number'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: Congratulations :name! You are now Tax Club alumni. Your membership number :number, points and certificates stay with you.');
    }

    public function replacements(object $notifiable): array
    {
        return ['name' => $notifiable->publicName(), 'number' => $notifiable->membership_id];
    }
}
