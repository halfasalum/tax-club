<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * One-time code for resetting a password (REG-REQ05).
 */
class PasswordResetCode extends Notification implements ShouldQueue
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
        return 'password_reset_code';
    }

    public function toSms(object $notifiable): string
    {
        return __('TRA Tax Club: Your password reset code is :code. It expires in :minutes minutes. Never share this code with anyone.', [
            'code' => $this->code,
            'minutes' => $this->minutes,
        ]);
    }
}
