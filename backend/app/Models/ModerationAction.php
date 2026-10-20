<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of every moderation and safeguarding step
 * (SAF-REQ03/04/06).
 */
class ModerationAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['case_id', 'action', 'target_type', 'target_id', 'target_user_id', 'reason', 'internal', 'performed_by'];

    protected $casts = ['internal' => 'boolean', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by', 'user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id', 'user_id');
    }
}
