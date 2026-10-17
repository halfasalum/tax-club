<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One numbered revision of a learning item (LRN-REQ08).
 */
class ContentVersion extends Model
{
    public const DRAFT = 'draft';

    public const IN_REVIEW = 'in_review';

    public const RETURNED = 'returned';

    public const REJECTED = 'rejected';

    public const APPROVED = 'approved';

    public const PUBLISHED = 'published';

    public const SUPERSEDED = 'superseded';

    /** Revisions still being worked on or reviewed. */
    public const OPEN = [self::DRAFT, self::IN_REVIEW, self::RETURNED, self::REJECTED, self::APPROVED];

    /** Revisions the author may still change. */
    public const EDITABLE = [self::DRAFT, self::RETURNED, self::REJECTED];

    protected $fillable = [
        'content_id', 'version', 'status', 'fields', 'change_note', 'created_by',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'published_by', 'published_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }

    public function field(string $name): mixed
    {
        return $this->fields[$name] ?? null;
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id', 'content_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }
}
