<?php

namespace App\Notifications;

/**
 * TRA answered the member's question, or explained why not (COM-REQ04).
 */
class QuestionAnswered extends TaxClubNotification
{
    public function __construct(public string $reference, public bool $declined = false)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'question_answered';
    }

    public static function label(): string
    {
        return __('Question answered');
    }

    public static function placeholders(): array
    {
        return ['reference'];
    }

    public function message(): string
    {
        return __('TRA Tax Club: TRA has replied to your question :reference. Open Ask TRA to read it.');
    }

    public function replacements(object $notifiable): array
    {
        return ['reference' => $this->reference];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.ask.show', $this->reference);
    }
}
