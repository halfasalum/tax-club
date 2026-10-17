<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Staff accounts for patrons and coordinators, found by phone number or
 * created with a one-time password, and the programme roles they hold.
 */
final class StaffAccounts
{
    /**
     * @param  array{phone: string, full_name?: ?string, email?: ?string}  $data
     * @return array{0: User, 1: ?string} The account and, if it was just created, its one-time password.
     */
    public static function findOrCreate(array $data, ?string $institutionId, string $missingNameMessage): array
    {
        $phone = MemberRegistration::normalizePhone($data['phone']);

        if ($user = User::where('phone', $phone)->first()) {
            return [$user, null];
        }

        if (blank($data['full_name'] ?? null)) {
            throw new InvalidArgumentException($missingNameMessage);
        }

        $temporaryPassword = Str::password(12, symbols: false);
        $user = User::create([
            'full_name' => trim($data['full_name']),
            'phone' => $phone,
            'email' => filled($data['email'] ?? null) ? Str::lower(trim($data['email'])) : null,
            'password_hash' => Hash::make($temporaryPassword),
            'user_type' => 'staff',
            'institution_id' => $institutionId,
            'is_verified' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        return [$user, $temporaryPassword];
    }

    public static function grantRole(User $user, string $slug, ?User $officer): void
    {
        if ($role = Role::where('slug', $slug)->where('is_active', true)->first()) {
            $user->roles()->syncWithoutDetaching([
                $role->role_id => ['assigned_by' => $officer?->user_id, 'assigned_at' => now()],
            ]);
            Audit::record('user.role_granted', $user, after: ['role' => $slug], actor: $officer);
        }
    }

    public static function revokeRole(User $user, string $slug, ?User $officer): void
    {
        if ($role = Role::where('slug', $slug)->first()) {
            $user->roles()->detach($role->role_id);
            Audit::record('user.role_removed', $user, before: ['role' => $slug], actor: $officer);
        }
    }
}
