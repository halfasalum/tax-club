<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for Tax Club notifications (NOT-REQ02). Each one has a key, a default
 * message in the recipient's language with :placeholders, and the values
 * that fill them. Sent through the queue with retries.
 */
abstract class TaxClubNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * Stable identifier, also used as the SMS purpose and template key.
     */
    abstract public static function key(): string;

    /**
     * Default wording, translated, with :placeholders left in.
     */
    abstract public function message(): string;

    /**
     * @return array<string, string|int|null>
     */
    abstract public function replacements(object $notifiable): array;

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function smsPurpose(): string
    {
        return static::key();
    }

    public function toSms(object $notifiable): string
    {
        return self::fill($this->message(), $this->replacements($notifiable));
    }

    /**
     * @param  array<string, string|int|null>  $replacements
     */
    public static function fill(string $text, array $replacements): string
    {
        uksort($replacements, fn ($a, $b) => strlen($b) <=> strlen($a));

        return strtr($text, collect($replacements)->mapWithKeys(fn ($value, $key) => [':'.$key => (string) $value])->all());
    }
}
