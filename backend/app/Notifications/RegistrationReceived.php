<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent when a student submits a registration (REG-REQ01 §8).
 */
class RegistrationReceived extends Notification implements ShouldQueue
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
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function smsPurpose(): string
    {
        return 'registration_received';
    }

    public function toSms(object $notifiable): string
    {
        return __('TRA Tax Club: Hi :name, we received your registration :reference. Your patron will confirm your membership and we will let you know by SMS.', [
            'name' => $notifiable->publicName(),
            'reference' => $notifiable->registration_reference,
        ]);
    }
}
