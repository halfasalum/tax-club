<?php

namespace App\Support\Sms;

use App\Models\SmsMessage;
use App\Support\Audit;
use App\Support\NotificationCatalogue;
use InvalidArgumentException;

/**
 * Controlled retry of failed SMS by an administrator (NOT-REQ07). Codes are
 * never resent: they expire, so the person should ask for a new one.
 */
final class SmsRetry
{
    public static function canRetry(SmsMessage $sms): bool
    {
        return $sms->status === SmsMessage::STATUS_FAILED && ! self::isSensitive($sms);
    }

    public static function isSensitive(SmsMessage $sms): bool
    {
        $class = NotificationCatalogue::find($sms->purpose);

        return $class !== null && $class::sensitive();
    }

    /**
     * Message text safe to show in the log: digits of codes and one-time
     * passwords are hidden.
     */
    public static function displayText(SmsMessage $sms): string
    {
        $text = $sms->message;

        if (self::isSensitive($sms)) {
            $text = preg_replace('/\b\d{4,8}\b/', '••••••', $text);
        }

        return preg_replace('/(Temporary password|Nenosiri la muda): \S+/u', '$1: ••••••', $text);
    }

    public static function retry(SmsMessage $sms, SmsGateway $gateway): bool
    {
        if (! self::canRetry($sms)) {
            throw new InvalidArgumentException(__('Only failed messages without codes can be sent again.'));
        }

        $sms->increment('attempts');
        $result = $gateway->send($sms->to, $sms->message);

        $sms->forceFill([
            'status' => $result->accepted ? SmsMessage::STATUS_SENT : SmsMessage::STATUS_FAILED,
            'provider' => $gateway->name(),
            'provider_reference' => $result->reference,
            'error' => $result->error,
            'sent_at' => $result->accepted ? now() : null,
        ])->save();

        Audit::record('sms.retried', after: ['sms_id' => $sms->id, 'purpose' => $sms->purpose, 'accepted' => $result->accepted]);

        return $result->accepted;
    }
}
