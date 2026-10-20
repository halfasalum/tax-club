<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's registration and entry in a competition (CMP-REQ03/04).
 */
class CompetitionEntry extends Model
{
    public const REGISTERED = 'registered';

    public const SUBMITTED = 'submitted';

    public const WITHDRAWN = 'withdrawn';

    public const DISQUALIFIED = 'disqualified';

    protected $fillable = ['competition_id', 'user_id', 'status', 'registered_at'];

    protected $casts = [
        'registered_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'float',
        'rank' => 'integer',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id', 'competition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CompetitionScore::class, 'entry_id');
    }
}
