<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent when a registration is approved and the account activated (REG-REQ02/03).
 */
class RegistrationApproved extends Notification implements ShouldQueue
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
        return 'registration_approved';
    }

    public function toSms(object $notifiable): string
    {
        return __('TRA Tax Club: Congratulations :name! Your membership is approved. Membership no. :number. Sign in at :url', [
            'name' => $notifiable->publicName(),
            'number' => $notifiable->membership_id,
            'url' => route('login'),
        ]);
    }
}
