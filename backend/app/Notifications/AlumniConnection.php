<?php

namespace App\Notifications;

/**
 * A connection request or acceptance in the alumni network (ALM-REQ02). Inbox only.
 */
class AlumniConnection extends TaxClubNotification
{
    public function __construct(public string $name, public bool $accepted)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'alumni_connection';
    }

    public static function label(): string
    {
        return __('Alumni connection');
    }

    public static function placeholders(): array
    {
        return ['name', 'update'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public static function preferenceCategory(): ?string
    {
        return 'community';
    }

    public function message(): string
    {
        return __('TRA Tax Club: :name :update');
    }

    public function replacements(object $notifiable): array
    {
        return ['name' => $this->name, 'update' => $this->accepted ? __('accepted your connection request.') : __('would like to connect with you.')];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.alumni.directory');
    }
}
