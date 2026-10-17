<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A learning item (LRN-REQ01-08). The row holds the live, published
 * fields; changes are made in numbered revisions (ContentVersion).
 */
class Content extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const TYPES = ['article', 'video', 'infographic', 'document'];

    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    public const LANGUAGES = ['sw', 'en'];

    public const AUDIENCES = ['secondary', 'college', 'university', 'alumni'];

    public const NOT_PUBLISHED = 'not_published';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const UNPUBLISHED = 'unpublished';

    public const ARCHIVED = 'archived';

    /**
     * Fields that belong to a revision and are reviewed before publishing.
     */
    public const VERSIONED_FIELDS = [
        'title', 'summary', 'content_type', 'body_text', 'body_markdown', 'objectives', 'file_url', 'media_path',
        'category_id', 'level', 'language', 'keywords', 'audience', 'estimated_minutes', 'is_public',
    ];

    protected $table = 'content';

    protected $primaryKey = 'content_id';

    /** @var list<string> */
    protected array $auditIgnore = ['body_text', 'body_markdown'];

    protected $fillable = [
        'code',
        'title',
        'slug',
        'summary',
        'category_id',
        'level',
        'language',
        'content_type',
        'file_url',
        'media_path',
        'body_text',
        'body_markdown',
        'objectives',
        'keywords',
        'audience',
        'estimated_minutes',
        'uploaded_by',
        'published_at',
        'publish_at',
        'expires_at',
        'archived_at',
        'is_active',
        'is_public',
        'publication_status',
        'current_version',
        'review_due_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'audience' => 'array',
        'published_at' => 'datetime',
        'publish_at' => 'datetime',
        'expires_at' => 'datetime',
        'archived_at' => 'datetime',
        'review_due_at' => 'datetime',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    /**
     * Published and inside its publication window.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('publication_status', [self::PUBLISHED, self::SCHEDULED])
            ->whereNotNull('current_version')
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Kept for older callers: live content.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->live();
    }

    /**
     * Live content that may be shown on the public website.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->live()->where('is_public', true);
    }

    /**
     * Content meant for this member's category (LRN-REQ04). Staff see all.
     */
    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        if ($user->user_type === 'staff') {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->whereNull('audience')
            ->orWhereJsonLength('audience', 0)
            ->orWhereJsonContains('audience', $user->user_type));
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('content_type', $type);
    }

    public function isLive(): bool
    {
        return in_array($this->publication_status, [self::PUBLISHED, self::SCHEDULED], true)
            && $this->current_version !== null
            && ($this->publish_at === null || $this->publish_at->lte(now()))
            && ($this->expires_at === null || $this->expires_at->gt(now()));
    }

    public function isFor(User $user): bool
    {
        return $user->user_type === 'staff' || empty($this->audience) || in_array($user->user_type, $this->audience, true);
    }

    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk('public')->url($this->media_path) : null;
    }

    /**
     * YouTube or Vimeo embed address for a video link, if recognised.
     */
    public function embedUrl(): ?string
    {
        $url = (string) $this->file_url;

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $url, $match)) {
            return 'https://www.youtube-nocookie.com/embed/'.$match[1];
        }

        if (preg_match('~vimeo\.com/(\d+)~', $url, $match)) {
            return 'https://player.vimeo.com/video/'.$match[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->keywords))));
    }

    protected static function booted(): void
    {
        static::saving(function (Content $content) {
            if (blank($content->slug) && filled($content->title)) {
                $content->slug = static::uniqueSlug($content->title);
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'content';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class, 'content_id', 'content_id')->orderByDesc('version');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class, 'content_id', 'content_id');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserProgress::class, 'content_id', 'content_id');
    }
}
