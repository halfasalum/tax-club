<?php

namespace App\Models;

use App\Support\Audience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A moderated discussion space or collaboration group (COM-REQ05, COM-REQ09).
 *
 * - national, thematic: open to members in the audience
 * - institution: members of one institution
 * - event, competition: people taking part
 * - group: chosen members from different institutions
 */
class CommunitySpace extends Model
{
    public const TYPES = ['national', 'institution', 'thematic', 'event', 'competition', 'group'];

    public const ACTIVE = 'active';

    public const LOCKED = 'locked';

    public const ARCHIVED = 'archived';

    protected $fillable = ['slug', 'name', 'description', 'type', 'institution_id', 'event_id', 'competition_id', 'audience', 'members_only', 'moderators_post_only', 'status', 'created_by'];

    protected $casts = [
        'audience' => 'array',
        'members_only' => 'boolean',
        'moderators_post_only' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CommunitySpace $space) {
            if (blank($space->slug)) {
                $base = Str::slug(Str::limit($space->name, 80, '')) ?: 'space';
                $space->slug = static::where('slug', $base)->exists() ? $base.'-'.Str::lower(Str::random(4)) : $base;
            }

            if ($space->type === 'group') {
                $space->members_only = true;
            }
        });
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('status', '!=', self::ARCHIVED);
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('users.user_id', $user->user_id)->exists();
    }

    public function isModerator(User $user): bool
    {
        return $user->hasControl('moderate_community')
            || $this->members()->where('users.user_id', $user->user_id)->wherePivot('role', 'moderator')->exists();
    }

    /**
     * Whether a member may read the space.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->status === self::ARCHIVED && ! $user->hasControl('moderate_community')) {
            return false;
        }

        if ($user->hasControl('moderate_community') || $this->isMember($user)) {
            return true;
        }

        if ($this->members_only) {
            return false;
        }

        return match ($this->type) {
            'institution' => $user->institution_id === $this->institution_id,
            'event' => $this->event_id && EventRegistration::where('event_id', $this->event_id)->where('user_id', $user->user_id)->whereIn('status', ['registered', 'attended'])->exists(),
            'competition' => $this->competition_id && CompetitionEntry::where('competition_id', $this->competition_id)->where('user_id', $user->user_id)->where('status', '!=', 'withdrawn')->exists(),
            default => Audience::includes($this->audience, $user),
        };
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_space_members', 'space_id', 'user_id')->withPivot('role', 'added_by', 'created_at');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'space_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'institution_id', 'institution_id');
    }
}
