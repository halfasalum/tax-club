<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboards by period and area (GAM-REQ10). Only active members are
 * ranked; everyone is shown by first name and last initial, and the
 * institution of members under 18 is replaced by their region (D-04).
 */
final class Leaderboard
{
    public const PERIODS = ['all', 'month', 'week'];

    public const SCOPES = ['national', 'region', 'institution'];

    public const SIZE = 50;

    public static function enabled(): bool
    {
        return (bool) AppSetting::get('leaderboards.enabled', true);
    }

    /**
     * @return array{entries: Collection<int, array<string, mixed>>, me: ?array<string, mixed>}
     */
    public static function rank(User $viewer, string $period = 'all', string $scope = 'national'): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'all';
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'national';
        $since = match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => null,
        };

        $members = DB::table('users')
            ->leftJoin('institutions', 'institutions.institution_id', '=', 'users.institution_id')
            ->where('users.account_status', User::STATUS_ACTIVE)
            ->where('users.user_type', '!=', 'staff')
            ->when($scope === 'institution', fn ($query) => $query->where('users.institution_id', $viewer->institution_id))
            ->when($scope === 'region', fn ($query) => $query->where('institutions.region', $viewer->institution?->region));

        if ($since) {
            $points = DB::table('points_ledger')->where('created_at', '>=', $since)->groupBy('user_id')
                ->selectRaw('user_id, sum(points) as points')->havingRaw('sum(points) > 0');
            $members->joinSub($points, 'period_points', 'period_points.user_id', '=', 'users.user_id')->addSelect(DB::raw('period_points.points as points'));
        } else {
            $members->where('users.total_points', '>', 0)->addSelect(DB::raw('users.total_points as points'));
        }

        $members->addSelect('users.user_id', 'users.full_name', 'users.age_band', 'institutions.name as institution', 'institutions.region')
            ->orderByDesc('points')
            ->orderBy('users.full_name');

        $rows = $members->get();
        $entries = $rows->values()->map(fn (object $row, int $index) => self::entry($row, $index + 1, $viewer));

        return [
            'entries' => $entries->take(self::SIZE),
            'me' => $entries->firstWhere('is_me', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function entry(object $row, int $rank, User $viewer): array
    {
        $member = new User(['full_name' => $row->full_name]);
        $minor = $row->age_band === User::AGE_MINOR;

        return [
            'rank' => $rank,
            'user_id' => $row->user_id,
            'display_name' => $member->publicName(),
            'place' => $minor ? $row->region : $row->institution,
            'points' => (int) $row->points,
            'is_me' => $row->user_id === $viewer->user_id,
        ];
    }
}
