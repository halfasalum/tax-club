<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Feedback, a suggestion or a concern from a member, handled as a
 * numbered case (FBK-REQ01-04).
 */
class Feedback extends Model
{
    use Auditable, HasUuids;

    public const CATEGORIES = ['suggestion', 'problem', 'content', 'event', 'account', 'concern', 'other'];

    public const AREAS = ['ict', 'content', 'events', 'membership', 'opportunities', 'safeguarding', 'general'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const SUBMITTED = 'submitted';

    public const IN_REVIEW = 'in_review';

    public const RESPONDED = 'responded';

    public const CLOSED = 'closed';

    public const STATUSES = [self::SUBMITTED, self::IN_REVIEW, self::RESPONDED, self::CLOSED];

    /** Concerns about safety go straight to safeguarding officers. */
    public const SAFEGUARDING = 'safeguarding';

    protected $table = 'feedback';

    protected $primaryKey = 'feedback_id';

    /** @var list<string> */
    protected array $auditIgnore = ['message', 'response'];

    protected $fillable = ['reference', 'user_id', 'category', 'subject', 'message', 'related_type', 'related_id', 'channel', 'status', 'priority', 'area', 'assigned_to'];

    protected $casts = [
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Feedback an officer may see: everything except safeguarding cases,
     * which only safeguarding officers and the assigned officer see.
     */
    public function scopeVisibleTo(Builder $query, User $officer): Builder
    {
        if ($officer->hasControl('handle_safeguarding_concerns')) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->where('area', '!=', self::SAFEGUARDING)->orWhereNull('area')->orWhere('assigned_to', $officer->user_id));
    }

    public function isSafeguarding(): bool
    {
        return $this->area === self::SAFEGUARDING;
    }

    public function isOpen(): bool
    {
        return $this->status !== self::CLOSED;
    }

    public function getRouteKeyName(): string
    {
        return 'feedback_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FeedbackEvent::class, 'feedback_id', 'feedback_id')->orderBy('id');
    }
}
