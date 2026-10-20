<?php

namespace App\Notifications;

/**
 * Tells moderators or safeguarding officers about a new or escalated case (SAF-REQ02, SAF-REQ05). Inbox only by default.
 */
class ModerationAlert extends TaxClubNotification
{
    public function __construct(public string $reference, public string $what, public string $caseId)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'moderation_alert';
    }

    public static function label(): string
    {
        return __('Moderation case for officers');
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
        return __('TRA Tax Club: Case :reference needs attention: :title');
    }

    public function replacements(object $notifiable): array
    {
        return ['reference' => $this->reference, 'title' => $this->what];
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.moderation.show', $this->caseId);
    }
}
