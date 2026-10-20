<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reward given to a member, tracked until it is delivered (RWD-REQ02).
 */
class RewardAward extends Model
{
    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const ISSUED = 'issued';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['reward_id', 'user_id', 'source_type', 'source_id', 'reason', 'status', 'awarded_by'];

    protected $casts = [
        'approved_at' => 'datetime',
        'issued_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
