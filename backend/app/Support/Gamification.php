<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Badge;
use App\Models\Content;
use App\Models\LearningModule;
use App\Models\PointsEntry;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\BadgeEarned;
use App\Notifications\PointsAdjusted;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Points and badges (GAM-REQ08, GAM-REQ09).
 *
 * Points are kept in an append-only ledger; users.total_points is the
 * running total. Automatic points follow configurable rules and a daily
 * cap (decision D-15); officers can add or remove points with a reason.
 */
final class Gamification
{
    /** Default rule values (decision D-15). */
    public const DEFAULT_RULES = [
        'quiz_passed' => 10,
        'quiz_correct_answer' => 1,
        'lesson_completed' => 2,
        'module_completed' => 10,
    ];

    public const DEFAULT_DAILY_CAP = 50;

    public static function rule(string $key): int
    {
        $rules = once(fn () => DB::table('gamification_rules')->get()->keyBy('key'));
        $rule = $rules->get($key);

        if ($rule && ! $rule->is_active) {
            return 0;
        }

        return (int) ($rule->points ?? self::DEFAULT_RULES[$key] ?? 0);
    }

    public static function dailyCap(): int
    {
        return (int) AppSetting::get('gamification.daily_cap', self::DEFAULT_DAILY_CAP);
    }

    public static function lessonCompleted(User $member, Content $lesson): void
    {
        self::awardOnce($member, 'lesson_completed', self::rule('lesson_completed'), 'content', $lesson->content_id, $lesson->title);
    }

    public static function moduleCompleted(User $member, LearningModule $module): void
    {
        self::awardOnce($member, 'module_completed', self::rule('module_completed'), 'module', (string) $module->id, $module->title);
    }

    /**
     * Quiz points: pass bonus plus points per correct answer, for the best
     * attempt only. A better attempt earns the difference.
     */
    public static function quizSubmitted(User $member, QuizAttempt $attempt): void
    {
        $quiz = $attempt->quiz;

        if ($quiz->isPractice()) {
            return;
        }

        $correct = $attempt->responses()->where('is_correct', true)->count();
        $value = ($attempt->passed ? self::rule('quiz_passed') : 0) + $correct * self::rule('quiz_correct_answer');
        $groupKey = 'quiz:'.$quiz->quiz_id;
        $already = (int) PointsEntry::where('user_id', $member->user_id)->where('group_key', $groupKey)->sum('points');

        if ($value > $already) {
            self::award($member, 'quiz', $value - $already, 'quiz_attempt', $attempt->attempt_id, $quiz->title, $groupKey);
        }
    }

    /**
     * Officer correction, positive or negative, always with a reason.
     */
    public static function adjust(User $member, int $points, string $reason, User $officer): PointsEntry
    {
        if ($points === 0) {
            throw new InvalidArgumentException(__('Enter a number of points other than zero.'));
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('Give a reason.'));
        }

        if ($member->is($officer)) {
            throw new InvalidArgumentException(__('You cannot change your own points.'));
        }

        $points = max($points, -1 * (int) $member->total_points);

        $entry = DB::transaction(function () use ($member, $points, $reason, $officer) {
            $entry = PointsEntry::create([
                'user_id' => $member->user_id,
                'points' => $points,
                'reason' => 'adjustment',
                'description' => trim($reason),
                'awarded_by' => $officer->user_id,
            ]);
            User::whereKey($member->user_id)->increment('total_points', $points);

            return $entry;
        });

        Audit::record('points.adjusted', $member, after: ['points' => $points], reason: trim($reason), actor: $officer);
        Notify::send($member, new PointsAdjusted($points, trim($reason)));
        self::evaluateBadges($member->fresh());

        return $entry;
    }

    /**
     * Points earned in a period, e.g. this month.
     */
    public static function pointsSince(User $member, \DateTimeInterface $since): int
    {
        return (int) PointsEntry::where('user_id', $member->user_id)->where('created_at', '>=', $since)->sum('points');
    }

    /**
     * Award badges the member has newly reached.
     *
     * @return list<Badge>
     */
    public static function evaluateBadges(User $member): array
    {
        $badges = Badge::where('is_active', true)
            ->whereNotIn('id', DB::table('user_badges')->where('user_id', $member->user_id)->select('badge_id'))
            ->orderBy('threshold')
            ->get();

        if ($badges->isEmpty()) {
            return [];
        }

        $counts = self::achievementCounts($member);
        $earned = [];

        foreach ($badges as $badge) {
            if (($counts[$badge->criteria] ?? 0) < $badge->threshold) {
                continue;
            }

            if (DB::table('user_badges')->insertOrIgnore(['user_id' => $member->user_id, 'badge_id' => $badge->id, 'awarded_at' => now()])) {
                $earned[] = $badge;
                Notify::send($member, new BadgeEarned($badge->name_sw, $badge->name_en, $badge->icon));
            }
        }

        return $earned;
    }

    /**
     * @return array<string, int>
     */
    public static function achievementCounts(User $member): array
    {
        $scored = fn ($query) => $query->select('quiz_id')->from('quizzes')->where('mode', 'scored');

        return [
            'lessons_completed' => DB::table('user_progress')->where('user_id', $member->user_id)->where('status', 'completed')->count(),
            'modules_completed' => DB::table('module_completions')->where('user_id', $member->user_id)->count(),
            'quizzes_passed' => QuizAttempt::where('user_id', $member->user_id)->where('passed', true)->whereIn('quiz_id', $scored)->distinct('quiz_id')->count('quiz_id'),
            'perfect_scores' => QuizAttempt::where('user_id', $member->user_id)->where('percent', '>=', 100)->whereIn('quiz_id', $scored)->distinct('quiz_id')->count('quiz_id'),
            'points_total' => (int) User::whereKey($member->user_id)->value('total_points'),
        ];
    }

    private static function awardOnce(User $member, string $reason, int $points, string $sourceType, string $sourceId, string $description): void
    {
        $exists = PointsEntry::where('user_id', $member->user_id)->where('reason', $reason)->where('source_type', $sourceType)->where('source_id', $sourceId)->exists();

        if (! $exists) {
            self::award($member, $reason, $points, $sourceType, $sourceId, $description);
        }

        self::evaluateBadges($member);
    }

    /**
     * Add automatic points, respecting the daily cap.
     */
    private static function award(User $member, string $reason, int $points, string $sourceType, string $sourceId, string $description, ?string $groupKey = null): void
    {
        if ($points <= 0 || ! $member->isActive() || $member->user_type === 'staff') {
            return;
        }

        DB::transaction(function () use ($member, $reason, $points, $sourceType, $sourceId, $description, $groupKey) {
            User::whereKey($member->user_id)->lockForUpdate()->first();

            $today = (int) PointsEntry::where('user_id', $member->user_id)->whereNull('awarded_by')->where('points', '>', 0)
                ->where('created_at', '>=', now()->startOfDay())->sum('points');
            $allowed = min($points, max(0, self::dailyCap() - $today));

            if ($allowed <= 0) {
                return;
            }

            PointsEntry::create([
                'user_id' => $member->user_id,
                'points' => $allowed,
                'reason' => $reason,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'group_key' => $groupKey,
                'description' => $allowed < $points ? $description.' '.__('(daily limit reached)') : $description,
            ]);

            User::whereKey($member->user_id)->increment('total_points', $allowed);
            User::whereKey($member->user_id)->increment('points_earned_this_month', $allowed);
        });

        self::evaluateBadges($member);
    }
}
