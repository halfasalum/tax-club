<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Password reset with a one-time SMS code (REG-REQ05). Responses never
 * reveal whether a phone number or email belongs to an account.
 */
final class PasswordRecovery
{
    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** Codes that may be requested per account in 15 minutes. */
    public const MAX_REQUESTS = 3;

    /**
     * Send a reset code if the identifier belongs to an active account.
     */
    public static function requestCode(string $identifier, ?string $ip = null): void
    {
        // Limits apply before the account lookup so they reveal nothing about it.
        $keys = [
            'password-reset-request|'.mb_strtolower(MemberRegistration::compactPhone($identifier)) => self::MAX_REQUESTS,
            'password-reset-request-ip|'.$ip => 10,
        ];

        foreach ($keys as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw new InvalidArgumentException(__('Too many codes requested. Try again in :minutes minutes.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]));
            }
        }

        foreach (array_keys($keys) as $key) {
            RateLimiter::hit($key, 15 * 60);
        }

        $user = User::findByLoginIdentifier($identifier);

        if (! $user || ! $user->isActive() || blank($user->phone)) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $code, $ip) {
            DB::table('password_reset_codes')
                ->where('user_id', $user->user_id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            DB::table('password_reset_codes')->insert([
                'user_id' => $user->user_id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::CODE_MINUTES),
                'requested_ip' => $ip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Notify::send($user, new PasswordResetCode($code, self::CODE_MINUTES));
    }

    /**
     * Set a new password using a valid code. Signs the user out everywhere.
     */
    public static function reset(string $identifier, string $code, string $password): User
    {
        $invalid = new InvalidArgumentException(__('The code is incorrect or has expired. Request a new code and try again.'));
        $user = User::findByLoginIdentifier($identifier);

        if (! $user || ! $user->isActive()) {
            throw $invalid;
        }

        $record = DB::table('password_reset_codes')
            ->where('user_id', $user->user_id)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record || $record->attempts >= self::MAX_ATTEMPTS) {
            throw $invalid;
        }

        DB::table('password_reset_codes')->where('id', $record->id)->increment('attempts');

        if (! Hash::check(preg_replace('/\D/', '', $code), $record->code_hash)) {
            throw $invalid;
        }

        DB::transaction(function () use ($user, $record, $password) {
            $user->forceFill(['password_hash' => Hash::make($password)])->save();
            DB::table('password_reset_codes')->where('id', $record->id)->update(['used_at' => now(), 'updated_at' => now()]);
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->user_id)->delete();
        });

        Notify::send($user, new PasswordChanged);

        return $user;
    }
}
