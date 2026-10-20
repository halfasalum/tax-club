<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject area for learning content (LRN-REQ04).
 */
class ContentCategory extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name_sw', 'name_en', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name_en');
    }

    public function name(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_sw;
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'category_id');
    }
}
