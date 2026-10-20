<?php

namespace App\Notifications;

/**
 * The decision on a mentor application (MNT-REQ01).
 */
class MentorApplicationDecided extends TaxClubNotification
{
    public function __construct(public bool $approved, public ?string $note)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'mentor_application';
    }

    public static function label(): string
    {
        return __('Mentor application decision');
    }

    public static function placeholders(): array
    {
        return ['decision', 'note'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: Your application to be a mentor was :decision. :note');
    }

    public function replacements(object $notifiable): array
    {
        return ['decision' => $this->approved ? __('approved') : __('not approved'), 'note' => $this->note];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.mentorship');
    }
}
