<?php

namespace App\Notifications;

/**
 * A mentor and mentee were matched (MNT-REQ03).
 */
class MentorshipMatched extends TaxClubNotification
{
    public function __construct(public string $otherName, public bool $asMentor)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'mentorship_matched';
    }

    public static function label(): string
    {
        return __('Mentorship match');
    }

    public static function placeholders(): array
    {
        return ['name', 'role'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: You have been matched with :name as your :role. Open Mentorship to plan your first session.');
    }

    public function replacements(object $notifiable): array
    {
        return ['name' => $this->otherName, 'role' => $this->asMentor ? __('mentee') : __('mentor')];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.mentorship');
    }
}
