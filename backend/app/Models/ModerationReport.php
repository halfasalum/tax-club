<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's report within a moderation case (SAF-REQ01).
 */
class ModerationReport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['case_id', 'reporter_id', 'category', 'details'];

    protected $casts = ['created_at' => 'datetime'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id', 'user_id');
    }
}
