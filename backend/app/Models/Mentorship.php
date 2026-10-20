<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mentor and mentee working together (MNT-REQ03-06).
 */
class Mentorship extends Model
{
    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    public const CLOSED = 'closed';

    public const OUTCOMES = ['goals_met', 'partly_met', 'not_met', 'ended_early'];

    protected $fillable = ['mentor_id', 'mentee_id', 'request_id', 'matched_by', 'supervisor_id', 'status', 'started_at', 'last_activity_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'mentee_rating' => 'integer',
    ];

    public function involves(User $user): bool
    {
        return in_array($user->user_id, [$this->mentor_id, $this->mentee_id], true);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id', 'user_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id', 'user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id', 'user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MentorshipSession::class)->orderByDesc('held_on')->orderByDesc('id');
    }
}
