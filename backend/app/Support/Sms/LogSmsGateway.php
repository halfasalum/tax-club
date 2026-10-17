<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes SMS to the application log instead of sending them. Used in local
 * development and tests, and as the default until Beem is configured.
 */
final class LogSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $to, string $message): SmsResult
    {
        Log::info('SMS to '.$to.': '.$message);

        return SmsResult::accepted('log-'.Str::lower(Str::random(10)));
    }
}
