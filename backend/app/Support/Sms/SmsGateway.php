<?php

namespace App\Support\Sms;

/**
 * Sends one SMS. Implementations must not throw for provider rejections;
 * they report them in the result so the message log stays accurate.
 */
interface SmsGateway
{
    /**
     * @param  string  $to  Phone number in +255XXXXXXXXX form
     */
    public function send(string $to, string $message): SmsResult;

    public function name(): string;
}
