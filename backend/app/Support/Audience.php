<?php

namespace App\Support;

use App\Models\Content;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who an event, opportunity or broadcast is for (EVT-REQ01/03, OPP-REQ05,
 * NOT-REQ03). Empty rules mean every member. Staff always see everything.
 *
 * Rules: categories (member types), regions (institution region names),
 * institutions (ids) and age (any, adults, minors).
 */
final class Audience
{
    public const ANY_AGE = 'any';

    public const ADULTS = 'adults';

    public const MINORS = 'minors';

    /**
     * @param  array<string, mixed>|null  $rules
     * @return array{categories: list<string>, regions: list<string>, institutions: list<string>, age: string}
     */
    public static function normalize(?array $rules): array
    {
        $rules ??= [];
        $regions = array_values(config('tanzania.regions'));

        return [
            'categories' => array_values(array_intersect((array) ($rules['categories'] ?? []), Content::AUDIENCES)),
            'regions' => array_values(array_intersect((array) ($rules['regions'] ?? []), $regions)),
            'institutions' => array_values(array_unique(array_map('strval', array_filter((array) ($rules['institutions'] ?? []))))),
            'age' => in_array($rules['age'] ?? null, [self::ADULTS, self::MINORS], true) ? $rules['age'] : self::ANY_AGE,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $rules
     */
    public static function isEveryone(?array $rules): bool
    {
        $rules = self::normalize($rules);

        return ! $rules['categories'] && ! $rules['regions'] && ! $rules['institutions'] && $rules['age'] === self::ANY_AGE;
    }

    /**
     * @param  array<string, mixed>|null  $rules
     */
    public static function includes(?array $rules, User $user): bool
    {
        if ($user->user_type === 'staff') {
            return true;
        }

        $rules = self::normalize($rules);

        if ($rules['categories'] && ! in_array($user->user_type, $rules['categories'], true)) {
            return false;
        }

        if ($rules['institutions'] && ! in_array((string) $user->institution_id, $rules['institutions'], true)) {
            return false;
        }

        if ($rules['regions'] && ! in_array($user->institution?->region, $rules['regions'], true)) {
            return false;
        }

        return match ($rules['age']) {
            self::ADULTS => ! $user->isMinor(),
            self::MINORS => $user->isMinor(),
            default => true,
        };
    }

    /**
     * Limit a users query to the active members the rules describe.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>|null  $rules
     * @return Builder<User>
     */
    public static function members(Builder $query, ?array $rules): Builder
    {
        $rules = self::normalize($rules);

        return $query->where('account_status', User::STATUS_ACTIVE)
            ->where('user_type', '!=', 'staff')
            ->when($rules['categories'], fn (Builder $query, array $categories) => $query->whereIn('user_type', $categories))
            ->when($rules['institutions'], fn (Builder $query, array $ids) => $query->whereIn('institution_id', $ids))
            ->when($rules['regions'], fn (Builder $query, array $regions) => $query->whereIn('institution_id', Institution::whereIn('region', $regions)->select('institution_id')))
            ->when($rules['age'] === self::ADULTS, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('age_band')->orWhere('age_band', User::AGE_ADULT)))
            ->when($rules['age'] === self::MINORS, fn (Builder $query) => $query->where('age_band', User::AGE_MINOR));
    }

    /**
     * A short description for lists, e.g. "University, College · Mwanza".
     *
     * @param  array<string, mixed>|null  $rules
     */
    public static function describe(?array $rules): string
    {
        $rules = self::normalize($rules);

        if (self::isEveryone($rules)) {
            return __('All members');
        }

        $labels = self::categoryLabels();
        $parts = [];

        if ($rules['categories']) {
            $parts[] = implode(', ', array_map(fn ($category) => $labels[$category] ?? $category, $rules['categories']));
        }

        if ($rules['regions']) {
            $parts[] = implode(', ', $rules['regions']);
        }

        if ($rules['institutions']) {
            $parts[] = trans_choice(':count institution|:count institutions', count($rules['institutions']), ['count' => count($rules['institutions'])]);
        }

        if ($rules['age'] !== self::ANY_AGE) {
            $parts[] = $rules['age'] === self::ADULTS ? __('18 and over') : __('Under 18');
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'primary' => __('Primary school'),
            'secondary' => __('Secondary school'),
            'college' => __('College'),
            'university' => __('University'),
            'alumni' => __('Alumni'),
        ];
    }
}
