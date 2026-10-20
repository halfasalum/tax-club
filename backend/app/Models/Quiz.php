<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quiz with its rules and review state (GAM-REQ01-07).
 */
class Quiz extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const SCORED = 'scored';

    public const PRACTICE = 'practice';

    public const RETAIN_BEST = 'best';

    public const RETAIN_LATEST = 'latest';

    public const ANSWERS_AFTER_SUBMIT = 'after_submit';

    public const ANSWERS_AFTER_CLOSE = 'after_close';

    public const ANSWERS_NEVER = 'never';

    // Editorial states (shared with content revisions).
    public const DRAFT = 'draft';

    public const IN_REVIEW = 'in_review';

    public const RETURNED = 'returned';

    public const REJECTED = 'rejected';

    public const APPROVED = 'approved';

    public const EDITABLE = [self::DRAFT, self::RETURNED, self::REJECTED];

    // Publication states.
    public const NOT_PUBLISHED = 'not_published';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const CLOSED = 'closed';

    public const ARCHIVED = 'archived';

    protected $table = 'quizzes';

    protected $primaryKey = 'quiz_id';

    protected $fillable = [
        'code',
        'content_id',
        'category_id',
        'title',
        'description',
        'instructions',
        'audience',
        'mode',
        'pass_mark',
        'time_limit_minutes',
        'max_attempts',
        'retained_result',
        'show_answers',
        'shuffle_questions',
        'total_marks',
        'is_active',
        'editorial_status',
        'publication_status',
        'opens_at',
        'closes_at',
        'created_by',
        'language',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_marks' => 'integer',
        'audience' => 'array',
        'pass_mark' => 'integer',
        'time_limit_minutes' => 'integer',
        'max_attempts' => 'integer',
        'shuffle_questions' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    /**
     * Published and open now (GAM-REQ04).
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('publication_status', [self::PUBLISHED, self::SCHEDULED])
            ->where(fn (Builder $query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    /**
     * Kept for older callers: quizzes open now.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->open();
    }

    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        $query->open();

        if ($user->user_type === 'staff') {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->whereNull('audience')
            ->orWhereJsonLength('audience', 0)
            ->orWhereJsonContains('audience', $user->user_type));
    }

    public function isOpen(): bool
    {
        return in_array($this->publication_status, [self::PUBLISHED, self::SCHEDULED], true)
            && ($this->opens_at === null || $this->opens_at->lte(now()))
            && ($this->closes_at === null || $this->closes_at->gt(now()));
    }

    public function isAvailableTo(User $user): bool
    {
        return $this->isOpen() && ($user->user_type === 'staff' || empty($this->audience) || in_array($user->user_type, $this->audience, true));
    }

    public function hasEnded(): bool
    {
        return $this->publication_status === self::CLOSED || ($this->closes_at !== null && $this->closes_at->lte(now()));
    }

    public function isPractice(): bool
    {
        return $this->mode === self::PRACTICE;
    }

    /**
     * Status for display: live, scheduled, closed, not published...
     */
    public function displayStatus(): string
    {
        if ($this->isOpen()) {
            return 'published';
        }

        if (in_array($this->publication_status, [self::PUBLISHED, self::SCHEDULED], true)) {
            return $this->opens_at?->isFuture() ? 'scheduled' : 'closed';
        }

        return $this->publication_status;
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id', 'content_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'quiz_id', 'quiz_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id', 'quiz_id')->orderBy('order_index');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'user_id');
    }
}
