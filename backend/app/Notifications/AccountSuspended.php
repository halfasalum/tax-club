<?php

namespace App\Notifications;

class AccountSuspended extends AccountStatusChanged
{
    public static function key(): string
    {
        return 'account_suspended';
    }

    public static function label(): string
    {
        return __('Account suspended');
    }

    public function message(): string
    {
        return __('TRA Tax Club: Hi :name, your account has been suspended. Reason: :reason. Contact your Tax Club patron for help.');
    }
}
