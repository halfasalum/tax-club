<?php

namespace App\Notifications;

/**
 * Security notice after a password change or reset (REG-REQ05).
 */
class PasswordChanged extends TaxClubNotification
{
    public static function key(): string
    {
        return 'password_changed';
    }

    public static function label(): string
    {
        return __('Password changed');
    }

    public static function placeholders(): array
    {
        return [];
    }

    public static function required(): bool
    {
        return true;
    }

    public function message(): string
    {
        return __('TRA Tax Club: Your password was changed. If this was not you, contact your Tax Club patron or TRA immediately.');
    }

    public function replacements(object $notifiable): array
    {
        return [];
    }
}
