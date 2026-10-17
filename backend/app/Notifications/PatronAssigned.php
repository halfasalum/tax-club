<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sent to a teacher who has been made patron of a Tax Club (INS-REQ05 §9).
 */
class PatronAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public string $clubName, public ?string $temporaryPassword = null)
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
        return 'patron_assigned';
    }

    public function toSms(object $notifiable): string
    {
        $message = __('TRA Tax Club: You are now the patron of :club. Sign in at :url to confirm student registrations.', [
            'club' => $this->clubName,
            'url' => route('login'),
        ]);

        if ($this->temporaryPassword) {
            $message .= ' '.__('Temporary password: :password. Change it after signing in.', ['password' => $this->temporaryPassword]);
        }

        return $message;
    }
}
