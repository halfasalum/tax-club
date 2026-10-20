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
use Illuminate\Support\Str;

/**
 * An employment or development opportunity (OPP-REQ01-06).
 */
class Opportunity extends Model
{
    use Auditable, HasFactory, HasReview, HasUuids;

    public const TYPES = ['job', 'internship', 'attachment', 'scholarship', 'volunteer', 'training', 'entrepreneurship', 'competition', 'other'];

    public const APPLY_METHODS = ['link', 'email', 'in_person'];

    public const WORK_MODES = ['on_site', 'remote', 'hybrid'];

    // Publication states.
    public const NOT_PUBLISHED = 'not_published';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const CLOSED = 'closed';

    public const WITHDRAWN = 'withdrawn';

    public const ARCHIVED = 'archived';

    /** Opportunities closing within this many days are flagged. */
    public const CLOSING_SOON_DAYS = 7;

    protected $table = 'opportunities';

    protected $primaryKey = 'opportunity_id';

    /** @var list<string> */
    protected array $auditIgnore = ['description'];

    protected $fillable = [
        'code',
        'title',
        'slug',
        'organization',
        'type',
        'description',
        'eligibility',
        'location',
        'region',
        'work_mode',
        'audience',
        'deadline',
        'apply_method',
        'apply_url',
        'apply_email',
        'apply_instructions',
        'attachment_path',
        'attachment_name',
        'posted_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'deadline' => 'date',
        'audience' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'closed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Opportunity $opportunity) {
            if (blank($opportunity->slug) && filled($opportunity->title)) {
                $base = Str::slug(Str::limit($opportunity->title, 80, '')) ?: 'opportunity';
                $slug = $base;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.Str::lower(Str::random(5));
                }

                $opportunity->slug = $slug;
            }
        });
    }

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->open();
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Published and before the deadline (OPP-REQ04).
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('publication_status', self::PUBLISHED)
            ->where(fn (Builder $query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()->toDateString()));
    }

    // ─── State ─────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->publication_status === self::PUBLISHED && ($this->deadline === null || $this->deadline->endOfDay()->isFuture());
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->isOpen() && Audience::includes($this->audience, $user);
    }

    public function isClosingSoon(): bool
    {
        return $this->isOpen() && $this->deadline !== null && $this->deadline->endOfDay()->diffInDays(now(), true) <= self::CLOSING_SOON_DAYS;
    }

    public function displayStatus(): string
    {
        return match (true) {
            $this->publication_status === self::PUBLISHED && ! $this->isOpen() => 'closed',
            default => $this->publication_status,
        };
    }

    public function applyTarget(): ?string
    {
        return match ($this->apply_method) {
            'link' => $this->apply_url,
            'email' => $this->apply_email ? 'mailto:'.$this->apply_email.'?subject='.rawurlencode($this->title) : null,
            default => null,
        };
    }

    public function reviewSubjectType(): string
    {
        return 'opportunity';
    }

    public function reviewUrl(): string
    {
        return route('admin.opportunities.show', $this);
    }

    public function getRouteKeyName(): string
    {
        return 'opportunity_id';
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by', 'user_id');
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'opportunity_saves', 'opportunity_id', 'user_id');
    }
}
