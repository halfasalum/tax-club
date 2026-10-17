<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in the review and publication history of content or a quiz
 * (LRN-REQ02/03, GAM-REQ03/04).
 */
class EditorialEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['subject_type', 'subject_id', 'version', 'action', 'note', 'user_id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
