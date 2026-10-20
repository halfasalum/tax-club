<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Member choices for optional notifications (NOT-REQ05). Messages about
 * the account, membership and security are not optional.
 */
final class NotificationPreferences
{
    /**
     * Optional categories and their defaults.
     *
     * @return array<string, array{label: string, description: string, sms: bool, inbox: bool}>
     */
    public static function categories(): array
    {
        return [
            'achievements' => [
                'label' => __('Points and badges'),
                'description' => __('When you earn a badge or your points change.'),
                'sms' => false,
                'inbox' => true,
            ],
            'events' => [
                'label' => __('Events'),
                'description' => __('Registration confirmations, reminders, updates and materials for events you joined.'),
                'sms' => true,
                'inbox' => true,
            ],
            'opportunities' => [
                'label' => __('Opportunities'),
                'description' => __('Reminders before saved opportunities close.'),
                'sms' => false,
                'inbox' => true,
            ],
            'learning' => [
                'label' => __('Quiz reminders'),
                'description' => __('When an open quiz you have not finished is about to close.'),
                'sms' => false,
                'inbox' => true,
            ],
            'community' => [
                'label' => __('Community'),
                'description' => __('Comments on your posts and alumni connection requests.'),
                'sms' => false,
                'inbox' => true,
            ],
            'competitions' => [
                'label' => __('Competitions'),
                'description' => __('Registration, reminders and results of competitions you entered.'),
                'sms' => true,
                'inbox' => true,
            ],
            'announcements' => [
                'label' => __('Announcements'),
                'description' => __('News and announcements from TRA Tax Club.'),
                'sms' => true,
                'inbox' => true,
            ],
        ];
    }

    /**
     * @return array{sms: bool, inbox: bool}
     */
    public static function for(User $user, string $category): array
    {
        $defaults = self::categories()[$category] ?? ['sms' => true, 'inbox' => true];
        $saved = DB::table('notification_preferences')->where('user_id', $user->user_id)->where('category', $category)->first();

        return [
            'sms' => $saved ? (bool) $saved->sms : $defaults['sms'],
            'inbox' => $saved ? (bool) $saved->inbox : $defaults['inbox'],
        ];
    }

    /**
     * @return array<string, array{sms: bool, inbox: bool}>
     */
    public static function all(User $user): array
    {
        return collect(self::categories())->keys()->mapWithKeys(fn ($category) => [$category => self::for($user, $category)])->all();
    }

    public static function set(User $user, string $category, bool $sms, bool $inbox): void
    {
        if (! array_key_exists($category, self::categories())) {
            return;
        }

        DB::table('notification_preferences')->updateOrInsert(
            ['user_id' => $user->user_id, 'category' => $category],
            ['sms' => $sms, 'inbox' => $inbox, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
