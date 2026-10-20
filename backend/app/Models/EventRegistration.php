<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's place at an event: registered, waitlisted, cancelled or
 * attended, with how attendance was recorded (EVT-REQ04-06).
 */
class EventRegistration extends Model
{
    public const REGISTERED = 'registered';

    public const WAITLISTED = 'waitlisted';

    public const CANCELLED = 'cancelled';

    public const ATTENDED = 'attended';

    public const CHECK_IN_QR = 'qr';

    public const CHECK_IN_NUMBER = 'number';

    public const CHECK_IN_MANUAL = 'manual';

    public const CREATED_AT = 'registered_at';

    protected $table = 'event_registrations';

    protected $fillable = ['event_id', 'user_id', 'status', 'answer'];

    protected $casts = [
        'registered_at' => 'datetime',
        'waitlisted_at' => 'datetime',
        'promoted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'walk_in' => 'boolean',
    ];

    public function scopeHoldingPlace(Builder $query): Builder
    {
        return $query->whereIn('status', [self::REGISTERED, self::ATTENDED]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::REGISTERED, self::WAITLISTED, self::ATTENDED], true);
    }

    /**
     * Position on the waitlist, starting at 1.
     */
    public function waitlistPosition(): ?int
    {
        if ($this->status !== self::WAITLISTED) {
            return null;
        }

        return static::where('event_id', $this->event_id)->where('status', self::WAITLISTED)
            ->where(fn (Builder $query) => $query->where('waitlisted_at', '<', $this->waitlisted_at)
                ->orWhere(fn (Builder $query) => $query->where('waitlisted_at', $this->waitlisted_at)->where('id', '<', $this->id)))
            ->count() + 1;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by', 'user_id');
    }
}
