<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $timestamps = false; // uses custom created_at / last_login

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password_hash',
        'user_type',
        'institution_id',
        'membership_id',
        'is_verified',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'created_at'  => 'datetime',
        'last_login'  => 'datetime',
    ];

    /**
     * Laravel's auth system expects getAuthPassword() to return the password field.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ─── RBAC Helpers ─────────────────────────────────────────────

    /**
     * Check if the user has a specific role by slug.
     * Usage: $user->hasRole('admin')
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->where('is_active', true)->exists();
    }

    /**
     * Check if the user has any of the given roles.
     * Usage: $user->hasAnyRole(['admin', 'educator'])
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->where('is_active', true)->exists();
    }

    /**
     * Check if the user has a specific module control permission by slug.
     * Usage: $user->hasControl('add_user')
     */
    public function hasControl(string $controlSlug): bool
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas('controls', function ($query) use ($controlSlug) {
                $query->where('slug', $controlSlug)
                      ->where('module_controls.is_active', true);
            })->exists();
    }

    /**
     * Get all control slugs this user has (via their roles).
     * Useful for returning permissions in API responses.
     */
    public function allControls(): array
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->with(['controls' => fn($q) => $q->where('module_controls.is_active', true)])
            ->get()
            ->pluck('controls')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->values()
            ->toArray();
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'institution_id');
    }

    public function roles(): BelongsToMany
    {
        // No withTimestamps() — user_roles uses assigned_at instead of
        // standard created_at/updated_at columns.
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
                    ->withPivot('assigned_by', 'assigned_at');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'user_id', 'user_id');
    }

    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'user_id', 'user_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserProgress::class, 'user_id', 'user_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations', 'user_id', 'event_id')
                    ->withPivot('status', 'registered_at');
    }

    public function uploadedContent(): HasMany
    {
        return $this->hasMany(Content::class, 'uploaded_by', 'user_id');
    }

    public function postedOpportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'posted_by', 'user_id');
    }
}