<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Screens community posts, questions and stories before they appear
 * (SAF-REQ03, SAF-REQ05). Text with blocked words, or with phone numbers
 * or email addresses (members must not share contact details, to protect
 * young members), is held for a moderator instead of being published.
 */
final class ContentFilter
{
    /** Starter list; administrators add their own words in Safety settings. */
    public const DEFAULT_BLOCKED = ['matusi', 'malaya', 'shenzi', 'mjinga', 'fuck', 'shit', 'bitch', 'idiot', 'stupid'];

    private const PHONE = '/(?:\+?255|\b0)[\s-]?[67]\d{2}[\s-]?\d{3}[\s-]?\d{3}\b/';

    private const EMAIL = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';

    private const CONTACT_APPS = '/\b(whats\s?app|telegram|snapchat|instagram|inbox me|dm me|nitumie namba|namba yako)\b/i';

    /**
     * Why the text should be held, or null when it can be published.
     */
    public static function problem(string $text): ?string
    {
        if (preg_match(self::PHONE, $text) || preg_match(self::EMAIL, $text) || preg_match(self::CONTACT_APPS, $text)) {
            return 'contact_sharing';
        }

        $lower = mb_strtolower($text);

        foreach (self::blockedWords() as $word) {
            if ($word !== '' && preg_match('/\b'.preg_quote($word, '/').'\b/u', $lower)) {
                return 'blocked_word';
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function blockedWords(): array
    {
        return array_values(array_filter(array_map(fn ($word) => mb_strtolower(trim((string) $word)), (array) AppSetting::get('safety.blocked_words', self::DEFAULT_BLOCKED))));
    }

    public static function label(?string $problem): string
    {
        return match ($problem) {
            'contact_sharing' => __('Contact details (phone, email or chat app)'),
            'blocked_word' => __('Words that are not allowed'),
            default => __('Needs a check'),
        };
    }
}
