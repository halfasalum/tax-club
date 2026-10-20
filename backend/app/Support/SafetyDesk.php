<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\ModerationAction;
use App\Models\ModerationCase;
use App\Models\ModerationReport;
use App\Models\User;
use App\Models\UserRestriction;
use App\Notifications\ModerationAlert;
use App\Notifications\ModerationWarning;
use App\Notifications\ReportReviewed;
use App\Notifications\RestrictionApplied;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Reports, moderation and safeguarding (SAF-REQ01-06, COM-REQ07/08).
 *
 *  - Reports about the same item are grouped into one case.
 *  - Sexual content, grooming, self-harm and violence go straight to
 *    safeguarding officers as urgent; anything involving a member under
 *    18 is at least high priority.
 *  - Content reported by several people is hidden until a moderator
 *    decides.
 *  - Every step is kept in moderation_actions, which cannot be changed.
 */
final class SafetyDesk
{
    public const DEFAULT_AUTO_HIDE = 3;

    /** Reports allowed per member per hour. */
    public const REPORTS_PER_HOUR = 20;

    public const ACCOUNT_ACTIONS = ['warn', 'restrict', 'suspend'];

    // ─── Reporting (SAF-REQ01) ─────────────────────────────────────

    public static function report(User $reporter, string $type, string $id, string $category, ?string $details = null): ModerationCase
    {
        if (! in_array($type, Reportables::TYPES, true) || ! in_array($category, ModerationCase::CATEGORIES, true)) {
            throw new InvalidArgumentException(__('Choose what is wrong.'));
        }

        $subject = Reportables::describe($type, $id);

        if (! $subject['exists']) {
            throw new InvalidArgumentException(__('This item no longer exists.'));
        }

        if ($subject['owner']?->is($reporter)) {
            throw new InvalidArgumentException(__('You cannot report your own content.'));
        }

        $key = 'safety-report:'.$reporter->user_id;

        if (RateLimiter::tooManyAttempts($key, self::REPORTS_PER_HOUR)) {
            throw new InvalidArgumentException(__('You have sent many reports. Please wait a while before sending more.'));
        }

        RateLimiter::hit($key, 3600);

        $case = DB::transaction(function () use ($reporter, $type, $id, $category, $details, $subject) {
            $case = self::openCaseFor($type, $id, $category, 'report', $subject['owner']);

            $alreadyReported = ModerationReport::where('case_id', $case->case_id)->where('reporter_id', $reporter->user_id)->exists();
            ModerationReport::create(['case_id' => $case->case_id, 'reporter_id' => $reporter->user_id, 'category' => $category, 'details' => trim((string) $details) ?: null]);

            if (! $alreadyReported) {
                $case->increment('reports_count');
            }

            self::raise($case, $category, $reporter->isMinor() || (bool) $subject['owner']?->isMinor());

            return $case->refresh();
        });

        $reporters = ModerationReport::where('case_id', $case->case_id)->distinct('reporter_id')->count('reporter_id');

        if ($subject['can_hide'] && ! $subject['hidden'] && $reporters >= self::autoHideThreshold()) {
            Reportables::setVisible($type, $id, false);
            self::log($case, 'auto_hidden', $type, $id, $subject['owner'], __('Hidden after :count reports, until a moderator decides.', ['count' => $reporters]), null);
        }

        return $case;
    }

    /**
     * Content the filter held back, or a concern raised by the system.
     */
    public static function flag(string $type, string $id, string $category, string $note): ModerationCase
    {
        $subject = Reportables::describe($type, $id);
        $case = self::openCaseFor($type, $id, $category, 'filter', $subject['owner']);
        self::raise($case, $category, (bool) $subject['owner']?->isMinor());
        self::log($case, 'flagged', $type, $id, $subject['owner'], $note, null);

        return $case;
    }

    /**
     * A safeguarding concern raised by someone involved, e.g. a mentee or
     * a patron (SAF-REQ05).
     */
    public static function raiseConcern(User $by, string $type, string $id, string $details): ModerationCase
    {
        $case = self::report($by, $type, $id, 'other', $details);
        self::escalate($case, $by, __('Safety concern raised by a participant.'));

        return $case->refresh();
    }

    // ─── Case handling (SAF-REQ02) ─────────────────────────────────

    public static function assign(ModerationCase $case, ?User $officer, User $by): void
    {
        if ($officer && $case->safeguarding && ! $officer->hasControl('handle_safeguarding_concerns')) {
            throw new InvalidArgumentException(__('Safeguarding cases can only be assigned to safeguarding officers.'));
        }

        $case->forceFill(['assigned_to' => $officer?->user_id, 'status' => $case->status === ModerationCase::OPEN ? ModerationCase::INVESTIGATING : $case->status])->save();
        self::log($case, 'assigned', null, null, null, $officer?->full_name ?? __('Nobody'), $by);

        if ($officer && ! $officer->is($by)) {
            Notify::send($officer, new ModerationAlert($case->reference, self::subjectLabel($case), $case->case_id));
        }
    }

    public static function escalate(ModerationCase $case, User $by, string $reason): void
    {
        $case->forceFill(['safeguarding' => true, 'priority' => 'urgent', 'status' => ModerationCase::INVESTIGATING])->save();
        self::log($case, 'escalated', null, null, null, Review::requireReason($reason), $by);
        self::alertOfficers($case, 'handle_safeguarding_concerns');
    }

    public static function note(ModerationCase $case, string $note, User $by): void
    {
        self::log($case, 'note', null, null, null, Review::requireReason($note), $by);
    }

    public static function close(ModerationCase $case, string $outcome, User $by, ?string $note = null): void
    {
        if (! in_array($outcome, ModerationCase::OUTCOMES, true)) {
            throw new InvalidArgumentException(__('Choose the outcome.'));
        }

        if ($outcome === 'no_violation') {
            $subject = Reportables::describe($case->subject_type, $case->subject_id);

            if ($subject['exists'] && $subject['hidden'] && $subject['can_hide'] && self::wasHiddenByModeration($case)) {
                Reportables::setVisible($case->subject_type, $case->subject_id, true);
                self::log($case, 'restored', $case->subject_type, $case->subject_id, $subject['owner'], __('No rule was broken.'), $by);
            }
        }

        $case->forceFill(['status' => ModerationCase::CLOSED, 'outcome' => $outcome, 'outcome_note' => trim((string) $note) ?: null, 'resolved_at' => now()])->save();
        self::log($case, 'closed', null, null, null, trim($outcome.' '.$note), $by);

        User::whereIn('user_id', ModerationReport::where('case_id', $case->case_id)->whereNotNull('reporter_id')->select('reporter_id'))->get()
            ->each(fn (User $reporter) => Notify::send($reporter, new ReportReviewed($case->reference)));
    }

    public static function reopen(ModerationCase $case, User $by, string $reason): void
    {
        $case->forceFill(['status' => ModerationCase::INVESTIGATING, 'resolved_at' => null, 'outcome' => null])->save();
        self::log($case, 'reopened', null, null, null, Review::requireReason($reason), $by);
    }

    // ─── Content actions (SAF-REQ03) ───────────────────────────────

    public static function hideContent(ModerationCase $case, User $by, string $reason, bool $remove = false): void
    {
        $reason = Review::requireReason($reason);

        if (! Reportables::setVisible($case->subject_type, $case->subject_id, false, $remove)) {
            throw new InvalidArgumentException(__('This item cannot be hidden.'));
        }

        self::log($case, $remove ? 'removed' : 'hidden', $case->subject_type, $case->subject_id, $case->subjectUser, $reason, $by);
    }

    public static function restoreContent(ModerationCase $case, User $by, string $reason): void
    {
        if (! Reportables::setVisible($case->subject_type, $case->subject_id, true)) {
            throw new InvalidArgumentException(__('This item cannot be restored.'));
        }

        self::log($case, 'restored', $case->subject_type, $case->subject_id, $case->subjectUser, Review::requireReason($reason), $by);
    }

    // ─── Account actions (SAF-REQ04) ───────────────────────────────

    public static function warn(User $member, string $reason, User $by, ?ModerationCase $case = null): void
    {
        $reason = Review::requireReason($reason);
        self::guardAccountAction($member, $by);
        self::log($case, 'warned', 'user', $member->user_id, $member, $reason, $by, internal: false);
        Notify::send($member, new ModerationWarning($reason));
    }

    public static function restrict(User $member, string $type, ?int $days, string $reason, User $by, ?ModerationCase $case = null): UserRestriction
    {
        $reason = Review::requireReason($reason);
        self::guardAccountAction($member, $by);

        if (! in_array($type, UserRestriction::TYPES, true)) {
            throw new InvalidArgumentException(__('Choose what to restrict.'));
        }

        $restriction = UserRestriction::create([
            'user_id' => $member->user_id,
            'type' => $type,
            'reason' => $reason,
            'case_id' => $case?->case_id,
            'starts_at' => now(),
            'ends_at' => $days ? now()->addDays($days) : null,
            'created_by' => $by->user_id,
        ]);

        self::log($case, 'restricted', 'user', $member->user_id, $member, self::restrictionLabel($type).($days ? ' · '.trans_choice(':count day|:count days', $days, ['count' => $days]) : '').' · '.$reason, $by, internal: false);
        Notify::send($member, new RestrictionApplied(self::restrictionLabel($type), $restriction->ends_at?->translatedFormat('j M Y'), $reason));

        return $restriction;
    }

    public static function liftRestriction(UserRestriction $restriction, User $by, string $reason): void
    {
        $restriction->forceFill(['lifted_at' => now(), 'lifted_by' => $by->user_id])->save();
        self::log(ModerationCase::find($restriction->case_id), 'restriction_lifted', 'user', $restriction->user_id, $restriction->user, Review::requireReason($reason), $by);
    }

    /**
     * Suspend the whole account (uses the member status rules).
     */
    public static function suspend(User $member, string $reason, User $by, ?ModerationCase $case = null): void
    {
        $reason = Review::requireReason($reason);
        self::guardAccountAction($member, $by);
        MemberAccounts::changeStatus($member, User::STATUS_SUSPENDED, $reason, $by);
        self::log($case, 'suspended', 'user', $member->user_id, $member, $reason, $by, internal: false);
    }

    public static function isRestricted(User $user, string $type): bool
    {
        return UserRestriction::active()->where('user_id', $user->user_id)->whereIn('type', [$type, 'all'])->exists();
    }

    public static function activeRestriction(User $user, string $type): ?UserRestriction
    {
        return UserRestriction::active()->where('user_id', $user->user_id)->whereIn('type', [$type, 'all'])->latest('id')->first();
    }

    // ─── Settings and reporting (SAF-REQ06) ────────────────────────

    public static function autoHideThreshold(): int
    {
        return max(1, (int) AppSetting::get('safety.auto_hide_after', self::DEFAULT_AUTO_HIDE));
    }

    /**
     * @param  list<string>  $blockedWords
     */
    public static function configure(array $blockedWords, int $autoHideAfter, User $by): void
    {
        $words = array_values(array_unique(array_filter(array_map(fn ($word) => mb_strtolower(trim($word)), $blockedWords))));
        $before = ['blocked_words' => count(ContentFilter::blockedWords()), 'auto_hide_after' => self::autoHideThreshold()];
        AppSetting::put('safety.blocked_words', $words, $by);
        AppSetting::put('safety.auto_hide_after', max(1, min(20, $autoHideAfter)), $by);
        Audit::record('safety.settings_updated', null, $before, ['blocked_words' => count($words), 'auto_hide_after' => $autoHideAfter], actor: $by);
    }

    /**
     * @return array{totals: array<string, int>, by_category: array<string, int>, by_source: array<string, int>, by_outcome: array<string, int>, by_region: array<string, int>, average_hours: ?float, actions: array<string, int>}
     */
    public static function statistics(Carbon $from, Carbon $to): array
    {
        $cases = ModerationCase::whereBetween('moderation_cases.created_at', [$from, $to]);
        $resolved = (clone $cases)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);

        return [
            'totals' => [
                'cases' => (clone $cases)->count(),
                'open' => (clone $cases)->where('status', '!=', ModerationCase::CLOSED)->count(),
                'safeguarding' => (clone $cases)->where('safeguarding', true)->count(),
                'minors' => (clone $cases)->where('involves_minor', true)->count(),
                'restrictions' => UserRestriction::whereBetween('created_at', [$from, $to])->count(),
                'active_restrictions' => UserRestriction::active()->count(),
            ],
            'by_category' => (clone $cases)->selectRaw('category, count(*) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category')->all(),
            'by_source' => (clone $cases)->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')->all(),
            'by_outcome' => (clone $cases)->whereNotNull('outcome')->selectRaw('outcome, count(*) as total')->groupBy('outcome')->pluck('total', 'outcome')->all(),
            'by_region' => (clone $cases)->join('users', 'users.user_id', '=', 'moderation_cases.subject_user_id')
                ->join('institutions', 'institutions.institution_id', '=', 'users.institution_id')
                ->selectRaw('institutions.region as region, count(*) as total')->groupBy('institutions.region')->orderByDesc('total')->limit(10)->pluck('total', 'region')->all(),
            'average_hours' => $resolved->isEmpty() ? null : round($resolved->avg(fn ($case) => $case->created_at->diffInMinutes($case->resolved_at) / 60), 1),
            'actions' => ModerationAction::whereBetween('created_at', [$from, $to])->selectRaw('action, count(*) as total')->groupBy('action')->orderByDesc('total')->pluck('total', 'action')->all(),
        ];
    }

    // ─── Labels ────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'spam' => __('Spam or advertising'),
            'bullying' => __('Bullying or harassment'),
            'hate' => __('Hate or discrimination'),
            'sexual' => __('Sexual content'),
            'violence' => __('Threats or violence'),
            'self_harm' => __('Self-harm or suicide'),
            'grooming' => __('An adult trying to get close to a young member'),
            'contact_sharing' => __('Asking for or sharing contact details'),
            'personal_info' => __('Sharing private information'),
            'misinformation' => __('Wrong or misleading tax information'),
            'impersonation' => __('Pretending to be someone else'),
            'other' => __('Something else'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function outcomeLabels(): array
    {
        return [
            'actioned' => __('Action taken'),
            'no_violation' => __('No rule broken'),
            'duplicate' => __('Duplicate'),
            'referred' => __('Referred to another authority'),
        ];
    }

    public static function restrictionLabel(string $type): string
    {
        return match ($type) {
            'community' => __('posting in the community'),
            'questions' => __('Ask TRA'),
            'mentoring' => __('mentorship'),
            default => __('all interactive features'),
        };
    }

    public static function subjectLabel(ModerationCase $case): string
    {
        return (self::categoryLabels()[$case->category] ?? $case->category).' · '.Reportables::describe($case->subject_type, $case->subject_id)['label'];
    }

    // ─── Internals ─────────────────────────────────────────────────

    private static function openCaseFor(string $type, string $id, string $category, string $source, ?User $owner): ModerationCase
    {
        $case = ModerationCase::where('subject_type', $type)->where('subject_id', $id)->where('status', '!=', ModerationCase::CLOSED)->first();

        if ($case) {
            return $case;
        }

        return ModerationCase::create([
            'reference' => self::nextReference(),
            'subject_type' => $type,
            'subject_id' => $id,
            'subject_user_id' => $owner?->user_id,
            'category' => $category,
            'source' => $source,
            'status' => ModerationCase::OPEN,
            'priority' => 'normal',
            'involves_minor' => (bool) $owner?->isMinor(),
        ]);
    }

    /**
     * Raise priority and route to safeguarding when needed.
     */
    private static function raise(ModerationCase $case, string $category, bool $minor): void
    {
        $safeguarding = in_array($category, ModerationCase::SAFEGUARDING_CATEGORIES, true) || ($minor && in_array($category, ['contact_sharing', 'personal_info'], true));
        $wasSafeguarding = $case->safeguarding;
        $priorities = ['low' => 0, 'normal' => 1, 'high' => 2, 'urgent' => 3];
        $priority = $safeguarding ? 'urgent' : ($minor ? 'high' : 'normal');

        $case->forceFill([
            'safeguarding' => $case->safeguarding || $safeguarding,
            'involves_minor' => $case->involves_minor || $minor,
            'priority' => $priorities[$priority] > $priorities[$case->priority] ? $priority : $case->priority,
        ])->save();

        if ($case->safeguarding && ! $wasSafeguarding) {
            self::alertOfficers($case, 'handle_safeguarding_concerns');
        } elseif ($case->wasRecentlyCreated) {
            self::alertOfficers($case, 'moderate_community');
        }
    }

    private static function alertOfficers(ModerationCase $case, string $control): void
    {
        User::where('account_status', User::STATUS_ACTIVE)
            ->whereHas('roles.controls', fn ($query) => $query->where('slug', $control))
            ->limit(25)
            ->get()
            ->each(fn (User $officer) => Notify::send($officer, new ModerationAlert($case->reference, self::subjectLabel($case), $case->case_id)));
    }

    private static function wasHiddenByModeration(ModerationCase $case): bool
    {
        return ModerationAction::where('case_id', $case->case_id)->whereIn('action', ['auto_hidden', 'hidden', 'flagged'])->exists();
    }

    private static function guardAccountAction(User $member, User $by): void
    {
        if ($member->is($by)) {
            throw new InvalidArgumentException(__('You cannot take action on your own account.'));
        }

        if (! $by->hasControl('restrict_users')) {
            throw new InvalidArgumentException(__('You do not have permission to act on accounts.'));
        }
    }

    public static function log(?ModerationCase $case, string $action, ?string $targetType, ?string $targetId, ?User $targetUser, ?string $reason, ?User $by, bool $internal = true): void
    {
        ModerationAction::create([
            'case_id' => $case?->case_id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_user_id' => $targetUser?->user_id,
            'reason' => $reason,
            'internal' => $internal,
            'performed_by' => $by?->user_id,
        ]);
    }

    private static function nextReference(): string
    {
        $prefix = 'MC-'.now()->format('Y').'-';
        $last = ModerationCase::where('reference', 'like', $prefix.'%')->orderByDesc('reference')->value('reference');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 5, '0', STR_PAD_LEFT);
    }
}
