<?php

namespace App\Notifications;

/**
 * Sign-in code for staff accounts (REG-REQ04, decision D-08).
 */
class StaffLoginCode extends TaxClubNotification
{
    public function __construct(public string $code, public int $minutes)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'staff_login_code';
    }

    public static function label(): string
    {
        return __('Staff sign-in code');
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
        return __('TRA Tax Club: Your sign-in code is :code. It expires in :minutes minutes. If you did not try to sign in, change your password now.');
    }

    public function replacements(object $notifiable): array
    {
        return ['code' => $this->code, 'minutes' => $this->minutes];
    }
}
