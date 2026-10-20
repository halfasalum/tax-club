<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reports about one piece of content or one person, handled as a case
 * (SAF-REQ01/02, COM-REQ07/08).
 */
class ModerationCase extends Model
{
    use HasUuids;

    public const OPEN = 'open';

    public const INVESTIGATING = 'investigating';

    public const CLOSED = 'closed';

    public const CATEGORIES = ['spam', 'bullying', 'hate', 'sexual', 'violence', 'self_harm', 'grooming', 'contact_sharing', 'personal_info', 'misinformation', 'impersonation', 'other'];

    /** Categories that go straight to safeguarding officers as urgent. */
    public const SAFEGUARDING_CATEGORIES = ['sexual', 'self_harm', 'grooming', 'violence'];

    public const OUTCOMES = ['actioned', 'no_violation', 'duplicate', 'referred'];

    protected $primaryKey = 'case_id';

    protected $fillable = ['reference', 'subject_type', 'subject_id', 'subject_user_id', 'category', 'source', 'status', 'priority', 'safeguarding', 'involves_minor', 'assigned_to'];

    protected $casts = [
        'safeguarding' => 'boolean',
        'involves_minor' => 'boolean',
        'resolved_at' => 'datetime',
        'reports_count' => 'integer',
    ];

    /**
     * Cases an officer may see: safeguarding cases only for safeguarding
     * officers (and whoever they are assigned to).
     */
    public function scopeVisibleTo(Builder $query, User $officer): Builder
    {
        if ($officer->hasControl('handle_safeguarding_concerns')) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->where('safeguarding', false)->orWhere('assigned_to', $officer->user_id));
    }

    public function isOpen(): bool
    {
        return $this->status !== self::CLOSED;
    }

    public function getRouteKeyName(): string
    {
        return 'case_id';
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ModerationReport::class, 'case_id', 'case_id')->latest('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ModerationAction::class, 'case_id', 'case_id')->latest('id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id', 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }
}
