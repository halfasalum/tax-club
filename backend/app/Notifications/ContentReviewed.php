<?php

namespace App\Notifications;

/**
 * Tells an author what the reviewer decided about their content or quiz
 * (LRN-REQ02, GAM-REQ03). Inbox only unless an administrator turns SMS on.
 */
class ContentReviewed extends TaxClubNotification
{
    public function __construct(public string $title, public string $decision, public ?string $note, public string $link)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'content_reviewed';
    }

    public static function label(): string
    {
        return __('Review decision for an author');
    }

    public static function placeholders(): array
    {
        return ['title', 'decision', 'note'];
    }

    public static function smsByDefault(): bool
    {
        return false;
    }

    public function message(): string
    {
        return __('TRA Tax Club: ":title" was :decision. :note');
    }

    public function replacements(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'decision' => match ($this->decision) {
                'approved' => __('approved'),
                'returned' => __('returned for changes'),
                default => __('rejected'),
            },
            'note' => $this->note,
        ];
    }

    public function url(object $notifiable): ?string
    {
        return $this->link;
    }
}
