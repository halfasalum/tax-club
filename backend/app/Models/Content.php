<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Content extends Model
{
    use HasUuids;

    protected $table = 'content';
    protected $primaryKey = 'content_id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'content_type',
        'file_url',
        'body_text',
        'uploaded_by',
        'published_at',
        'is_active',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('content_type', $type);
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