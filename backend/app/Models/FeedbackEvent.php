<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a feedback case. Internal notes are never shown to the
 * member who sent the feedback (FBK-REQ03, FBK-REQ04).
 */
class FeedbackEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['feedback_id', 'action', 'note', 'internal', 'user_id'];

    protected $casts = ['internal' => 'boolean', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
