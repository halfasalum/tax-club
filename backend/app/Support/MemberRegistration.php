<?php

namespace App\Support;

use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Registration lifecycle shared by the website, the mobile API and the
 * Administration Portal (REG-REQ01–03, MEM-REQ01).
 *
 * Registration creates a pending account with a reference number. Only an
 * authorised officer's approval activates it and issues a membership number.
 */
final class MemberRegistration
{
    /**
     * Validation rules shared by every registration channel.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^(\+?255|0)[67]\d{8}$/', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_type' => ['required', 'in:secondary,college,university'],
            'institution_id' => ['required', 'uuid', Rule::exists('institutions', 'institution_id')->where('status', Institution::STATUS_ACTIVE)],
        ];
    }

    /**
     * Normalise input before validation so uniqueness is checked against the
     * stored phone format.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $input): array
    {
        $phone = self::compactPhone($input['phone'] ?? '');

        if (preg_match('/^(\+?255|0)[67]\d{8}$/', $phone)) {
            $input['phone'] = self::normalizePhone($phone);
        }

        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        return $input;
    }

    /**
     * Remove the spaces and dashes people type in phone numbers.
     */
    public static function compactPhone(?string $phone): string
    {
        return preg_replace('/[\s-]+/', '', (string) $phone);
    }

    /**
     * Normalise a Tanzanian mobile number to +255XXXXXXXXX.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    /**
     * @param  array{full_name: string, phone: string, email?: ?string, password: string, user_type: string, institution_id: string}  $data
     */
    public static function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'full_name' => trim($data['full_name']),
                'phone' => self::normalizePhone($data['phone']),
                'email' => filled($data['email'] ?? null) ? Str::lower($data['email']) : null,
                'password_hash' => Hash::make($data['password']),
                'user_type' => $data['user_type'],
                'institution_id' => $data['institution_id'],
                'registration_reference' => self::newReference(),
                'is_verified' => false,
                'account_status' => User::STATUS_PENDING_VERIFICATION,
            ]);

            if ($defaultRole = Role::getDefault()) {
                $user->roles()->attach($defaultRole->role_id, ['assigned_by' => null, 'assigned_at' => now()]);
            }

            return $user;
        });
    }

    public static function approve(User $user, User $officer): void
    {
        if ($user->account_status !== User::STATUS_PENDING_VERIFICATION) {
            throw new InvalidArgumentException('Only registrations awaiting verification can be approved.');
        }

        $user->forceFill([
            'account_status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'verified_by' => $officer->user_id,
            'verified_at' => now(),
            'status_reason' => null,
            'membership_id' => $user->membership_id ?: self::newMembershipNumber(),
        ])->save();
    }

    public static function reject(User $user, User $officer, string $reason): void
    {
        if ($user->account_status !== User::STATUS_PENDING_VERIFICATION) {
            throw new InvalidArgumentException('Only registrations awaiting verification can be rejected.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to reject a registration.');
        }

        $user->forceFill([
            'account_status' => User::STATUS_REJECTED,
            'is_verified' => false,
            'verified_by' => $officer->user_id,
            'verified_at' => now(),
            'status_reason' => trim($reason),
        ])->save();
    }

    /**
     * Message shown when a non-active account tries to sign in.
     */
    public static function blockedLoginMessage(User $user): string
    {
        return match ($user->account_status) {
            User::STATUS_PENDING_VERIFICATION => $user->registration_reference
                ? __('Your registration :reference is waiting for confirmation by your patron or TRA coordinator.', ['reference' => $user->registration_reference])
                : __('Your account is waiting for confirmation by your patron or TRA coordinator.'),
            User::STATUS_REJECTED => __('Your registration was not approved. Contact your Tax Club patron for help.'),
            User::STATUS_SUSPENDED => __('Your account is suspended. Contact your Tax Club patron or TRA coordinator.'),
            default => __('Your account cannot be used to sign in.'),
        };
    }

    private static function newReference(): string
    {
        do {
            $reference = 'REG-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (User::where('registration_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Interim format until TRA approves the final one (decision D-03).
     */
    private static function newMembershipNumber(): string
    {
        do {
            $number = 'TC-'.now()->format('y').'-'.Str::upper(Str::random(8));
        } while (User::where('membership_id', $number)->exists());

        return $number;
    }
}
