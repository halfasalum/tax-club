<?php

namespace App\Notifications;

/**
 * Thanks a member whose report was reviewed, without details (SAF-REQ02). Inbox only.
 */
class ReportReviewed extends TaxClubNotification
{
    public function __construct(public string $reference)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'report_reviewed';
    }

    public static function label(): string
    {
        return __('Report reviewed');
    }

    public static function placeholders(): array
    {
        return ['reference'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public function message(): string
    {
        return __('TRA Tax Club: Thank you. Our moderators reviewed your report :reference and took the right action.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reference' => $this->reference];
    }

    public function url(object $notifiable): ?string
    {
        return null;
    }
}
