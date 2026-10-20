<?php

namespace App\Notifications;

/**
 * Tells the coordinator about an inactive mentorship or a concern (MNT-REQ04, MNT-REQ06). Inbox only.
 */
class MentorshipNeedsAttention extends TaxClubNotification
{
    public function __construct(public string $pair, public string $issue, public int $mentorshipId)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'mentorship_attention';
    }

    public static function label(): string
    {
        return __('Mentorship needs attention');
    }

    public static function placeholders(): array
    {
        return ['pair', 'issue'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public function message(): string
    {
        return __('TRA Tax Club: Mentorship :pair needs attention: :issue');
    }

    public function replacements(object $notifiable): array
    {
        return ['pair' => $this->pair, 'issue' => $this->issue];
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.mentorship.show', $this->mentorshipId);
    }
}
