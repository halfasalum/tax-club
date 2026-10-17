<?php

namespace App\Notifications;

class AccountDeactivated extends AccountStatusChanged
{
    public static function key(): string
    {
        return 'account_deactivated';
    }

    public static function label(): string
    {
        return __('Account deactivated');
    }

    public function message(): string
    {
        return __('TRA Tax Club: Hi :name, your account has been deactivated. Reason: :reason. Contact your Tax Club patron for help.');
    }
}
