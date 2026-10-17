<?php

namespace App\Notifications;

/**
 * One-time code for resetting a password (REG-REQ05).
 */
class PasswordResetCode extends TaxClubNotification
{
    public function __construct(public string $code, public int $minutes)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'password_reset_code';
    }

    public static function label(): string
    {
        return __('Password reset code');
    }

    public static function placeholders(): array
    {
        return ['code', 'minutes'];
    }

    public static function required(): bool
    {
        return true;
    }

    public static function sensitive(): bool
    {
        return true;
    }

    public function message(): string
    {
        return __('TRA Tax Club: Your password reset code is :code. It expires in :minutes minutes. Never share this code with anyone.');
    }

    public function replacements(object $notifiable): array
    {
        return ['code' => $this->code, 'minutes' => $this->minutes];
    }
}
