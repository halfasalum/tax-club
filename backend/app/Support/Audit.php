<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the audit trail (NFR-12). Model changes are recorded by the
 * Auditable trait; security events are recorded here explicitly.
 */
final class Audit
{
    public const REDACTED = '[changed]';

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?User $actor = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'actor_id' => ($actor ?? auth()->user())?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $subject ? self::typeOf($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'reason' => $reason,
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }

    public static function typeOf(Model|string $subject): string
    {
        return Str::snake(class_basename($subject));
    }

    /**
     * Show only the start and end of a phone number or email in security
     * events about unknown accounts.
     */
    public static function mask(string $value): string
    {
        $length = mb_strlen($value);

        return $length <= 6 ? str_repeat('•', $length) : mb_substr($value, 0, 3).str_repeat('•', $length - 6).mb_substr($value, -3);
    }
}
