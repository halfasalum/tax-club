<?php

namespace App\Notifications;

/**
 * Tells a member that their feedback was answered or closed (FBK-REQ03/04).
 */
class FeedbackUpdated extends TaxClubNotification
{
    public function __construct(public string $reference, public string $statusLabel)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'feedback_updated';
    }

    public static function label(): string
    {
        return __('Feedback answered or closed');
    }

    public static function placeholders(): array
    {
        return ['reference', 'status'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public function message(): string
    {
        return __('TRA Tax Club: Your feedback :reference is now ":status". Open it to read the response.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reference' => $this->reference, 'status' => $this->statusLabel];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.feedback.show', $this->reference);
    }
}
