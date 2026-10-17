<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Six-digit codes sent by SMS for password resets, staff sign-in and phone
 * number changes. Codes are stored hashed, expire, allow a limited number
 * of tries, and a new code replaces any earlier one for the same purpose.
 */
final class OneTimeCode
{
    public const PASSWORD_RESET = 'password_reset';

    public const STAFF_LOGIN = 'staff_login';

    public const PHONE_CHANGE = 'phone_change';

    public const MAX_ATTEMPTS = 5;

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function issue(User $user, string $purpose, int $minutes, ?string $ip = null, array $meta = []): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $purpose, $minutes, $ip, $meta, $code) {
            self::pending($user, $purpose)->update(['revoked_at' => now(), 'updated_at' => now()]);

            DB::table('one_time_codes')->insert([
                'user_id' => $user->user_id,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'meta' => $meta ? json_encode($meta) : null,
                'expires_at' => now()->addMinutes($minutes),
                'requested_ip' => $ip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $code;
    }

    /**
     * Check a code and mark it used. Returns the code's data on success,
     * null when the code is wrong, expired, replaced or out of tries.
     *
     * @return array<string, mixed>|null
     */
    public static function consume(User $user, string $purpose, string $code): ?array
    {
        $record = self::pending($user, $purpose)->where('expires_at', '>', now())->latest('id')->first();

        if (! $record || $record->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        DB::table('one_time_codes')->where('id', $record->id)->increment('attempts');

        if (! Hash::check(preg_replace('/\D/', '', $code), $record->code_hash)) {
            return null;
        }

        DB::table('one_time_codes')->where('id', $record->id)->update(['used_at' => now(), 'updated_at' => now()]);

        return json_decode($record->meta ?? '[]', true) ?: [];
    }

    private static function pending(User $user, string $purpose): Builder
    {
        return DB::table('one_time_codes')
            ->where('user_id', $user->user_id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->whereNull('revoked_at');
    }
}
