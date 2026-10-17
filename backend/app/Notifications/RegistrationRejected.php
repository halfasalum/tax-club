<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent when a registration is rejected, with the reason (REG-REQ02 §8).
 */
class RegistrationRejected extends Notification implements ShouldQueue
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
        return 'registration_rejected';
    }

    public function toSms(object $notifiable): string
    {
        return __('TRA Tax Club: Your registration :reference was not approved. Reason: :reason. Contact your Tax Club patron for help.', [
            'reference' => $notifiable->registration_reference,
            'reason' => $notifiable->status_reason,
        ]);
    }
}
