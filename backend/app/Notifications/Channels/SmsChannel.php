<?php

namespace App\Notifications\Channels;

use App\Models\SmsMessage;
use App\Models\User;
use App\Support\Sms\SmsGateway;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Notification channel that sends SMS through the configured gateway and
 * records each message. Retries reuse the same log row, and a message that
 * was already sent is never sent twice (NOT-REQ02 §5, NOT-REQ07 §7).
 */
class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (blank($to) || ! method_exists($notification, 'toSms')) {
            return;
        }

        $record = SmsMessage::firstOrCreate(
            ['notification_id' => $notification->id, 'to' => $to],
            [
                'user_id' => $notifiable instanceof User ? $notifiable->user_id : null,
                'purpose' => method_exists($notification, 'smsPurpose') ? $notification->smsPurpose() : class_basename($notification),
                'message' => $notification->toSms($notifiable),
                'status' => SmsMessage::STATUS_PENDING,
                'provider' => $this->gateway->name(),
            ],
        );

        if ($record->status === SmsMessage::STATUS_SENT) {
            return;
        }

        $record->increment('attempts');
        $result = $this->gateway->send($to, $record->message);

        $record->forceFill([
            'status' => $result->accepted ? SmsMessage::STATUS_SENT : SmsMessage::STATUS_FAILED,
            'provider' => $this->gateway->name(),
            'provider_reference' => $result->reference,
            'error' => $result->error,
            'sent_at' => $result->accepted ? now() : null,
        ])->save();

        if (! $result->accepted) {
            // Lets the queue retry with backoff; the attempt is already logged.
            throw new RuntimeException('SMS not accepted: '.$result->error);
        }
    }
}
