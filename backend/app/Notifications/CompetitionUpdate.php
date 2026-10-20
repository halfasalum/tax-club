<?php

namespace App\Notifications;

/**
 * Registration confirmed, results published or a competition changed (CMP-REQ03/05).
 */
class CompetitionUpdate extends TaxClubNotification
{
    public function __construct(public string $competitionTitle, public string $update, public string $slug)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'competition_update';
    }

    public static function label(): string
    {
        return __('Competition update');
    }

    public static function placeholders(): array
    {
        return ['title', 'update'];
    }

    public static function preferenceCategory(): ?string
    {
        return 'competitions';
    }

    public function message(): string
    {
        return __('TRA Tax Club: ":title": :update');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->competitionTitle, 'update' => $this->update];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.competitions.show', $this->slug);
    }
}
