<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\StaffLoginCode;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Second sign-in step for staff (REG-REQ04, decision D-08): anyone with
 * an administration permission confirms sign-in with a code sent by SMS.
 */
final class StaffSignIn
{
    public const CODE_MINUTES = 10;

    public static function required(User $user): bool
    {
        if (! config('auth.staff_two_factor') || ! $user->canAccessAdmin()) {
            return false;
        }

        if (blank($user->phone)) {
            Audit::record('auth.two_factor_skipped', $user, after: ['reason' => 'no phone number'], actor: $user);

            return false;
        }

        return true;
    }

    public static function sendCode(User $user, ?string $ip = null): void
    {
        $key = 'staff-login-code|'.$user->user_id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new InvalidArgumentException(__('Too many codes requested. Try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ]));
        }
        RateLimiter::hit($key, 15 * 60);

        $code = OneTimeCode::issue($user, OneTimeCode::STAFF_LOGIN, self::CODE_MINUTES, $ip);
        Notify::send($user, new StaffLoginCode($code, self::CODE_MINUTES));
    }

    public static function confirm(User $user, string $code): bool
    {
        $confirmed = OneTimeCode::consume($user, OneTimeCode::STAFF_LOGIN, $code) !== null;

        if (! $confirmed) {
            Audit::record('auth.two_factor_failed', $user, actor: $user);
        }

        return $confirmed;
    }

    /**
     * Record a completed sign-in.
     */
    public static function recordLogin(User $user, string $channel, bool $twoFactor): void
    {
        $user->forceFill(['last_login' => now()])->save();
        Audit::record('auth.login', $user, after: ['channel' => $channel, 'two_factor' => $twoFactor], actor: $user);
    }

    public static function recordFailure(string $identifier, ?User $user, string $channel): void
    {
        Audit::record('auth.login_failed', $user, after: [
            'channel' => $channel,
            'identifier' => Audit::mask(trim($identifier)),
        ], actor: $user);
    }
}
