<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A post or, with a parent, a comment in a discussion space (COM-REQ06).
 */
class CommunityPost extends Model
{
    use HasUuids;

    public const PUBLISHED = 'published';

    /** Waiting for a moderator because the filter flagged it. */
    public const HELD = 'held';

    public const HIDDEN = 'hidden';

    public const REMOVED = 'removed';

    protected $primaryKey = 'post_id';

    protected $fillable = ['space_id', 'user_id', 'parent_id', 'title', 'body', 'status', 'held_reason', 'last_activity_at'];

    protected $casts = [
        'edited_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'comments_count' => 'integer',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function getRouteKeyName(): string
    {
        return 'post_id';
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(CommunitySpace::class, 'space_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'post_id')->oldest();
    }
}
