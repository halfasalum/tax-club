<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Support\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for Tax Club notifications (NOT-REQ01/02/06). Each one has a key, a
 * default message in the recipient's language with :placeholders, and the
 * values that fill them. Administrators can replace the wording and turn
 * the SMS or inbox channel off, except where a notification is required.
 * Sent through the queue; each channel is its own job with retries.
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
     * Name shown to administrators.
     */
    abstract public static function label(): string;

    /**
     * Default wording, translated, with :placeholders left in.
     */
    abstract public function message(): string;

    /**
     * @return array<string, string|int|null>
     */
    abstract public function replacements(object $notifiable): array;

    /**
     * Placeholders available in this notification's wording.
     *
     * @return list<string>
     */
    abstract public static function placeholders(): array;

    /**
     * Security messages whose SMS cannot be turned off.
     */
    public static function required(): bool
    {
        return false;
    }

    /**
     * One-time codes and passwords: SMS only, never kept in the inbox.
     */
    public static function sensitive(): bool
    {
        return false;
    }

    /**
     * Whether SMS is on before an administrator changes it. Routine updates
     * can default to the inbox only to save SMS costs.
     */
    public static function smsByDefault(): bool
    {
        return true;
    }

    /**
     * Optional category members can switch off (NOT-REQ05); null means the
     * notification is about their account and always delivered.
     */
    public static function preferenceCategory(): ?string
    {
        return null;
    }

    public static function template(): ?NotificationTemplate
    {
        return NotificationTemplate::find(static::key());
    }

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        $template = static::template();
        $channels = [];

        if ($notifiable instanceof User && ! static::sensitive() && ($template?->inbox_enabled ?? true)) {
            $channels[] = 'database';
        }

        if (static::required() || ($template?->sms_enabled ?? static::smsByDefault())) {
            $channels[] = SmsChannel::class;
        }

        // Members may switch off optional categories (NOT-REQ05).
        if ($notifiable instanceof User && static::preferenceCategory() && ! static::required()) {
            $preference = NotificationPreferences::for($notifiable, static::preferenceCategory());
            $channels = array_values(array_filter($channels, fn ($channel) => match ($channel) {
                'database' => $preference['inbox'],
                SmsChannel::class => $preference['sms'],
                default => true,
            }));
        }

        return $channels;
    }

    public function smsPurpose(): string
    {
        return static::key();
    }

    /**
     * The wording in the current language: the administrator's version if
     * there is one, otherwise the default.
     */
    public function text(): string
    {
        return static::template()?->bodyFor(app()->getLocale()) ?? $this->message();
    }

    public function toSms(object $notifiable): string
    {
        return self::fill($this->text(), $this->replacements($notifiable));
    }

    /**
     * Inbox entry (NOT-REQ06).
     *
     * @return array{key: string, message: string, url: ?string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => static::key(),
            'message' => self::fill($this->text(), $this->replacements($notifiable)),
            'url' => $this->url($notifiable),
        ];
    }

    /**
     * Where the inbox entry leads, if anywhere.
     */
    public function url(object $notifiable): ?string
    {
        return null;
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
