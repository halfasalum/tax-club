<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Content extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'content';

    protected $primaryKey = 'content_id';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content_type',
        'file_url',
        'body_text',
        'uploaded_by',
        'published_at',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Published content that may be shown on the public website.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where('is_active', true)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('content_type', $type);
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

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class, 'content_id', 'content_id');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserProgress::class, 'content_id', 'content_id');
    }
}
