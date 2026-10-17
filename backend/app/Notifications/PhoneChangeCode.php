<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Code that confirms a new phone number (PRO-REQ02).
 */
class PhoneChangeCode extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $code, public int $minutes)
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
        return 'phone_change_code';
    }

    public function toSms(object $notifiable): string
    {
        return __('TRA Tax Club: Your code to confirm this phone number is :code. It expires in :minutes minutes.', [
            'code' => $this->code,
            'minutes' => $this->minutes,
        ]);
    }
}
