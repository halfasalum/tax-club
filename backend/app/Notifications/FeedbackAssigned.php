<?php

namespace App\Notifications;

/**
 * Tells an officer that feedback was assigned to them (FBK-REQ02).
 */
class FeedbackAssigned extends TaxClubNotification
{
    public function __construct(public string $reference, public string $subject, public string $feedbackId)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'feedback_assigned';
    }

    public static function label(): string
    {
        return __('Feedback assigned to an officer');
    }

    public static function placeholders(): array
    {
        return ['reference', 'title'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public function message(): string
    {
        return __('TRA Tax Club: Feedback :reference ":title" was assigned to you.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reference' => $this->reference, 'title' => $this->subject];
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.feedback.show', $this->feedbackId);
    }
}
