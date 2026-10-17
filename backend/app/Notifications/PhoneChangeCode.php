<?php

namespace App\Notifications;

/**
 * Code that confirms a new phone number (PRO-REQ02).
 */
class PhoneChangeCode extends TaxClubNotification
{
    public function __construct(public string $code, public int $minutes)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'phone_change_code';
    }

    public static function label(): string
    {
        return __('Phone number confirmation code');
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
        return __('TRA Tax Club: Your code to confirm this phone number is :code. It expires in :minutes minutes.');
    }

    public function replacements(object $notifiable): array
    {
        return ['code' => $this->code, 'minutes' => $this->minutes];
    }
}
