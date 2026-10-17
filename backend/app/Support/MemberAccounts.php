<?php

namespace App\Support;

use App\Models\MemberStatusChange;
use App\Models\User;
use App\Notifications\AccountStatusChanged;
use App\Notifications\AlumniStatusGranted;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Membership lifecycle after approval: suspend, deactivate and reactivate
 * accounts (REG-REQ06), keep the history members and officers see
 * (MEM-REQ04), and move students to alumni (REG-REQ07).
 */
final class MemberAccounts
{
    /**
     * Allowed status changes, from => [to...].
     */
    public const TRANSITIONS = [
        User::STATUS_ACTIVE => [User::STATUS_SUSPENDED, User::STATUS_DEACTIVATED],
        User::STATUS_SUSPENDED => [User::STATUS_ACTIVE, User::STATUS_DEACTIVATED],
        User::STATUS_DEACTIVATED => [User::STATUS_ACTIVE],
    ];

    public const STUDENT_CATEGORIES = ['secondary', 'college', 'university'];

    /**
     * @return list<string>
     */
    public static function allowedStatuses(User $member): array
    {
        return self::TRANSITIONS[$member->account_status] ?? [];
    }

    public static function changeStatus(User $member, string $status, string $reason, User $officer): void
    {
        $reason = trim($reason);

        if ($member->is($officer)) {
            throw new InvalidArgumentException(__('You cannot change the status of your own account.'));
        }

        if ($member->user_type === 'staff') {
            throw new InvalidArgumentException(__('Staff accounts are managed under Users, not Members.'));
        }

        if (! in_array($status, self::allowedStatuses($member), true)) {
            throw new InvalidArgumentException(__('This change is not allowed for the member’s current status.'));
        }

        if ($reason === '') {
            throw new InvalidArgumentException(__('Give a reason. The member will see it.'));
        }

        $from = $member->account_status;

        DB::transaction(function () use ($member, $status, $reason, $officer, $from) {
            $member->forceFill([
                'account_status' => $status,
                'status_reason' => $status === User::STATUS_ACTIVE ? null : $reason,
                'status_changed_at' => now(),
            ])->save();

            self::recordChange($member, MemberStatusChange::FIELD_STATUS, $from, $status, $reason, $officer);

            if ($status !== User::STATUS_ACTIVE) {
                $member->tokens()->delete();
                DB::table('sessions')->where('user_id', $member->user_id)->delete();
            } elseif (blank($member->membership_id)) {
                MembershipCard::issue($member);
            }
        });

        Notify::send($member, AccountStatusChanged::for($status, $status === User::STATUS_ACTIVE ? null : $reason));
    }

    public static function recordChange(User $member, string $field, ?string $from, string $to, ?string $reason, ?User $officer): void
    {
        MemberStatusChange::create([
            'user_id' => $member->user_id,
            'field' => $field,
            'from_value' => $from,
            'to_value' => $to,
            'reason' => $reason,
            'changed_by' => $officer?->user_id,
            'changed_at' => now(),
        ]);
    }

    public static function canBecomeAlumni(User $member): bool
    {
        return $member->isActive() && in_array($member->user_type, self::STUDENT_CATEGORIES, true);
    }

    /**
     * A member says they have finished their studies; an officer confirms.
     */
    public static function requestAlumni(User $member): void
    {
        if (! self::canBecomeAlumni($member)) {
            throw new InvalidArgumentException(__('Only active student members can ask to become alumni.'));
        }

        $member->forceFill(['alumni_requested_at' => now()])->save();
    }

    public static function cancelAlumniRequest(User $member): void
    {
        $member->forceFill(['alumni_requested_at' => null])->save();
    }

    /**
     * Move a member to alumni. Number, points, history and institution stay.
     */
    public static function makeAlumni(User $member, User $officer, ?string $reason = null): void
    {
        if (! self::canBecomeAlumni($member)) {
            throw new InvalidArgumentException(__(':name cannot be moved to alumni.', ['name' => $member->full_name]));
        }

        $from = $member->user_type;

        DB::transaction(function () use ($member, $officer, $reason, $from) {
            $member->forceFill([
                'user_type' => 'alumni',
                'alumni_since' => now(),
                'alumni_requested_at' => null,
            ])->save();

            self::recordChange($member, MemberStatusChange::FIELD_CATEGORY, $from, 'alumni', $reason, $officer);
        });

        Notify::send($member, new AlumniStatusGranted);
    }

    public static function declineAlumni(User $member, User $officer, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('Give a reason. The member will see it.'));
        }

        $member->forceFill(['alumni_requested_at' => null])->save();
        Audit::record('user.alumni_declined', $member, reason: trim($reason), actor: $officer);
    }

    /**
     * Move several members at once, e.g. a graduating class (decision D-12).
     *
     * @param  list<string>  $memberIds
     * @return array{moved: int, skipped: int}
     */
    public static function makeAlumniInBulk(array $memberIds, User $officer, ?string $reason = null): array
    {
        $report = ['moved' => 0, 'skipped' => 0];

        foreach (User::whereIn('user_id', $memberIds)->get() as $member) {
            if ($officer->canLookAfter($member) && self::canBecomeAlumni($member)) {
                self::makeAlumni($member, $officer, $reason);
                $report['moved']++;
            } else {
                $report['skipped']++;
            }
        }

        $report['skipped'] += count($memberIds) - $report['moved'] - $report['skipped'];

        return $report;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            User::STATUS_PENDING_VERIFICATION => __('Waiting for confirmation'),
            User::STATUS_ACTIVE => __('Active'),
            User::STATUS_SUSPENDED => __('Suspended'),
            User::STATUS_DEACTIVATED => __('Deactivated'),
            User::STATUS_REJECTED => __('Not approved'),
            default => $status,
        };
    }
}
