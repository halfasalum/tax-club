<?php

namespace App\Notifications;

/**
 * An open quiz the member has not finished closes soon (NOT-REQ04).
 */
class QuizClosingReminder extends TaxClubNotification
{
    public function __construct(public string $quizTitle, public string $closes, public string $quizId)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'quiz_closing';
    }

    public static function label(): string
    {
        return __('Quiz closing soon');
    }

    public static function placeholders(): array
    {
        return ['title', 'when'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public static function preferenceCategory(): ?string
    {
        return 'learning';
    }

    public function message(): string
    {
        return __('TRA Tax Club: The quiz ":title" closes :when. Take it before it closes.');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->quizTitle, 'when' => $this->closes];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.quizzes.show', $this->quizId);
    }
}
