<?php

namespace App\Support;

use App\Models\ClubPatron;
use App\Models\MentorProfile;
use App\Models\Mentorship;
use App\Models\MentorshipRequest;
use App\Models\MentorshipSession;
use App\Models\User;
use App\Notifications\MentorApplicationDecided;
use App\Notifications\MentorshipMatched;
use App\Notifications\MentorshipNeedsAttention;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mentorship (MNT-REQ01-06) with the safeguarding rules of decision D-11:
 *
 *  - Mentors are alumni or TRA staff, sign a declaration and are approved
 *    by a TRA officer. Mentoring members under 18 also needs a cleared
 *    background check.
 *  - A member under 18 needs guardian consent, confirmed by their patron
 *    or a coordinator, before being matched. Their patron is copied on
 *    the mentorship as supervisor.
 *  - There is no private chat. Participants record each session (topic and
 *    short summary), which the coordinator and supervisor can see.
 *  - Mentorships with no session for 30 days are flagged to coordinators.
 */
final class MentorshipDesk
{
    public const INACTIVE_DAYS = 30;

    // ─── Mentors (MNT-REQ01) ───────────────────────────────────────

    public static function canApplyAsMentor(User $user): bool
    {
        return $user->isActive() && ! $user->isMinor() && in_array($user->user_type, ['alumni', 'staff'], true);
    }

    /**
     * @param  array{expertise: list<string>, bio: string, availability?: ?string, max_mentees?: int, mentors_minors?: bool, declaration: bool}  $values
     */
    public static function apply(User $user, array $values): MentorProfile
    {
        if (! self::canApplyAsMentor($user)) {
            throw new InvalidArgumentException(__('Mentors are Tax Club alumni or TRA staff aged 18 and over.'));
        }

        if (! ($values['declaration'] ?? false)) {
            throw new InvalidArgumentException(__('Accept the mentor code of conduct to apply.'));
        }

        $expertise = array_values(array_intersect($values['expertise'] ?? [], MentorProfile::AREAS));

        if ($expertise === []) {
            throw new InvalidArgumentException(__('Choose at least one area you can help with.'));
        }

        $profile = MentorProfile::firstOrNew(['user_id' => $user->user_id]);

        if ($profile->exists && $profile->status === MentorProfile::SUSPENDED) {
            throw new InvalidArgumentException(__('Your mentor role is suspended. Please contact your coordinator.'));
        }

        $minors = (bool) ($values['mentors_minors'] ?? false);
        $profile->fill([
            'expertise' => $expertise,
            'bio' => trim(strip_tags($values['bio'])),
            'availability' => trim((string) ($values['availability'] ?? '')) ?: null,
            'max_mentees' => max(1, min(5, (int) ($values['max_mentees'] ?? 2))),
            'mentors_minors' => $minors,
        ]);
        $profile->forceFill([
            'status' => $profile->status === MentorProfile::APPROVED ? MentorProfile::APPROVED : MentorProfile::SUBMITTED,
            'declaration_at' => now(),
            'background_check' => $minors ? ($profile->background_check === 'cleared' ? 'cleared' : 'pending') : 'not_required',
        ])->save();

        Audit::record('mentor.applied', $profile, after: ['expertise' => $expertise, 'mentors_minors' => $minors], actor: $user);

        return $profile;
    }

    public static function decideApplication(MentorProfile $profile, User $reviewer, bool $approve, ?string $note = null): void
    {
        if ($profile->user_id === $reviewer->user_id) {
            throw new InvalidArgumentException(__('You cannot review your own application.'));
        }

        if (! $approve) {
            Review::requireReason($note);
        }

        $profile->forceFill(['status' => $approve ? MentorProfile::APPROVED : MentorProfile::REJECTED, 'reviewed_by' => $reviewer->user_id, 'reviewed_at' => now(), 'review_note' => trim((string) $note) ?: null])->save();
        Audit::record($approve ? 'mentor.approved' : 'mentor.rejected', $profile, reason: $note, actor: $reviewer);
        Notify::send($profile->user, new MentorApplicationDecided($approve, $note));
    }

    public static function recordBackgroundCheck(MentorProfile $profile, bool $cleared, User $by, string $reference): void
    {
        $profile->forceFill(['background_check' => $cleared ? 'cleared' : 'failed', 'background_checked_by' => $by->user_id, 'background_checked_at' => now()])->save();
        Audit::record('mentor.background_check', $profile, after: ['result' => $cleared ? 'cleared' : 'failed'], reason: Review::requireReason($reference), actor: $by);

        if (! $cleared) {
            self::endMentorshipsWithMinors($profile, $by, __('Background check not cleared.'));
        }
    }

    public static function suspendMentor(MentorProfile $profile, User $by, string $reason): void
    {
        $reason = Review::requireReason($reason);
        $profile->forceFill(['status' => MentorProfile::SUSPENDED, 'review_note' => $reason])->save();
        Audit::record('mentor.suspended', $profile, reason: $reason, actor: $by);

        Mentorship::where('mentor_id', $profile->user_id)->where('status', Mentorship::ACTIVE)->each(
            fn (Mentorship $mentorship) => self::close($mentorship, $by, 'ended_early', __('Mentor role suspended: :reason', ['reason' => $reason]))
        );
    }

    // ─── Requests (MNT-REQ02) ──────────────────────────────────────

    /**
     * @param  array{interests: list<string>, goals: string, mode?: string, guardian_consent?: bool}  $values
     */
    public static function request(User $member, array $values): MentorshipRequest
    {
        if (! $member->isActive() || $member->user_type === 'staff') {
            throw new InvalidArgumentException(__('Only active members can ask for a mentor.'));
        }

        if (SafetyDesk::isRestricted($member, 'mentoring')) {
            throw new InvalidArgumentException(__('Your account is restricted, so you cannot ask for a mentor right now.'));
        }

        if (MentorshipRequest::where('mentee_id', $member->user_id)->where('status', MentorshipRequest::OPEN)->exists()
            || Mentorship::where('mentee_id', $member->user_id)->where('status', Mentorship::ACTIVE)->exists()) {
            throw new InvalidArgumentException(__('You already have a mentor or a request waiting.'));
        }

        $interests = array_values(array_intersect($values['interests'] ?? [], MentorProfile::AREAS));

        if ($interests === []) {
            throw new InvalidArgumentException(__('Choose at least one area.'));
        }

        if ($member->isMinor() && ! ($values['guardian_consent'] ?? false)) {
            throw new InvalidArgumentException(__('Members under 18 need a parent or guardian to agree first.'));
        }

        return MentorshipRequest::create([
            'mentee_id' => $member->user_id,
            'interests' => $interests,
            'goals' => trim(strip_tags($values['goals'])),
            'mode' => in_array($values['mode'] ?? null, ['online', 'in_person', 'either'], true) ? $values['mode'] : 'either',
            'guardian_consent' => (bool) ($values['guardian_consent'] ?? false),
        ]);
    }

    public static function cancelRequest(MentorshipRequest $request, User $member): void
    {
        if ($request->mentee_id !== $member->user_id || $request->status !== MentorshipRequest::OPEN) {
            throw new InvalidArgumentException(__('This request cannot be cancelled.'));
        }

        $request->forceFill(['status' => MentorshipRequest::CANCELLED])->save();
    }

    /**
     * The patron or a coordinator confirms that the guardian agreed.
     */
    public static function confirmConsent(MentorshipRequest $request, User $by): void
    {
        if (! self::canSupervise($by, $request->mentee)) {
            throw new InvalidArgumentException(__('Only the member\'s patron or a coordinator can confirm consent.'));
        }

        $request->forceFill(['guardian_consent' => true, 'consent_confirmed_by' => $by->user_id, 'consent_confirmed_at' => now()])->save();
        Audit::record('mentorship.consent_confirmed', $request->mentee, actor: $by);
    }

    public static function declineRequest(MentorshipRequest $request, User $by, string $reason): void
    {
        $request->forceFill(['status' => MentorshipRequest::DECLINED, 'status_reason' => Review::requireReason($reason)])->save();
    }

    // ─── Matching (MNT-REQ03, MNT-REQ06) ───────────────────────────

    /**
     * Approved mentors with room, best match first.
     *
     * @return Collection<int, array{profile: MentorProfile, overlap: int, minors_ok: bool}>
     */
    public static function suggestions(MentorshipRequest $request): Collection
    {
        $minor = $request->mentee->isMinor();

        return MentorProfile::approved()->with('user:user_id,full_name,institution_id,account_status', 'user.institution:institution_id,name,region')->get()
            ->filter(fn (MentorProfile $profile) => $profile->user_id !== $request->mentee_id && $profile->user?->isActive() && $profile->hasCapacity())
            ->map(fn (MentorProfile $profile) => [
                'profile' => $profile,
                'overlap' => count(array_intersect($profile->expertise, $request->interests)),
                'minors_ok' => ! $minor || $profile->canMentorMinors(),
            ])
            ->sortByDesc(fn (array $row) => [$row['minors_ok'], $row['overlap']])
            ->values();
    }

    public static function match(MentorshipRequest $request, MentorProfile $mentor, User $coordinator): Mentorship
    {
        $mentee = $request->mentee;

        match (true) {
            $request->status !== MentorshipRequest::OPEN => throw new InvalidArgumentException(__('This request is no longer open.')),
            $mentor->status !== MentorProfile::APPROVED => throw new InvalidArgumentException(__('This mentor is not approved.')),
            ! $mentor->hasCapacity() => throw new InvalidArgumentException(__('This mentor has no room for another mentee.')),
            $mentor->user_id === $mentee->user_id => throw new InvalidArgumentException(__('A member cannot mentor themselves.')),
            SafetyDesk::isRestricted($mentor->user, 'mentoring') => throw new InvalidArgumentException(__('This mentor is restricted.')),
            $mentee->isMinor() && ! $mentor->canMentorMinors() => throw new InvalidArgumentException(__('This mentor is not cleared to mentor members under 18.')),
            $mentee->isMinor() && ! $request->consent_confirmed_at => throw new InvalidArgumentException(__('The patron or a coordinator must confirm guardian consent first.')),
            default => null,
        };

        $mentorship = DB::transaction(function () use ($request, $mentor, $mentee, $coordinator) {
            $mentorship = Mentorship::create([
                'mentor_id' => $mentor->user_id,
                'mentee_id' => $mentee->user_id,
                'request_id' => $request->id,
                'matched_by' => $coordinator->user_id,
                'supervisor_id' => $mentee->isMinor() ? self::patronOf($mentee)?->user_id : null,
                'status' => Mentorship::ACTIVE,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
            $request->forceFill(['status' => MentorshipRequest::MATCHED])->save();

            return $mentorship;
        });

        Audit::record('mentorship.matched', $mentorship, after: ['mentor' => $mentor->user?->membership_id ?? $mentor->user_id, 'mentee' => $mentee->membership_id], actor: $coordinator);
        Notify::send($mentee, new MentorshipMatched($mentor->user->full_name, asMentor: false));
        Notify::send($mentor->user, new MentorshipMatched($mentee->publicName(), asMentor: true));

        if ($mentorship->supervisor) {
            Notify::send($mentorship->supervisor, new MentorshipNeedsAttention(self::pair($mentorship), __('a new mentorship for a member of your club; you can follow its sessions.'), $mentorship->id));
        }

        return $mentorship;
    }

    // ─── Activity (MNT-REQ04, MNT-REQ05) ───────────────────────────

    /**
     * @param  array{held_on: string, mode: string, duration_minutes?: ?int, topic: string, summary?: ?string, milestone?: bool}  $values
     */
    public static function logSession(Mentorship $mentorship, User $by, array $values): MentorshipSession
    {
        if (! $mentorship->involves($by)) {
            throw new InvalidArgumentException(__('Only the mentor and mentee record sessions.'));
        }

        if ($mentorship->status !== Mentorship::ACTIVE) {
            throw new InvalidArgumentException(__('This mentorship has ended.'));
        }

        $heldOn = Carbon::parse($values['held_on']);

        if ($heldOn->isFuture() || $heldOn->lt($mentorship->started_at->copy()->startOfDay())) {
            throw new InvalidArgumentException(__('Choose a date between the start of the mentorship and today.'));
        }

        $session = MentorshipSession::create([
            'mentorship_id' => $mentorship->id,
            'held_on' => $heldOn->toDateString(),
            'mode' => in_array($values['mode'], MentorshipSession::MODES, true) ? $values['mode'] : 'video',
            'duration_minutes' => $values['duration_minutes'] ?? null,
            'topic' => trim($values['topic']),
            'summary' => trim((string) ($values['summary'] ?? '')) ?: null,
            'milestone' => (bool) ($values['milestone'] ?? false),
            'logged_by' => $by->user_id,
        ]);

        $mentorship->forceFill(['last_activity_at' => now()])->save();

        // A session note asking for contact details is a safeguarding signal.
        if (ContentFilter::problem($session->topic.' '.$session->summary) === 'contact_sharing' && $mentorship->mentee->isMinor()) {
            SafetyDesk::flag('mentorship', (string) $mentorship->id, 'contact_sharing', __('Session note mentions contact details in a mentorship with a member under 18.'));
        }

        return $session;
    }

    public static function close(Mentorship $mentorship, User $by, string $outcome, ?string $note = null, ?int $rating = null): void
    {
        if (! $mentorship->involves($by) && ! $by->hasControl('manage_mentorships')) {
            throw new InvalidArgumentException(__('You cannot close this mentorship.'));
        }

        if ($mentorship->status !== Mentorship::ACTIVE) {
            throw new InvalidArgumentException(__('This mentorship has already ended.'));
        }

        if (! in_array($outcome, Mentorship::OUTCOMES, true)) {
            throw new InvalidArgumentException(__('Choose how it went.'));
        }

        $mentorship->forceFill([
            'status' => in_array($outcome, ['goals_met', 'partly_met'], true) ? Mentorship::COMPLETED : Mentorship::CLOSED,
            'ended_at' => now(),
            'outcome' => $outcome,
            'closing_note' => trim((string) $note) ?: null,
            'mentee_rating' => $by->user_id === $mentorship->mentee_id && $rating ? max(1, min(5, $rating)) : $mentorship->mentee_rating,
            'closed_by' => $by->user_id,
        ])->save();

        Audit::record('mentorship.closed', $mentorship, after: ['outcome' => $outcome], reason: $note, actor: $by);
    }

    /**
     * Either participant or the supervisor raises a safety concern.
     */
    public static function raiseConcern(Mentorship $mentorship, User $by, string $details): void
    {
        if (! $mentorship->involves($by) && $mentorship->supervisor_id !== $by->user_id) {
            throw new InvalidArgumentException(__('You are not part of this mentorship.'));
        }

        SafetyDesk::raiseConcern($by, 'mentorship', (string) $mentorship->id, Review::requireReason($details));

        if ($mentorship->supervisor && ! $mentorship->supervisor->is($by)) {
            Notify::send($mentorship->supervisor, new MentorshipNeedsAttention(self::pair($mentorship), __('a safety concern was raised.'), $mentorship->id));
        }
    }

    /**
     * Who may see the mentorship and its session log.
     */
    public static function canView(User $user, Mentorship $mentorship): bool
    {
        return $mentorship->involves($user) || $mentorship->supervisor_id === $user->user_id || $user->hasControl('manage_mentorships');
    }

    /**
     * Tell coordinators about mentorships with no recent session.
     * Run daily by the scheduler.
     */
    public static function flagInactive(): int
    {
        $coordinators = User::where('account_status', User::STATUS_ACTIVE)->whereHas('roles.controls', fn ($query) => $query->where('slug', 'manage_mentorships'))->limit(25)->get();
        $flagged = 0;

        Mentorship::where('status', Mentorship::ACTIVE)
            ->where('last_activity_at', '<=', now()->subDays(self::INACTIVE_DAYS))
            ->with(['mentor', 'mentee', 'supervisor'])
            ->each(function (Mentorship $mentorship) use ($coordinators, &$flagged) {
                $key = $mentorship->id.':'.now()->format('Y-m');

                if (! DB::table('reminders_sent')->insertOrIgnore(['kind' => 'mentorship_inactive', 'subject_id' => $key, 'user_id' => $mentorship->mentor_id, 'created_at' => now()])) {
                    return;
                }

                $recipients = $coordinators->when($mentorship->supervisor, fn ($people) => $people->push($mentorship->supervisor))->unique('user_id');
                $recipients->each(fn (User $person) => Notify::send($person, new MentorshipNeedsAttention(self::pair($mentorship), __('no session recorded for :days days.', ['days' => self::INACTIVE_DAYS]), $mentorship->id)));
                $flagged++;
            });

        return $flagged;
    }

    /**
     * @return array<string, string>
     */
    public static function areaLabels(): array
    {
        return [
            'tax_basics' => __('Understanding taxes'),
            'careers_in_tax' => __('Careers in tax and TRA'),
            'accounting' => __('Accounting and finance'),
            'business_and_entrepreneurship' => __('Business and entrepreneurship'),
            'customs_and_trade' => __('Customs and trade'),
            'law' => __('Law'),
            'ict' => __('ICT'),
            'study_skills' => __('Study skills and exams'),
            'leadership' => __('Leadership and public speaking'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function outcomeLabels(): array
    {
        return [
            'goals_met' => __('Goals met'),
            'partly_met' => __('Goals partly met'),
            'not_met' => __('Goals not met'),
            'ended_early' => __('Ended early'),
        ];
    }

    public static function pair(Mentorship $mentorship): string
    {
        return ($mentorship->mentor?->publicName() ?? '?').' & '.($mentorship->mentee?->publicName() ?? '?');
    }

    private static function patronOf(User $member): ?User
    {
        return ClubPatron::current()
            ->whereHas('taxClub', fn ($query) => $query->where('institution_id', $member->institution_id))
            ->with('user')
            ->first()?->user;
    }

    private static function canSupervise(User $by, User $mentee): bool
    {
        return $by->hasControl('manage_mentorships') || self::patronOf($mentee)?->is($by);
    }

    private static function endMentorshipsWithMinors(MentorProfile $profile, User $by, string $reason): void
    {
        Mentorship::where('mentor_id', $profile->user_id)->where('status', Mentorship::ACTIVE)->with('mentee')->get()
            ->filter(fn (Mentorship $mentorship) => $mentorship->mentee?->isMinor())
            ->each(fn (Mentorship $mentorship) => self::close($mentorship, $by, 'ended_early', $reason));
    }
}
