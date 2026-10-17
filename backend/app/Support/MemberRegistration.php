<?php

namespace App\Support;

use App\Models\Institution;
use App\Models\MemberStatusChange;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationReceived;
use App\Notifications\RegistrationRejected;
use Illuminate\Support\Carbon;
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
    /** Youngest and oldest age accepted at registration (decision D-04). */
    public const MIN_AGE = 11;

    public const MAX_AGE = 35;

    /** @var list<string> */
    public const GUARDIAN_RELATIONSHIPS = ['parent', 'guardian', 'other'];

    /** Tanzanian mobile number: 07…, 06…, 2557… or +2557…. */
    public const PHONE_PATTERN = '/^(\+?255|0)[67]\d{8}$/';

    /**
     * Validation rules shared by every registration channel. Guardian details
     * are required only when the date of birth makes the applicant a minor.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function rules(array $input = []): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN, 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'user_type' => ['required', 'in:secondary,college,university'],
            'institution_id' => ['required', 'uuid', Rule::exists('institutions', 'institution_id')->where('status', Institution::STATUS_ACTIVE)],
            'date_of_birth' => [
                'required', 'date',
                'before_or_equal:'.now()->subYears(self::MIN_AGE)->toDateString(),
                'after:'.now()->subYears(self::MAX_AGE + 1)->toDateString(),
            ],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
        ];

        if (self::isMinorDateOfBirth($input['date_of_birth'] ?? null)) {
            $rules += [
                'guardian_name' => ['required', 'string', 'min:3', 'max:150'],
                'guardian_phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN],
                'guardian_relationship' => ['required', Rule::in(self::GUARDIAN_RELATIONSHIPS)],
                'guardian_consent' => ['accepted'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'phone.regex' => __('Enter a Tanzanian mobile number, for example 0712 345 678.'),
            'phone.unique' => __('This phone number is already registered. Sign in or recover your account.'),
            'email.unique' => __('This email is already registered. Sign in or recover your account.'),
            'guardian_phone.regex' => __('Enter a Tanzanian mobile number, for example 0712 345 678.'),
            'date_of_birth.before_or_equal' => __('Tax Club members must be at least :age years old.', ['age' => self::MIN_AGE]),
            'date_of_birth.after' => __('Tax Club membership is for people aged :age or younger.', ['age' => self::MAX_AGE]),
            'guardian_consent.accepted' => __('Your parent or guardian must agree before you can join.'),
            'accepted_terms.accepted' => __('You must accept the terms to register.'),
        ];
    }

    public static function isMinorDateOfBirth(mixed $dateOfBirth): bool
    {
        if (blank($dateOfBirth)) {
            return false;
        }

        try {
            return User::ageBandFor(Carbon::parse($dateOfBirth)) === User::AGE_MINOR;
        } catch (\Throwable) {
            return false;
        }
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

        $guardianPhone = self::compactPhone($input['guardian_phone'] ?? '');

        if (preg_match('/^(\+?255|0)[67]\d{8}$/', $guardianPhone)) {
            $input['guardian_phone'] = self::normalizePhone($guardianPhone);
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
     * @param  array{full_name: string, phone: string, email?: ?string, password: string, user_type: string, institution_id: string, date_of_birth: string, gender?: ?string, guardian_name?: ?string, guardian_phone?: ?string, guardian_relationship?: ?string}  $data
     */
    public static function register(array $data): User
    {
        $dateOfBirth = Carbon::parse($data['date_of_birth'])->startOfDay();
        $ageBand = User::ageBandFor($dateOfBirth);
        $isMinor = $ageBand === User::AGE_MINOR;

        $user = DB::transaction(function () use ($data, $dateOfBirth, $ageBand, $isMinor) {
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
                'date_of_birth' => $dateOfBirth->toDateString(),
                'gender' => $data['gender'] ?? null,
                'age_band' => $ageBand,
                'preferred_locale' => app()->getLocale(),
                'guardian_name' => $isMinor ? trim($data['guardian_name']) : null,
                'guardian_phone' => $isMinor ? self::normalizePhone($data['guardian_phone']) : null,
                'guardian_relationship' => $isMinor ? $data['guardian_relationship'] : null,
                'guardian_consent_at' => $isMinor ? now() : null,
            ]);

            if ($defaultRole = Role::getDefault()) {
                $user->roles()->attach($defaultRole->role_id, ['assigned_by' => null, 'assigned_at' => now()]);
            }

            return $user;
        });

        Notify::send($user, new RegistrationReceived);

        return $user;
    }

    /**
     * Approve a pending registration. For members under 18 the officer must
     * confirm that the guardian has given consent (SAF-REQ05).
     */
    public static function approve(User $user, User $officer, bool $guardianConsentConfirmed = false): void
    {
        if ($user->account_status !== User::STATUS_PENDING_VERIFICATION) {
            throw new InvalidArgumentException('Only registrations awaiting verification can be approved.');
        }

        if ($user->isMinor() && ! $guardianConsentConfirmed) {
            throw new InvalidArgumentException(__('Confirm that the parent or guardian agrees before approving a member under 18.'));
        }

        $user->forceFill([
            'guardian_consent_confirmed_by' => $user->isMinor() ? $officer->user_id : null,
            'guardian_consent_confirmed_at' => $user->isMinor() ? now() : null,
            'account_status' => User::STATUS_ACTIVE,
            'is_verified' => true,
            'verified_by' => $officer->user_id,
            'verified_at' => now(),
            'status_reason' => null,
            'status_changed_at' => now(),
        ])->save();

        MembershipCard::issue($user);
        MemberAccounts::recordChange($user, MemberStatusChange::FIELD_STATUS, User::STATUS_PENDING_VERIFICATION, User::STATUS_ACTIVE, null, $officer);

        Notify::send($user, new RegistrationApproved);
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
            'status_changed_at' => now(),
        ])->save();

        MemberAccounts::recordChange($user, MemberStatusChange::FIELD_STATUS, User::STATUS_PENDING_VERIFICATION, User::STATUS_REJECTED, trim($reason), $officer);
        Notify::send($user, new RegistrationRejected);
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
            User::STATUS_DEACTIVATED => __('Your account has been deactivated. Contact your Tax Club patron or TRA coordinator.'),
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
}
