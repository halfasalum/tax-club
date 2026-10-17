<?php

namespace App\Notifications;

class AccountReactivated extends AccountStatusChanged
{
    public static function key(): string
    {
        return 'account_reactivated';
    }

    public static function label(): string
    {
        return __('Account reactivated');
    }

    public function message(): string
    {
        return __('TRA Tax Club: Hi :name, your account is active again. Sign in at :url');
    }
}
