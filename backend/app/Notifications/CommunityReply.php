<?php

namespace App\Notifications;

/**
 * Someone commented on the member's post (COM-REQ06). Inbox only.
 */
class CommunityReply extends TaxClubNotification
{
    public function __construct(public string $postTitle, public string $postId)
    {
        parent::__construct();
    }

    public static function key(): string
    {
        return 'community_reply';
    }

    public static function label(): string
    {
        return __('New comment on your post');
    }

    public static function placeholders(): array
    {
        return ['title'];
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
        return __('TRA Tax Club: Someone commented on your post ":title".');
    }

    public function replacements(object $notifiable): array
    {
        return ['title' => $this->postTitle];
    }

    public function url(object $notifiable): ?string
    {
        return route('member.community.post', $this->postId);
    }
}
