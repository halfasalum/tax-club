<?php

namespace App\Support;

use App\Models\CoordinatorAssignment;
use App\Models\User;
use App\Notifications\CoordinatorAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * TRA Tax Club coordinators and their regions (INS-REQ06, decision D-09).
 * A coordinator sees every institution, registration and member in the
 * regions they currently cover.
 */
final class Coordinators
{
    /**
     * @param  array{phone: string, region: string, full_name?: ?string, email?: ?string, starts_on?: ?string}  $data
     * @return array{0: CoordinatorAssignment, 1: ?string} The assignment and a one-time password for a new account.
     */
    public static function assign(array $data, ?User $officer): array
    {
        if (! in_array($data['region'], config('tanzania.regions'), true)) {
            throw new InvalidArgumentException(__('Choose a region from the list.'));
        }

        $existing = User::where('phone', MemberRegistration::normalizePhone($data['phone']))->first();

        if ($existing && CoordinatorAssignment::current()->where('user_id', $existing->user_id)->where('region', $data['region'])->exists()) {
            throw new InvalidArgumentException(__(':name already coordinates :region.', ['name' => $existing->full_name, 'region' => $data['region']]));
        }

        if ($existing && $existing->user_type !== 'staff') {
            throw new InvalidArgumentException(__('This phone number belongs to a member account. Coordinators need a staff account.'));
        }

        $result = DB::transaction(function () use ($data, $officer) {
            [$coordinator, $temporaryPassword] = StaffAccounts::findOrCreate(
                $data,
                null,
                __('No account uses this phone number. Enter the coordinator’s full name to create one.'),
            );

            StaffAccounts::grantRole($coordinator, 'coordinator', $officer);

            $assignment = CoordinatorAssignment::create([
                'user_id' => $coordinator->user_id,
                'region' => $data['region'],
                'starts_on' => $data['starts_on'] ?? now()->toDateString(),
                'assigned_by' => $officer?->user_id,
            ]);

            return [$assignment, $temporaryPassword];
        });

        Notify::send($result[0]->user, new CoordinatorAssigned($data['region'], $result[1]));

        return $result;
    }

    public static function end(CoordinatorAssignment $assignment, string $reason, ?User $officer): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('A reason is required to end an assignment.'));
        }

        $assignment->forceFill([
            'ends_on' => now()->toDateString(),
            'end_reason' => trim($reason),
            'ended_by' => $officer?->user_id,
        ])->save();

        $stillCoordinator = CoordinatorAssignment::current()->where('user_id', $assignment->user_id)->exists();

        if (! $stillCoordinator) {
            StaffAccounts::revokeRole($assignment->user, 'coordinator', $officer);
        }
    }

    /**
     * Current coordinators of a region, for institution pages.
     *
     * @return Collection<int, User>
     */
    public static function forRegion(?string $region): Collection
    {
        if (blank($region)) {
            return collect();
        }

        return CoordinatorAssignment::current()->where('region', $region)->with('user:user_id,full_name,phone,email')->get()->pluck('user')->filter()->values();
    }
}
