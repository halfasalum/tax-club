<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sign-in code for staff accounts (REG-REQ04, decision D-08).
 */
class StaffLoginCode extends Notification implements ShouldQueue
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
        return 'staff_login_code';
    }

    public function toSms(object $notifiable): string
    {
        return __('TRA Tax Club: Your sign-in code is :code. It expires in :minutes minutes. If you did not try to sign in, change your password now.', [
            'code' => $this->code,
            'minutes' => $this->minutes,
        ]);
    }
}
