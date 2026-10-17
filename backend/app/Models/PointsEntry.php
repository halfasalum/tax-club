<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line in a member's points ledger (GAM-REQ08). Entries are never
 * changed; corrections are new entries.
 */
class PointsEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'points_ledger';

    protected $fillable = ['user_id', 'points', 'reason', 'source_type', 'source_id', 'group_key', 'description', 'awarded_by'];

    protected $casts = ['points' => 'integer', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Points entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Points entries cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by', 'user_id');
    }

    public function label(): string
    {
        return match ($this->reason) {
            'lesson_completed' => __('Lesson completed'),
            'module_completed' => __('Module completed'),
            'quiz' => __('Quiz result'),
            'adjustment' => __('Adjustment by TRA'),
            'opening_balance' => __('Earlier points'),
            default => __(ucfirst(str_replace('_', ' ', $this->reason))),
        };
    }
}
