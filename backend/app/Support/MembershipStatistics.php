<?php

namespace App\Support;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membership numbers per institution and region (INS-REQ08). Staff accounts
 * are never counted. Results respect the viewer's institution scope.
 */
final class MembershipStatistics
{
    public const CATEGORIES = ['secondary', 'college', 'university', 'alumni'];

    /**
     * Counted columns, in report order.
     *
     * @return array<string, string> column => SQL condition on users
     */
    public static function measures(): array
    {
        $active = "users.account_status = '".User::STATUS_ACTIVE."'";
        $measures = ['active_members' => $active];

        foreach (self::CATEGORIES as $category) {
            $measures["active_{$category}"] = "{$active} and users.user_type = '{$category}'";
        }

        return $measures + [
            'under_18' => "{$active} and users.age_band = '".User::AGE_MINOR."'",
            'joined_this_month' => "{$active} and users.membership_issued_at >= '".now()->startOfMonth()->toDateTimeString()."'",
            'waiting' => "users.account_status = '".User::STATUS_PENDING_VERIFICATION."'",
            'suspended' => "users.account_status = '".User::STATUS_SUSPENDED."'",
        ];
    }

    /**
     * One row per institution with its counts.
     *
     * @param  list<string>|null  $scope
     */
    public static function perInstitution(?array $scope, string $region = '', string $type = ''): Builder
    {
        $columns = ['institutions.institution_id', 'institutions.code', 'institutions.name', 'institutions.type', 'institutions.region', 'institutions.district', 'institutions.status'];

        $query = Institution::query()
            ->leftJoin('users', fn ($join) => $join->on('users.institution_id', '=', 'institutions.institution_id')->where('users.user_type', '!=', 'staff'))
            ->select($columns)
            ->selectRaw("(select count(*) from tax_clubs where tax_clubs.institution_id = institutions.institution_id and tax_clubs.status = 'active') as active_clubs")
            ->when($scope !== null, fn ($query) => $query->whereIn('institutions.institution_id', $scope))
            ->when($region !== '', fn ($query) => $query->where('institutions.region', $region))
            ->when($type !== '', fn ($query) => $query->where('institutions.type', $type))
            ->groupBy($columns);

        foreach (self::measures() as $column => $condition) {
            $query->selectRaw("sum(case when {$condition} then 1 else 0 end) as {$column}");
        }

        return $query;
    }

    /**
     * Totals per region.
     *
     * @param  list<string>|null  $scope
     * @return Collection<int, object>
     */
    public static function perRegion(?array $scope, string $type = ''): Collection
    {
        $query = DB::table('institutions')
            ->leftJoin('users', fn ($join) => $join->on('users.institution_id', '=', 'institutions.institution_id')->where('users.user_type', '!=', 'staff'))
            ->select('institutions.region')
            ->selectRaw('count(distinct institutions.institution_id) as institutions')
            ->when($scope !== null, fn ($query) => $query->whereIn('institutions.institution_id', $scope))
            ->when($type !== '', fn ($query) => $query->where('institutions.type', $type))
            ->groupBy('institutions.region')
            ->orderBy('institutions.region');

        foreach (self::measures() as $column => $condition) {
            $query->selectRaw("sum(case when {$condition} then 1 else 0 end) as {$column}");
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, int>
     */
    public static function totals(Collection $rows): array
    {
        $totals = ['institutions' => (int) $rows->sum('institutions')];

        foreach (array_keys(self::measures()) as $column) {
            $totals[$column] = (int) $rows->sum($column);
        }

        return $totals;
    }

    /**
     * Column headings for reports.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'active_members' => __('Active members'),
            'active_secondary' => __('Secondary school'),
            'active_college' => __('College'),
            'active_university' => __('University'),
            'active_alumni' => __('Alumni'),
            'under_18' => __('Under 18'),
            'joined_this_month' => __('Joined this month'),
            'waiting' => __('Waiting for confirmation'),
            'suspended' => __('Suspended'),
        ];
    }
}
