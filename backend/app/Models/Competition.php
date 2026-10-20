<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasReview;
use App\Support\Audience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A Tax Club competition (CMP-REQ01-05). Either quiz-based (scores come
 * from a linked quiz) or submission-based (essays, posters, videos or
 * projects scored by judges).
 */
class Competition extends Model
{
    use Auditable, HasReview, HasUuids;

    public const TYPES = ['quiz', 'submission'];

    public const SUBMISSION_KINDS = ['text', 'link', 'file'];

    public const NOT_PUBLISHED = 'not_published';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const CANCELLED = 'cancelled';

    public const ARCHIVED = 'archived';

    // Results states.
    public const RESULTS_PENDING = 'pending';

    public const RESULTS_IN_REVIEW = 'in_review';

    public const RESULTS_PUBLISHED = 'published';

    protected $primaryKey = 'competition_id';

    /** @var list<string> */
    protected array $auditIgnore = ['description', 'rules'];

    protected $fillable = ['code', 'title', 'slug', 'description', 'rules', 'type', 'quiz_id', 'submission_kind', 'audience', 'max_participants', 'registration_closes_at', 'starts_at', 'ends_at', 'winners_count', 'created_by'];

    protected $casts = [
        'audience' => 'array',
        'max_participants' => 'integer',
        'winners_count' => 'integer',
        'registration_closes_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'results_published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Competition $competition) {
            if (blank($competition->slug) && filled($competition->title)) {
                $competition->slug = (Str::slug(Str::limit($competition->title, 80, '')) ?: 'competition').'-'.Str::lower(Str::random(5));
            }
        });
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('publication_status', [self::PUBLISHED, self::CANCELLED]);
    }

    public function isVisibleTo(User $user): bool
    {
        return in_array($this->publication_status, [self::PUBLISHED, self::CANCELLED], true) && Audience::includes($this->audience, $user);
    }

    public function registrationOpen(): bool
    {
        return $this->publication_status === self::PUBLISHED
            && ! $this->hasEnded()
            && ($this->registration_closes_at === null || $this->registration_closes_at->isFuture());
    }

    public function isRunning(): bool
    {
        return $this->publication_status === self::PUBLISHED
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ! $this->hasEnded();
    }

    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->results_status === self::RESULTS_PUBLISHED => 'results',
            $this->publication_status === self::PUBLISHED && $this->hasEnded() => 'ended',
            $this->publication_status === self::PUBLISHED && $this->isRunning() => 'happening',
            default => $this->publication_status,
        };
    }

    public function reviewSubjectType(): string
    {
        return 'competition';
    }

    public function reviewUrl(): string
    {
        return route('admin.competitions.show', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'competition_id';
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'quiz_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class, 'competition_id', 'competition_id');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(CompetitionPrize::class, 'competition_id', 'competition_id')->orderBy('rank_from');
    }
}
