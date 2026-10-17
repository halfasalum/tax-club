<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * An ordered set of lessons members work through (LRN-REQ06).
 */
class LearningModule extends Model
{
    use Auditable, HasFactory;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    protected $fillable = ['slug', 'title', 'summary', 'category_id', 'level', 'audience', 'status', 'sort_order', 'created_by', 'published_at'];

    protected $casts = [
        'audience' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (LearningModule $module) {
            if (blank($module->slug)) {
                $base = Str::slug(Str::limit($module->title, 80, '')) ?: 'module';
                $module->slug = static::where('slug', $base)->exists() ? $base.'-'.Str::lower(Str::random(4)) : $base;
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }

    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        if ($user->user_type === 'staff') {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->whereNull('audience')
            ->orWhereJsonLength('audience', 0)
            ->orWhereJsonContains('audience', $user->user_type));
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'learning_module_items', 'module_id', 'content_id')
            ->withPivot('position', 'is_required')
            ->orderByPivot('position');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }
}
