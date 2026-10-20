<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded mentorship meeting: what was covered, not a transcript
 * (MNT-REQ04).
 */
class MentorshipSession extends Model
{
    public const UPDATED_AT = null;

    public const MODES = ['in_person', 'phone', 'video', 'at_club'];

    protected $fillable = ['mentorship_id', 'held_on', 'mode', 'duration_minutes', 'topic', 'summary', 'milestone', 'logged_by'];

    protected $casts = ['held_on' => 'date', 'milestone' => 'boolean', 'created_at' => 'datetime'];

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by', 'user_id');
    }
}
