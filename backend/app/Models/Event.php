<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReview;
use App\Support\Audience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A Tax Club event or campaign (EVT-REQ01-08).
 */
class Event extends Model
{
    use Auditable, HasFactory, HasReview, HasUuids;

    public const TYPES = ['workshop', 'seminar', 'competition', 'campaign', 'webinar', 'school_visit', 'tax_day', 'other'];

    public const MODES = ['in_person', 'online', 'hybrid'];

    // Publication states.
    public const NOT_PUBLISHED = 'not_published';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const POSTPONED = 'postponed';

    public const CANCELLED = 'cancelled';

    public const CLOSED = 'closed';

    public const ARCHIVED = 'archived';

    /** States in which members can see the event. */
    public const VISIBLE = [self::PUBLISHED, self::POSTPONED, self::CANCELLED, self::CLOSED];

    protected $table = 'events';

    protected $primaryKey = 'event_id';

    /** @var list<string> */
    protected array $auditIgnore = ['description'];

    protected $fillable = [
        'code',
        'title',
        'slug',
        'type',
        'mode',
        'description',
        'objective',
        'organizer',
        'location',
        'region',
        'online_url',
        'starts_at',
        'ends_at',
        'audience',
        'registration_required',
        'registration_closes_at',
        'capacity',
        'waitlist_enabled',
        'registration_question',
        'is_public',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'registration_required' => 'boolean',
        'waitlist_enabled' => 'boolean',
        'capacity' => 'integer',
        'audience' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    /**
     * Live for members: published (or postponed, cancelled or closed, so
     * people who registered can still see what happened).
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('publication_status', self::VISIBLE);
    }

    /**
     * Kept for older callers: events members can see.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->live();
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('publication_status', [self::PUBLISHED, self::POSTPONED])
            ->where(fn (Builder $query) => $query->where('ends_at', '>=', now())->orWhere(fn (Builder $query) => $query->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())));
    }

    /**
     * Shown on the public website.
     */
    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->upcoming()->where('is_public', true);
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            if (blank($event->slug) && filled($event->title)) {
                $base = Str::slug(Str::limit($event->title, 80, '')) ?: 'event';
                $slug = $base;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.Str::lower(Str::random(5));
                }

                $event->slug = $slug;
            }
        });
    }

    // ─── State ─────────────────────────────────────────────────────

    public function isVisible(): bool
    {
        return in_array($this->publication_status, self::VISIBLE, true);
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->isVisible() && Audience::includes($this->audience, $user);
    }

    public function hasEnded(): bool
    {
        return ($this->ends_at ?? $this->starts_at?->copy()->endOfDay())?->isPast() ?? false;
    }

    public function isHappeningNow(): bool
    {
        return $this->starts_at?->isPast() && ! $this->hasEnded();
    }

    public function registrationDeadline(): ?Carbon
    {
        return $this->registration_closes_at ?? $this->starts_at;
    }

    /**
     * Open for new registrations: published, not started (or before the
     * cut-off) and not closed.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->registration_required
            && $this->publication_status === self::PUBLISHED
            && ($this->registrationDeadline() === null || $this->registrationDeadline()->isFuture());
    }

    /**
     * Places taken by confirmed registrations and attendees.
     */
    public function placesTaken(): int
    {
        return $this->registrations()->whereIn('status', [EventRegistration::REGISTERED, EventRegistration::ATTENDED])->count();
    }

    public function placesLeft(): ?int
    {
        return $this->capacity ? max(0, $this->capacity - $this->placesTaken()) : null;
    }

    /**
     * Status for display: live, upcoming, happening, ended, cancelled...
     */
    public function displayStatus(): string
    {
        return match (true) {
            $this->publication_status === self::PUBLISHED && $this->hasEnded() => 'ended',
            $this->publication_status === self::PUBLISHED && $this->isHappeningNow() => 'happening',
            $this->publication_status === self::SCHEDULED && $this->publish_at?->isFuture() => 'scheduled',
            default => $this->publication_status,
        };
    }

    public function reviewSubjectType(): string
    {
        return 'event';
    }

    public function reviewUrl(): string
    {
        return route('admin.events.show', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'event_id';
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id', 'event_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(EventUpdate::class, 'event_id', 'event_id')->latest();
    }

    /**
     * Users registered for this event (M:N via event_registrations).
     */
    public function registeredUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations', 'event_id', 'user_id')
            ->withPivot('status', 'registered_at');
    }
}
