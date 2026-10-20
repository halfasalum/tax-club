<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\CompetitionPrize;
use App\Models\Reward;
use App\Models\RewardAward;
use App\Models\User;
use App\Notifications\RewardAwarded;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rewards and recognition (RWD-REQ01/02):
 *
 *   awarded ─(needs approval)→ pending_approval ─approve→ approved
 *   approved ─issue→ issued ─deliver→ delivered      (or cancelled)
 *
 * Rewards that cost money need a second officer's approval before they
 * are issued (decision D-16). Stock goes down when a reward is issued.
 */
final class Rewards
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(?Reward $reward, array $values, User $by): Reward
    {
        $values = [
            'name_sw' => trim((string) $values['name_sw']),
            'name_en' => trim((string) $values['name_en']),
            'type' => in_array($values['type'] ?? null, Reward::TYPES, true) ? $values['type'] : 'other',
            'description' => trim((string) ($values['description'] ?? '')) ?: null,
            'value_tzs' => filled($values['value_tzs'] ?? null) ? (int) $values['value_tzs'] : null,
            'stock' => filled($values['stock'] ?? null) ? (int) $values['stock'] : null,
            'needs_approval' => (bool) ($values['needs_approval'] ?? false) || filled($values['value_tzs'] ?? null),
            'is_active' => (bool) ($values['is_active'] ?? true),
        ];

        if ($reward) {
            $reward->update($values);

            return $reward;
        }

        return Reward::create($values);
    }

    /**
     * Prizes per rank, e.g. 1st: trophy, 2nd-3rd: medal.
     *
     * @param  list<array{rank_from: int, rank_to: int, reward_id: int}>  $prizes
     */
    public static function setPrizes(Competition $competition, array $prizes, User $by): void
    {
        DB::transaction(function () use ($competition, $prizes) {
            $competition->prizes()->delete();

            foreach ($prizes as $prize) {
                if (! $prize['reward_id'] || $prize['rank_from'] < 1 || $prize['rank_to'] < $prize['rank_from']) {
                    continue;
                }

                CompetitionPrize::create(['competition_id' => $competition->competition_id, 'rank_from' => $prize['rank_from'], 'rank_to' => $prize['rank_to'], 'reward_id' => $prize['reward_id']]);
            }
        });

        Review::event($competition, 'prizes_set', trans_choice(':count prize|:count prizes', count($prizes), ['count' => count($prizes)]), $by);
    }

    public static function award(User $member, Reward $reward, string $reason, User $by, ?string $sourceType = null, ?string $sourceId = null): RewardAward
    {
        if (! $reward->is_active) {
            throw new InvalidArgumentException(__('This reward is no longer offered.'));
        }

        if ($sourceType && RewardAward::where('user_id', $member->user_id)->where('reward_id', $reward->id)->where('source_type', $sourceType)->where('source_id', $sourceId)->where('status', '!=', RewardAward::CANCELLED)->exists()) {
            throw new InvalidArgumentException(__('This member already has this reward.'));
        }

        $award = RewardAward::create([
            'reward_id' => $reward->id,
            'user_id' => $member->user_id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reason' => trim($reason),
            'status' => $reward->needs_approval ? RewardAward::PENDING_APPROVAL : RewardAward::APPROVED,
            'awarded_by' => $by->user_id,
        ]);

        Audit::record('reward.awarded', $award, after: ['reward' => $reward->name_en, 'member' => $member->membership_id], actor: $by);

        if ($award->status === RewardAward::APPROVED) {
            Notify::send($member, new RewardAwarded($reward->name_sw, $reward->name_en, $award->reason));
        }

        return $award;
    }

    public static function approve(RewardAward $award, User $by): void
    {
        self::requireStatus($award, [RewardAward::PENDING_APPROVAL]);

        if (ContentWorkflow::fourEyes() && $award->awarded_by === $by->user_id) {
            throw new InvalidArgumentException(__('Someone else must approve a reward you gave.'));
        }

        $award->forceFill(['status' => RewardAward::APPROVED, 'approved_by' => $by->user_id, 'approved_at' => now()])->save();
        Audit::record('reward.approved', $award, actor: $by);
        Notify::send($award->user, new RewardAwarded($award->reward->name_sw, $award->reward->name_en, $award->reason));
    }

    public static function issue(RewardAward $award, User $by, ?string $note = null): void
    {
        self::requireStatus($award, [RewardAward::APPROVED]);
        $reward = $award->reward;

        DB::transaction(function () use ($award, $reward, $note) {
            if ($reward->stock !== null) {
                $updated = Reward::whereKey($reward->id)->where('stock', '>', 0)->decrement('stock');

                if (! $updated) {
                    throw new InvalidArgumentException(__('This reward is out of stock.'));
                }
            }

            $award->forceFill(['status' => RewardAward::ISSUED, 'issued_at' => now(), 'delivery_note' => trim((string) $note) ?: $award->delivery_note])->save();
        });

        Audit::record('reward.issued', $award, actor: $by);
    }

    /**
     * Record that the member received it, with evidence (who handed it
     * over, where, receipt or SMS reference).
     */
    public static function deliver(RewardAward $award, User $by, string $evidence): void
    {
        self::requireStatus($award, [RewardAward::ISSUED]);
        $award->forceFill(['status' => RewardAward::DELIVERED, 'delivered_at' => now(), 'delivery_note' => Review::requireReason($evidence)])->save();
        Audit::record('reward.delivered', $award, after: ['evidence' => $evidence], actor: $by);
    }

    public static function cancel(RewardAward $award, User $by, string $reason): void
    {
        self::requireStatus($award, [RewardAward::PENDING_APPROVAL, RewardAward::APPROVED, RewardAward::ISSUED]);

        if ($award->status === RewardAward::ISSUED && $award->reward->stock !== null) {
            Reward::whereKey($award->reward_id)->increment('stock');
        }

        $award->forceFill(['status' => RewardAward::CANCELLED, 'cancelled_reason' => Review::requireReason($reason)])->save();
        Audit::record('reward.cancelled', $award, reason: $reason, actor: $by);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            RewardAward::PENDING_APPROVAL => __('Waiting for approval'),
            RewardAward::APPROVED => __('Approved, to be issued'),
            RewardAward::ISSUED => __('On its way'),
            RewardAward::DELIVERED => __('Received'),
            RewardAward::CANCELLED => __('Cancelled'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'trophy' => __('Trophy'),
            'medal' => __('Medal'),
            'merchandise' => __('Tax Club merchandise'),
            'airtime' => __('Airtime'),
            'books' => __('Books or stationery'),
            'scholarship' => __('Scholarship or bursary'),
            'visit' => __('Visit to TRA'),
            'other' => __('Other'),
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private static function requireStatus(RewardAward $award, array $statuses): void
    {
        if (! in_array($award->status, $statuses, true)) {
            throw new InvalidArgumentException(__('This action is not available for the reward now.'));
        }
    }
}
