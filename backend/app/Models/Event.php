<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'events';

    protected $primaryKey = 'event_id';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('is_active', true)->where('starts_at', '>=', now());
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            if (blank($event->slug) && filled($event->title)) {
                $base = Str::slug(Str::limit($event->title, 80, '')) ?: 'event';
                $slug = $base;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.Str::lower(Str::random(5));
                }

                $event->slug = $slug;
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Users registered for this event (M:N via event_registrations).
     */
    public function registeredUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations', 'event_id', 'user_id')
            ->withPivot('status', 'registered_at');
    }
}
