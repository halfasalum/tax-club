<?php

namespace App\Notifications;

/**
 * A question was assigned to a TRA expert (COM-REQ02). Inbox only.
 */
class QuestionAssigned extends TaxClubNotification
{
    public function __construct(public string $reference, public string $subject, public string $questionId)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'question_assigned';
    }

    public static function label(): string
    {
        return __('Question assigned to an expert');
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
        return __('TRA Tax Club: Question :reference ":title" was assigned to you for an answer.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reference' => $this->reference, 'title' => $this->subject];
    }

    public function url(object $notifiable): ?string
    {
        return route('admin.questions.show', $this->questionId);
    }
}
