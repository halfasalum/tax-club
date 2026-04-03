<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumPost extends Model
{
    use HasUuids;

    protected $table = 'forum_posts';
    protected $primaryKey = 'post_id';

    protected $fillable = [
        'user_id',
        'parent_post_id',
        'title',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    /**
     * Only top-level posts (not replies).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_post_id');
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Parent post (if this is a reply).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'parent_post_id', 'post_id');
    }

    /**
     * Direct replies to this post.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'parent_post_id', 'post_id');
    }

    /**
     * All nested replies recursively.
     */
    public function allReplies(): HasMany
    {
        return $this->replies()->with('allReplies');
    }
}