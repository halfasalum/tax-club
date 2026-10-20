<?php

namespace App\Models;

use App\Models\Concerns\HasReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An alumnus's experience or career story, published after review
 * (ALM-REQ03).
 */
class AlumniStory extends Model
{
    use HasReview, HasUuids;

    public const KINDS = ['story', 'career_tip'];

    protected $primaryKey = 'story_id';

    protected $fillable = ['user_id', 'title', 'slug', 'body', 'kind'];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AlumniStory $story) {
            if (blank($story->slug)) {
                $story->slug = (Str::slug(Str::limit($story->title, 80, '')) ?: 'story').'-'.Str::lower(Str::random(5));
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    public function reviewSubjectType(): string
    {
        return 'alumni_story';
    }

    public function reviewUrl(): string
    {
        return route('admin.alumni.stories');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
