<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Send a notification without letting a delivery problem break the user's
 * action. Failures are reported to the log; queued notifications still retry.
 */
final class Notify
{
    public static function send(object $notifiable, Notification $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
