<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;

/**
 * A targeted or broadcast announcement written by an officer (NOT-REQ03).
 * The broadcast decides the channels; members may switch announcement
 * SMS or inbox messages off.
 */
class BroadcastMessage extends TaxClubNotification
{
    public function __construct(
        public string $broadcastId,
        public string $messageSw,
        public ?string $messageEn,
        public ?string $link,
        public bool $sms,
        public bool $inbox,
    ) {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'broadcast';
    }

    public static function label(): string
    {
        return __('Announcement (broadcast)');
    }

    public static function placeholders(): array
    {
        return ['message'];
    }

    public static function preferenceCategory(): ?string
    {
        return 'announcements';
    }

    public function via(object $notifiable): array
    {
        return array_values(array_filter(parent::via($notifiable), fn ($channel) => match ($channel) {
            'database' => $this->inbox,
            SmsChannel::class => $this->sms,
            default => true,
        }));
    }

    public function smsPurpose(): string
    {
        return 'broadcast';
    }

    public function message(): string
    {
        return __('TRA Tax Club: :message');
    }

    public function replacements(object $notifiable): array
    {
        $message = app()->getLocale() === 'en' && filled($this->messageEn) ? $this->messageEn : $this->messageSw;

        return ['message' => $message];
    }

    public function url(object $notifiable): ?string
    {
        return $this->link;
    }
}
