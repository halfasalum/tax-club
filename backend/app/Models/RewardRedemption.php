<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    use HasUuids;

    protected $table = 'reward_redemptions';

    protected $primaryKey = 'redemption_id';

    protected $fillable = [
        'user_id',
        'reward_id',
        'points_spent',
        'status',
        'notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'points_spent' => 'integer',
        'processed_at' => 'datetime',
    ];

    // ─── Relationships ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class, 'reward_id', 'reward_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by', 'user_id');
    }
}
