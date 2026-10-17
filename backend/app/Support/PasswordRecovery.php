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

    public const MAX_ATTEMPTS = OneTimeCode::MAX_ATTEMPTS;

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

        $code = OneTimeCode::issue($user, OneTimeCode::PASSWORD_RESET, self::CODE_MINUTES, $ip);
        Audit::record('auth.password_reset_requested', $user, actor: $user);

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

        if (OneTimeCode::consume($user, OneTimeCode::PASSWORD_RESET, $code) === null) {
            throw $invalid;
        }

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password_hash' => Hash::make($password)])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->user_id)->delete();
        });

        Audit::record('auth.password_reset', $user, actor: $user);
        Notify::send($user, new PasswordChanged);

        return $user;
    }
}
