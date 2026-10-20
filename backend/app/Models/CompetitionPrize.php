<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The reward for a range of ranks in a competition (RWD-REQ01).
 */
class CompetitionPrize extends Model
{
    public $timestamps = false;

    protected $fillable = ['competition_id', 'rank_from', 'rank_to', 'reward_id'];

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }
}
