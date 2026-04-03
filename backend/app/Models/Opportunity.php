<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Opportunity extends Model
{
    use HasUuids;

    protected $table = 'opportunities';
    protected $primaryKey = 'opportunity_id';

    protected $fillable = [
        'title',
        'organization',
        'type',
        'description',
        'deadline',
        'posted_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'deadline'  => 'date',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Only listings whose deadline hasn't passed.
     */
    public function scopeOpen($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('deadline')
                           ->orWhere('deadline', '>=', now()->toDateString());
                     });
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by', 'user_id');
    }
}